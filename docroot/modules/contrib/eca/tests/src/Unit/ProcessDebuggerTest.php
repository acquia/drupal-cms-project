<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\eca\ProcessDebugger;
use Drupal\eca\Token\Browser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that ProcessDebugger::exception() produces serializable history.
 *
 * Browser::compress() runs serialize() + gzcompress() on the history array
 * and silently returns '' on failure (catching \Throwable). Before this fix,
 * exception() stored a raw \Exception object whose trace can contain
 * non-serializable closures and circular references, causing compress()
 * to silently discard all debug replay data for the entire ECA run.
 *
 * @see \Drupal\eca\ProcessDebugger::exception()
 * @see \Drupal\eca\Token\Browser::compress()
 */
#[Group('eca')]
#[Group('eca_core')]
class ProcessDebuggerTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    ProcessDebugger::$debug = FALSE;
    parent::tearDown();
  }

  /**
   * Creates a ProcessDebugger with a minimal Browser instance.
   *
   * Browser is final and its constructor requires 9 service dependencies.
   * We bypass both constructors via newInstanceWithoutConstructor() and
   * set Browser::$isRunning = TRUE so normalizedTokenData() returns [].
   */
  private function createDebugger(): ProcessDebugger {
    $browserReflector = new \ReflectionClass(Browser::class);
    $browser = $browserReflector->newInstanceWithoutConstructor();
    $browserReflector->getProperty('isRunning')->setValue($browser, TRUE);

    $reflector = new \ReflectionClass(ProcessDebugger::class);
    $debugger = $reflector->newInstanceWithoutConstructor();

    $reflector->getProperty('tokenBrowser')->setValue($debugger, $browser);
    $reflector->getProperty('ecaId')->setValue($debugger, 'test_model');
    $reflector->getProperty('eventId')->setValue($debugger, 'test_event');
    $reflector->getProperty('history')->setValue($debugger, []);
    $reflector->getProperty('lastTokenDataHash')->setValue($debugger, '');
    $reflector->getProperty('eventName')->setValue($debugger, 'test_event');

    return $debugger;
  }

  /**
   * Returns the history array from a ProcessDebugger via reflection.
   */
  private function getHistory(ProcessDebugger $debugger): array {
    $prop = new \ReflectionProperty(ProcessDebugger::class, 'history');
    return $prop->getValue($debugger);
  }

  /**
   * Replicates Browser::compress() to test serialization behavior.
   *
   * Browser::compress() is private. This replicates its exact logic:
   * serialize + gzcompress, catching \Throwable and returning '' on failure.
   * This is the operation that silently discards data when the history
   * contains non-serializable objects.
   */
  private function compress(array $data): string {
    try {
      return gzcompress(serialize($data));
    }
    catch (\Throwable) {
      return '';
    }
  }

  /**
   * Creates an exception whose trace contains a non-serializable Closure.
   *
   * PHP captures function arguments in Exception::$trace. By throwing from
   * a method that received a Closure, the Closure ends up in the trace and
   * breaks serialize().
   *
   * @return \RuntimeException
   *   An exception with a non-serializable trace.
   */
  private function createNonSerializableException(): \RuntimeException {
    try {
      $this->throwWithArg(function () {
        return 'callback';
      });
    }
    catch (\RuntimeException $ex) {
      return $ex;
    }
  }

  /**
   * Throws an exception from a function that received a non-serializable arg.
   *
   * @param mixed $arg
   *   A non-serializable value (Closure, anonymous class, etc.).
   */
  private function throwWithArg(mixed $arg): void {
    throw new \RuntimeException('Action failed');
  }

  /**
   * Tests that exception() records nothing when debug is disabled.
   */
  public function testExceptionDoesNothingWhenDebugDisabled(): void {
    ProcessDebugger::$debug = FALSE;
    $debugger = $this->createDebugger();
    $debugger->exception('action_id', new \stdClass(), new \RuntimeException('Test error'));

    $this->assertEmpty($this->getHistory($debugger));
  }

  /**
   * Tests that exception() history survives compression.
   *
   * When an exception propagates through Drupal's event dispatcher or plugin
   * manager, its trace often contains non-serializable values (closures,
   * anonymous classes). Browser::compress() calls serialize() + gzcompress()
   * and silently returns '' on failure. This test verifies that the
   * debugger's exception() output compresses without data loss.
   *
   * Before the fix, this test FAILS: exception() stored the raw \Exception
   * object, so compress() silently returned '' -- losing all replay data.
   */
  public function testExceptionHistorySurvivesCompression(): void {
    ProcessDebugger::$debug = TRUE;
    $debugger = $this->createDebugger();

    $ex = $this->createNonSerializableException();
    $debugger->exception('my_action', new \stdClass(), $ex);
    $history = $this->getHistory($debugger);
    $this->assertNotEmpty($history, 'Debugger must record the exception entry');

    // The history must survive Browser::compress(). Before the fix,
    // compress() silently returned '' here -- losing all debug replay data.
    $compressed = $this->compress($history);
    $this->assertNotSame('', $compressed, 'Exception history must survive compression (serialize + gzcompress)');
  }

  /**
   * Tests the full structure and round-trip of an exception entry.
   *
   * After the fix, exception() stores exception details as a plain array
   * (class, code, message, file, trace) instead of the raw object. The
   * executed object is still included for context. This ensures
   * Browser::compress() succeeds and replay data is persisted -- even when
   * the exception trace contains non-serializable values.
   */
  public function testExceptionEntryStructureAndRoundTrip(): void {
    ProcessDebugger::$debug = TRUE;
    $debugger = $this->createDebugger();

    $ex = $this->createNonSerializableException();
    $debugger->exception('my_action', new \stdClass(), $ex);
    $history = $this->getHistory($debugger);

    // Verify the entry structure.
    $this->assertCount(1, $history);
    $entry = $history[0];
    $this->assertSame('exception', $entry['type']);
    $this->assertSame('my_action', $entry['id']);
    $this->assertInstanceOf(\stdClass::class, $entry['object']);
    $this->assertIsArray($entry['exception']);
    $this->assertSame('RuntimeException', $entry['exception']['class']);
    $this->assertSame(0, $entry['exception']['code']);
    $this->assertSame('Action failed', $entry['exception']['message']);
    $this->assertArrayHasKey('file', $entry['exception']);
    $this->assertIsString($entry['exception']['trace']);
    $this->assertNotEmpty($entry['exception']['trace']);

    // The fixed entry compresses and round-trips successfully -- even though
    // the original exception contained a non-serializable Closure.
    $compressed = $this->compress($history);
    $this->assertNotSame('', $compressed, 'Exception entry must survive compression');

    $restored = unserialize(gzuncompress($compressed));
    $this->assertSame('exception', $restored[0]['type']);
    $this->assertSame('RuntimeException', $restored[0]['exception']['class']);
    $this->assertSame(0, $restored[0]['exception']['code']);
    $this->assertSame('Action failed', $restored[0]['exception']['message']);
    $this->assertIsString($restored[0]['exception']['trace']);
  }

}
