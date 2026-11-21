<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel;

use Drupal\google_tag\Drush\Commands\GoogleTagCreateCommand;
use Drupal\google_tag\Entity\TagContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the google_tag:create Drush command.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class DrushCommandsTest extends GoogleTagTestCase {

  /**
   * Runs the command under test.
   */
  private CommandTester $tester;

  /**
   * Messages logged by the command, keyed by log level.
   *
   * @var array<string, string[]>
   */
  private array $messages = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['google_tag']);

    $messages = &$this->messages;
    $logger = new class($messages) extends AbstractLogger {

      /**
       * Constructs the logger.
       *
       * @param array<string, string[]> $messages
       *   The array to collect messages in.
       */
      public function __construct(private array &$messages) {}

      /**
       * {@inheritdoc}
       */
      public function log($level, \Stringable|string $message, array $context = []): void {
        $this->messages[$level][] = (string) $message;
      }

    };
    $command = new GoogleTagCreateCommand(
      $this->container->get('entity_type.manager'),
      $this->container->get('config.factory'),
      $this->container->get('plugin.manager.google_tag_event'),
      $logger,
    );
    $this->tester = new CommandTester($command);
  }

  /**
   * Tests that the command creates a container like the form does.
   */
  public function testCreate(): void {
    $status = $this->tester->execute(['--id' => ' GTM-ABC1234 ', '--label' => 'Default container']);
    self::assertSame(Command::SUCCESS, $status);

    // Only the machine name is printed, so scripts can capture it.
    $id = trim($this->tester->getDisplay());
    self::assertStringStartsWith('GTM-ABC1234.', $id);
    self::assertCount(1, $this->messages['notice']);
    $container = TagContainer::load($id);
    self::assertInstanceOf(TagContainer::class, $container);
    self::assertSame('Default container', $container->label());
    self::assertSame(['GTM-ABC1234'], $container->get('tag_container_ids'));
    self::assertTrue($container->status());
    self::assertSame($id, $this->config('google_tag.settings')->get('default_google_tag_entity'));

    // Every event is enabled with its default settings, as on the form.
    $event_ids = array_keys($this->container->get('plugin.manager.google_tag_event')->getDefinitions());
    self::assertNotEmpty($event_ids);
    self::assertEqualsCanonicalizing($event_ids, array_keys($container->get('events')));

    // With --force, a second container is created for the same ID. Its label
    // defaults to the tag ID.
    self::assertSame(Command::SUCCESS, $this->tester->execute(['--id' => 'GTM-ABC1234', '--force' => TRUE]));
    $second_id = trim($this->tester->getDisplay());
    self::assertNotSame($id, $second_id);
    self::assertSame('GTM-ABC1234', TagContainer::load($second_id)?->label());
    self::assertSame($second_id, $this->config('google_tag.settings')->get('default_google_tag_entity'));
  }

  /**
   * Tests that a tag ID already used by a container is rejected.
   */
  public function testExistingTagId(): void {
    // The ID is the second of the existing container's IDs, and is stored
    // with whitespace, as a recipe can leave it.
    TagContainer::create([
      'id' => 'existing',
      'label' => 'Existing',
      'tag_container_ids' => ['G-AAAA1111', ' GTM-ABC1234'],
    ])->save();
    $this->config('google_tag.settings')->set('default_google_tag_entity', 'existing')->save();

    $status = $this->tester->execute(['--id' => 'GTM-ABC1234']);
    self::assertSame(Command::FAILURE, $status);
    self::assertCount(1, $this->messages['error']);
    self::assertStringContainsString('"Existing" (existing)', $this->messages['error'][0]);
    self::assertSame('', $this->tester->getDisplay());
    self::assertSame(['existing'], array_keys(TagContainer::loadMultiple()));
    self::assertSame('existing', $this->config('google_tag.settings')->get('default_google_tag_entity'));
  }

  /**
   * Tests that invalid tag IDs are rejected without creating a container.
   *
   * @dataProvider providerInvalidTagId
   */
  #[DataProvider('providerInvalidTagId')]
  public function testInvalidTagId(?string $tag_id): void {
    $input = $tag_id === NULL ? [] : ['--id' => $tag_id];
    $status = $this->tester->execute($input + ['--label' => 'Invalid']);

    // A failed create exits non-zero, so provisioning scripts stop.
    self::assertSame(Command::FAILURE, $status);
    self::assertCount(1, $this->messages['error']);
    self::assertSame('', $this->tester->getDisplay());
    self::assertSame([], TagContainer::loadMultiple());
    self::assertSame('', $this->config('google_tag.settings')->get('default_google_tag_entity'));
  }

  /**
   * Data provider for testInvalidTagId().
   *
   * @return array<string, array{?string}>
   *   Test cases.
   */
  public static function providerInvalidTagId(): array {
    return [
      'missing' => [NULL],
      'empty' => [''],
      'unknown prefix' => ['XX-ABC1234'],
      'too short' => ['GTM-ABC'],
      'surrounding text' => ['fooGTM-ABC1234bar'],
      'too long' => ['GTM-ABCDEFGHIJKLMNOPQ'],
    ];
  }

}
