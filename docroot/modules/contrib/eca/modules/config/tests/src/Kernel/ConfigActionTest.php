<?php

namespace Drupal\Tests\eca_config\Kernel;

use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * Kernel tests for the "eca_config_action" action plugin.
 */
#[Group('eca')]
#[Group('eca_config')]
#[RunTestsInSeparateProcesses]
class ConfigActionTest extends Base {

  /**
   * The service name of a logger that collects everything being logged.
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
   * Tests the boolean and object forms of ConfigAction::access().
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccess(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $config_action_manager */
    $config_action_manager = \Drupal::service('plugin.manager.config_action');
    $definitions = $config_action_manager->getDefinitions();
    $this->assertNotEmpty($definitions, 'Expected at least one config action plugin.');
    $action_id = (string) array_key_first($definitions);

    $config = [
      'config_name' => 'system.site',
      'action_id' => $action_id,
      'data' => '',
    ];

    // Anonymous user (no "administer site configuration" permission) is denied
    // in both the boolean and the object form.
    /** @var \Drupal\eca_config\Plugin\Action\ConfigAction $action */
    $action = $action_manager->createInstance('eca_config_action', $config);
    $this->assertFalse($action->access(NULL), 'Anonymous user must be denied (boolean form).');
    $this->assertFalse($action->access(NULL, NULL, TRUE)->isAllowed(), 'Anonymous user must be denied (object form).');

    // An admin with the permission and a valid action must be allowed in the
    // boolean form. Regression: access() previously always returned FALSE in
    // the boolean form regardless of the result.
    $admin = User::load(1);
    $action = $action_manager->createInstance('eca_config_action', $config);
    $this->assertTrue($action->access(NULL, $admin), 'Admin with permission and valid action must be allowed (boolean form).');
    $this->assertTrue($action->access(NULL, $admin, TRUE)->isAllowed(), 'Admin with permission and valid action must be allowed (object form).');

