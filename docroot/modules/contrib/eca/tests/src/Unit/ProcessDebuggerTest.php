<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\eca\ProcessDebugger;
use Drupal\eca\Token\Browser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that ProcessDebugger::exception() produces serializable history.
 *
 * Browser::compress() runs json_encode() + gzcompress() on the history array
 * and silently returns '' on failure (catching \Throwable). Before this fix,
 * exception() stored a raw \Exception object whose trace can contain
 * closures and circular references that cannot be JSON-encoded, causing
 * compress() to silently discard all debug replay data for the entire ECA run.
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
   * json_encode + gzcompress, catching \Throwable and returning '' on failure.
   * This is the operation that silently discards data when the history
   * contains values that cannot be JSON-encoded.
   */
  private function compress(array $data): string {
    try {
      return gzcompress(json_encode($data, JSON_THROW_ON_ERROR));
    }
    catch (\Throwable) {
      return '';
    }
  }

  /**
   * Creates an exception whose trace contains a Closure.
   *
   * PHP captures function arguments in Exception::$trace. By throwing from
   * a method that received a Closure, the Closure ends up in the trace, which
   * cannot be JSON-encoded.
   *
   * @return \RuntimeException
   *   An exception whose trace cannot be JSON-encoded.
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
   * manager, its trace often contains values that cannot be JSON-encoded
   * (closures, anonymous classes). Browser::compress() calls json_encode() +
   * gzcompress() and silently returns '' on failure. This test verifies that
   * the debugger's exception() output compresses without data loss.
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
    $this->assertNotSame('', $compressed, 'Exception history must survive compression (json_encode + gzcompress)');
  }

  /**
   * Tests the full structure and round-trip of an exception entry.
   *
   * After the fix, exception() stores exception details as a plain array
   * (class, code, message, file, trace) and reduces the operating object to a
   * JSON-safe descriptor string. This ensures Browser::compress() succeeds and
   * replay data is persisted -- even when the exception trace contains
   * non-serializable values.
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
    // The operating object is reduced to a JSON-safe descriptor string,
    // never the raw object.
    $this->assertSame('[Object: stdClass]', $entry['object']);
    $this->assertIsArray($entry['exception']);
    $this->assertSame('RuntimeException', $entry['exception']['class']);
    $this->assertSame(0, $entry['exception']['code']);
    $this->assertSame('Action failed', $entry['exception']['message']);
    $this->assertArrayHasKey('file', $entry['exception']);
    $this->assertIsString($entry['exception']['trace']);
    $this->assertNotEmpty($entry['exception']['trace']);

    // The fixed entry compresses and round-trips successfully -- even though
    // the original exception contained a Closure that cannot be JSON-encoded.
    $compressed = $this->compress($history);
    $this->assertNotSame('', $compressed, 'Exception entry must survive compression');

    $restored = json_decode(gzuncompress($compressed), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame('exception', $restored[0]['type']);
    $this->assertSame('[Object: stdClass]', $restored[0]['object']);
    $this->assertSame('RuntimeException', $restored[0]['exception']['class']);
    $this->assertSame(0, $restored[0]['exception']['code']);
    $this->assertSame('Action failed', $restored[0]['exception']['message']);
    $this->assertIsString($restored[0]['exception']['trace']);
  }

  /**
   * Tests that a non-JSON-encodable operating object cannot break compression.
   *
   * The operating object passed to execute()/exception() is arbitrary: an
   * entity, a closure-bearing object or one with circular references. Raw, such
   * objects make json_encode(JSON_THROW_ON_ERROR) throw, which would make
   * Browser::compress() silently return '' and discard the entire run's replay
   * history. This test passes an object with a circular reference (which alone
   * makes json_encode() throw with "Recursion detected") and proves the
   * recorded history still compresses and round-trips, because execute()
   * stores a JSON-safe descriptor instead of the raw object.
   *
   * @see \Drupal\eca\ProcessDebugger::describeObject()
   * @see \Drupal\eca\Token\Browser::compress()
   */
  public function testNonEncodableObjectIsReducedToSafeDescriptor(): void {
    // A circular reference: json_encode() throws "Recursion detected" on this.
    $recursive = new \stdClass();
    $recursive->self = $recursive;

    // Confirm the precondition: the raw object truly breaks JSON encoding.
    $threw = FALSE;
    try {
      json_encode(['object' => $recursive], JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      $threw = TRUE;
    }
    $this->assertTrue($threw, 'The raw object must be non-JSON-encodable for this test to be meaningful');

    ProcessDebugger::$debug = TRUE;
    $debugger = $this->createDebugger();
    $debugger->execute('my_action', $recursive);
    $history = $this->getHistory($debugger);

    // The stored object descriptor must be a plain string, not the object.
    $this->assertSame('[Object: stdClass]', $history[0]['object']);

    // The history now compresses without throwing and round-trips intact.
    $compressed = $this->compress($history);
    $this->assertNotSame('', $compressed, 'History with a recursive object must still compress');
    $restored = json_decode(gzuncompress($compressed), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame('[Object: stdClass]', $restored[0]['object']);
  }

  /**
   * Tests the round-trip of Browser::compress() and Browser::decompress().
   *
   * Verifies that data compressed with json_encode() + gzcompress() is
   * faithfully restored by decompress() using json_decode(). This guards the
   * security fix that replaced serialize()/unserialize() with JSON, removing
   * the PHP object injection vector while preserving the storage round-trip.
   *
   * @see \Drupal\eca\Token\Browser::compress()
   * @see \Drupal\eca\Token\Browser::decompress()
   */
  public function testCompressDecompressRoundTrip(): void {
    $browserReflector = new \ReflectionClass(Browser::class);
    $browser = $browserReflector->newInstanceWithoutConstructor();
    $compress = $browserReflector->getMethod('compress');
    $decompress = $browserReflector->getMethod('decompress');
    $prefix = $browserReflector->getConstant('COMPRESS_PREFIX');

    $data = [
      'string' => 'value',
      'int' => 42,
      'float' => 1.5,
      'bool' => TRUE,
      'null' => NULL,
      'nested' => ['a' => ['b' => ['c' => 'deep']]],
      'list' => [1, 2, 3],
    ];

    $compressed = $compress->invoke($browser, $data);
    $this->assertNotSame('', $compressed, 'Data must compress successfully');
    $this->assertStringStartsWith($prefix, $compressed, 'Compressed data must carry the prefix');

    $restored = $decompress->invoke($browser, $compressed);
    $this->assertSame($data, $restored, 'decompress() must restore the original data');

    // An empty array round-trips as an empty array (json_encode([]) is "[]").
    $emptyCompressed = $compress->invoke($browser, []);
    $this->assertNotSame('', $emptyCompressed, 'An empty array must compress successfully');
    $this->assertSame([], $decompress->invoke($browser, $emptyCompressed), 'decompress() must restore an empty array');
  }

  /**
   * Tests that decompress() returns an empty array for legacy serialized data.
   *
   * Data previously stored with serialize() is no longer decodable as JSON.
   * decompress() must fail safe and return an empty array rather than calling
   * unserialize(), so the PHP object injection vector stays closed.
   *
   * @see \Drupal\eca\Token\Browser::decompress()
   */
  public function testDecompressRejectsLegacySerializedData(): void {
    $browserReflector = new \ReflectionClass(Browser::class);
    $browser = $browserReflector->newInstanceWithoutConstructor();
    $decompress = $browserReflector->getMethod('decompress');
    $prefix = $browserReflector->getConstant('COMPRESS_PREFIX');

    // Build a payload exactly as the old compress() did: serialize + gzcompress
    // with the compression prefix.
    $legacy = $prefix . gzcompress(serialize(['legacy' => 'data']));

    $restored = $decompress->invoke($browser, $legacy);
    $this->assertSame([], $restored, 'Legacy serialized data must not be decoded');
  }

}
