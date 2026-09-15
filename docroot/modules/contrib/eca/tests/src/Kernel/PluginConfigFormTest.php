<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\Action\ActionInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * Tests for config forms of ECA plugins.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class PluginConfigFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'views',
    'workflows',
    'content_moderation',
    'eca',
    'eca_base',
    'eca_cache',
    'eca_config',
    'eca_content',
    'eca_form',
    'eca_log',
    'eca_migrate',
    'eca_misc',
    'eca_queue',
    'eca_user',
    'eca_views',
    'eca_workflow',
    'modeler_api',
  ];

  /**
   * The service name for a logger implementation that collects anything logged.
   *
   * @var string
   */
  protected static string $testLogServiceName = 'eca_test.logger';

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container
      ->register(self::$testLogServiceName, BufferingLogger::class)
      ->addTag('logger');
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('view');
    $this->installEntitySchema('workflow');
    $this->installConfig(static::$modules);

    // Prepare the logger for collecting ECA log messages.
    $this->container->get(self::$testLogServiceName)->cleanLogs();
  }

  /**
   * Tests configuration forms of plugins.
   */
  public function testPluginConfigForms(): void {
    /** @var \Drupal\eca\Service\Events $eventManager */
    $eventManager = \Drupal::service('eca.service.event');
    foreach ($eventManager->events() as $event) {
      $this->doExecute('event', $event->getPluginId(), $event);
    }

    /** @var \Drupal\eca\Service\Conditions $conditionManager */
    $conditionManager = \Drupal::service('eca.service.condition');
    foreach ($conditionManager->conditions() as $condition) {
      $this->doExecute('condition', $condition->getPluginId(), $condition);
    }

    /** @var \Drupal\eca\Service\Actions $actionManager */
    $actionManager = \Drupal::service('eca.service.action');
    foreach ($actionManager->actions() as $action) {
      // Check that it's an ECA action plugin, we don't want to test core
      // plugins.
      if ($action instanceof ActionInterface) {
        $this->doExecute('action', $action->getPluginId(), $action);
      }
    }
  }

  /**
   * Positive control for the three assertEmpty() calls in ::doExecute().
   *
   * ::doExecute() asserts three times that the buffering logger registered as
   * self::$testLogServiceName is empty after a plugin has built, validated and
   * submitted its configuration form. On its own, an empty collector is
   * ambiguous: it means either that no plugin logged an error, or that nothing
   * is being collected at all. Nothing else in this class can tell those two
   * states apart. So if the path from the plugins to that service ever broke -
   * the "logger" tag disappearing from ::register(), the logger factory no
   * longer passing its collected loggers on to the ECA channel, or the
   * "log_level" setting of "eca.settings" dropping below RfcLogLevel::ERROR -
   * then all three assertions would keep passing, the suite would stay green,
   * and the coverage would silently be worth nothing.
   *
   * This test removes that ambiguity by logging an error through the very
   * service the plugins log through, "logger.channel.eca", and asserting that
   * the collector picks it up. It therefore fails exactly when those three
   * assertions would have turned vacuous, and it does not duplicate them: they
   * go red when a plugin logs something, this one goes red when nothing can be
   * collected any more.
   *
   * @see \Drupal\eca\Plugin\Action\ActionBase::create()
   * @see \Drupal\eca\ConfigurableLoggerChannel::log()
   */
  public function testLogCollectorPositiveControl(): void {
    $message = 'Positive control entry for the ECA log collector.';

    // Log through the same channel service that the plugins are given, so that
    // this test exercises the collection path ::doExecute() relies on instead
    // of a logger of its own making. A logger set up here would look like
    // coverage without proving that the plugins reach the collector.
    $this->container->get('logger.channel.eca')->error($message);

    $log_messages = $this->container->get(self::$testLogServiceName)->cleanLogs();
    $this->assertCount(1, $log_messages, 'The test logger should collect what ECA plugins log through their logger channel.');
    // BufferingLogger keeps every entry as [level, message, context].
    [$level, $collected_message, $context] = $log_messages[0];
    $this->assertSame(RfcLogLevel::ERROR, $level, 'The collected entry should keep the severity it was logged with.');
    $this->assertSame($message, $collected_message, 'The collected entry should be the one this test logged.');
    $this->assertSame('eca', $context['channel'] ?? NULL, 'The collected entry should have passed through the ECA logger channel.');

    // Reading the entries above already drained the collector, so this test
    // leaves nothing behind that could make an assertEmpty() in ::doExecute()
    // fail. Each test method runs in its own process and setUp() empties the
    // collector as well, but stating it here keeps the guarantee local.
    $this->assertEmpty($this->container->get(self::$testLogServiceName)->cleanLogs(), 'This test should not leave a collected entry behind.');
  }

  /**
   * Execute all the config form assertions for a given plugin.
   *
   * @param string $type
   *   The plugin type, either event, condition or action.
   * @param string $id
   *   The plugin id.
   * @param mixed $plugin
   *   The plugin.
   */
  private function doExecute(string $type, string $id, mixed $plugin): void {
    if ($plugin instanceof PluginFormInterface) {
      $form_state = new FormState();

      $form = $plugin->buildConfigurationForm([], $form_state);
      $this->assertIsArray($form, 'The form for event ' . $id . ' should be an array.');
      $log_messages = $this->container->get(self::$testLogServiceName)->cleanLogs();
      $this->assertEmpty($log_messages, 'Building the form for ' . $type . ' ' . $id . ' should not produce any errors.');

      $plugin->validateConfigurationForm($form, $form_state);
      $log_messages = $this->container->get(self::$testLogServiceName)->cleanLogs();
      $this->assertEmpty($log_messages, 'Validating the form for ' . $type . ' ' . $id . ' should not produce any errors.');

      $plugin->submitConfigurationForm($form, $form_state);
      $log_messages = $this->container->get(self::$testLogServiceName)->cleanLogs();
      $this->assertEmpty($log_messages, 'Submitting the form for ' . $type . ' ' . $id . ' should not produce any errors.');
    }
  }

}