    // An unknown config action id is denied even for an admin.
    $invalid = $config;
    $invalid['action_id'] = 'no_such_config_action';
    $action = $action_manager->createInstance('eca_config_action', $invalid);
    $this->assertFalse($action->access(NULL, $admin), 'Invalid config action must be denied (boolean form).');
    $this->assertFalse($action->access(NULL, $admin, TRUE)->isAllowed(), 'Invalid config action must be denied (object form).');
  }

  /**
   * Tests that access() rejects a config name that resolves to nothing.
   *
   * Uid 2 is used throughout instead of uid 1: it holds "administer site
   * configuration" through the test role, so it genuinely passes the
   * permission gate, whereas uid 1 would bypass the permission check
   * altogether and could not tell the two branches apart.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessInvalidConfigName(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    $account = User::load(2);

    // Control: with a valid config name this very account is allowed, so the
    // denials below cannot be explained away by a missing permission.
    /** @var \Drupal\eca_config\Plugin\Action\ConfigAction $action */
    $action = $action_manager->createInstance('eca_config_action', [
      'config_name' => 'system.site',
      'action_id' => 'simpleConfigUpdate',
      'data' => '',
    ]);
    $this->assertTrue($action->access(NULL, $account), 'The control case must be allowed.');

    // A config name consisting of whitespace only is trimmed away and must be
    // rejected with the dedicated "Invalid config name." reason, which also
    // distinguishes this branch from the "Invalid config action." one.
    /** @var \Drupal\eca_config\Plugin\Action\ConfigAction $action */
    $action = $action_manager->createInstance('eca_config_action', [
      'config_name' => '   ',
      'action_id' => 'simpleConfigUpdate',
      'data' => '',
    ]);
    $this->assertFalse($action->access(NULL, $account), 'A blank config name must be denied (boolean form).');
    $result = $action->access(NULL, $account, TRUE);
    $this->assertInstanceOf(AccessResultReasonInterface::class, $result);
    $this->assertFalse($result->isAllowed(), 'A blank config name must be denied (object form).');
    $this->assertSame('Invalid config name.', $result->getReason(), 'A blank config name must be denied because of the config name.');

    // A config name made up of a token that cannot be resolved is cleared to
    // an empty string and must be rejected for the very same reason.
    /** @var \Drupal\eca_config\Plugin\Action\ConfigAction $action */
    $action = $action_manager->createInstance('eca_config_action', [
      'config_name' => '[no_such_token:value]',
      'action_id' => 'simpleConfigUpdate',
      'data' => '',
    ]);
    $this->assertFalse($action->access(NULL, $account), 'An unresolvable config name token must be denied (boolean form).');
    $result = $action->access(NULL, $account, TRUE);
    $this->assertInstanceOf(AccessResultReasonInterface::class, $result);
    $this->assertFalse($result->isAllowed(), 'An unresolvable config name token must be denied (object form).');
    $this->assertSame('Invalid config name.', $result->getReason(), 'An unresolvable config name token must be denied because of the config name.');
  }

  /**
   * Tests the "_eca_token" sentinel for "action_id" inside access().
   *
   * The sentinel means "read the real value from the companion token", which
   * the configuration form names after the plugin ID plus the field key, so
   * "eca_config_action_action_id" here.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessActionIdFromToken(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    $account = User::load(2);

    $config = [
      'config_name' => 'system.site',
      'action_id' => '_eca_token',
      'data' => '',
    ];

    // Without the companion token the sentinel falls back to the documented
    // default of an empty string, which is never a valid config action.
    /** @var \Drupal\eca_config\Plugin\Action\ConfigAction $action */
    $action = $action_manager->createInstance('eca_config_action', $config);
    $this->assertFalse($action->access(NULL, $account), 'Without the companion token the sentinel must fall back to an invalid action (boolean form).');
    $result = $action->access(NULL, $account, TRUE);
    $this->assertInstanceOf(AccessResultReasonInterface::class, $result);
    $this->assertFalse($result->isAllowed(), 'Without the companion token the sentinel must fall back to an invalid action (object form).');
    $this->assertSame('Invalid config action.', $result->getReason(), 'The denial must be caused by the action id, not the config name.');

    // With the companion token in place the real action id is used.
    $token_services->addTokenData('eca_config_action_action_id', 'simpleConfigUpdate');
    /** @var \Drupal\eca_config\Plugin\Action\ConfigAction $action */
    $action = $action_manager->createInstance('eca_config_action', $config);
    $this->assertTrue($action->access(NULL, $account), 'With the companion token the resolved action must be allowed (boolean form).');
    $this->assertTrue($action->access(NULL, $account, TRUE)->isAllowed(), 'With the companion token the resolved action must be allowed (object form).');
  }

  /**
   * Tests that execute() applies the configured core config action.
   *
   * "system.site" is simple configuration rather than a config entity, so
   * SimpleConfigUpdate::apply() does not take its deprecation path.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testExecute(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $config = \Drupal::configFactory()->getEditable('system.site');
    $config->set('slogan', 'Before ECA');
    $config->save();
    $this->assertSame('Before ECA', \Drupal::configFactory()->get('system.site')->get('slogan'));

    /** @var \Drupal\eca_config\Plugin\Action\ConfigAction $action */
    $action = $action_manager->createInstance('eca_config_action', [
      'config_name' => 'system.site',
      'action_id' => 'simpleConfigUpdate',
      'data' => "slogan: 'After ECA'",
    ]);
    $action->execute();
    $this->assertSame('After ECA', \Drupal::configFactory()->get('system.site')->get('slogan'), 'The config action must have been applied.');

    // Both the config name and the data support token replacement.
    $token_services->addTokenData('my_config_name', 'system.site');
    $token_services->addTokenData('my_slogan', 'Slogan from a token');
    /** @var \Drupal\eca_config\Plugin\Action\ConfigAction $action */
    $action = $action_manager->createInstance('eca_config_action', [
      'config_name' => '[my_config_name]',
      'action_id' => 'simpleConfigUpdate',
      'data' => 'slogan: "[my_slogan]"',
    ]);
    $action->execute();
    $this->assertSame('Slogan from a token', \Drupal::configFactory()->get('system.site')->get('slogan'), 'The config name and the data must both be token aware.');
  }

  /**
   * Tests that execute() bails out and logs when the data is not valid YAML.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testExecuteMalformedYaml(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Symfony\Component\ErrorHandler\BufferingLogger $logger */
    $logger = $this->container->get(self::$testLogServiceName);
    $logger->cleanLogs();

    $config = \Drupal::configFactory()->getEditable('system.site');
    $config->set('slogan', 'Untouched');
    $config->save();

    // An unterminated inline mapping cannot be parsed.
    /** @var \Drupal\eca_config\Plugin\Action\ConfigAction $action */
    $action = $action_manager->createInstance('eca_config_action', [
      'config_name' => 'system.site',
      'action_id' => 'simpleConfigUpdate',
      'data' => '{ slogan: broken',
    ]);
    $action->execute();

    $this->assertSame('Untouched', \Drupal::configFactory()->get('system.site')->get('slogan'), 'Malformed data must leave the configuration alone.');
    $messages = array_column($logger->cleanLogs(), 1);
    $this->assertContains('Tried parsing data in action "eca_config_action", but parsing failed.', $messages, 'The parse failure must be logged.');
  }

  /**
   * Tests the "_eca_token" sentinel for "action_id" inside execute().
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testExecuteActionIdFromToken(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $config = \Drupal::configFactory()->getEditable('system.site');
    $config->set('slogan', 'Before ECA');
    $config->save();

    $token_services->addTokenData('eca_config_action_action_id', 'simpleConfigUpdate');
    /** @var \Drupal\eca_config\Plugin\Action\ConfigAction $action */
    $action = $action_manager->createInstance('eca_config_action', [
      'config_name' => 'system.site',
      'action_id' => '_eca_token',
      'data' => "slogan: 'Applied through the sentinel'",
    ]);
    $action->execute();

    // Without the sentinel resolution the literal "_eca_token" would reach
    // ConfigActionManager::applyAction() and no config action by that name
    // exists, so this assertion can only pass if the token was resolved.
    $this->assertSame('Applied through the sentinel', \Drupal::configFactory()->get('system.site')->get('slogan'), 'The action id must be resolved from the companion token.');
  }

}
