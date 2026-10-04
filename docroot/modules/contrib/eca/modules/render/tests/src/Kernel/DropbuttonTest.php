<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests regarding ECA render Dropbutton action.
 */
#[Group('eca')]
#[Group('eca_render')]
#[RunTestsInSeparateProcesses]
class DropbuttonTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_custom_form".
   */
  public function testDropbutton(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Dropbutton $action */
    $action = $this->actionManager->createInstance('eca_render_dropbutton', [
      'dropbutton_type' => 'small',
      'links' => '[links]',
      'use_yaml' => FALSE,
      'name' => '',
      'token_name' => '',
      'weight' => '100',
      'mode' => 'append',
    ]);

    $build = [];
    $this->eventDispatcher->addListener(RenderBasicsEvents::BASIC, function (BasicRenderEvent $event) use (&$action, &$build) {
      $action->setEvent($event);
      $action->execute();
      $build = $event->getRenderArray();
    });

    $this->tokenService->addTokenData('links', [
      ['title' => 'Structure', 'url' => '/admin/structure'],
      ['title' => 'Config', 'url' => '/admin/config'],
    ]);

    $this->dispatchBasicRenderEvent([]);

    $this->assertTrue(isset($build[0]));
    $this->assertSame('dropbutton', $build[0]['#type']);
    $this->assertSame('Structure', $build[0]['#links'][0]['title']);
    $this->assertSame('Config', $build[0]['#links'][1]['title']);
  }

  /**
   * Tests YAML validation in the "eca_render_dropbutton" access check.
   *
   * The access check must evaluate the "links" configuration key, which is the
   * key this plugin actually stores. Reading a non-existent "value" key passes
   * NULL into the string-typed YamlParser::parse() and throws a TypeError
   * instead of returning an access result.
   *
   * @see https://git.drupalcode.org/project/eca/-/work_items/3590420
   */
  public function testDropbuttonYamlValidation(): void {
    $links = <<<YAML
-
  title: Structure
  url: "/admin/structure"
YAML;
    /** @var \Drupal\eca_render\Plugin\Action\Dropbutton $valid */
    $valid = $this->actionManager->createInstance('eca_render_dropbutton', [
      'dropbutton_type' => 'small',
      'links' => $links,
      'use_yaml' => TRUE,
      'validate_yaml' => TRUE,
      'name' => '',
      'token_name' => '',
      'weight' => '100',
      'mode' => 'append',
    ]);
    /** @var \Drupal\eca_render\Plugin\Action\Dropbutton $invalid */
    $invalid = $this->actionManager->createInstance('eca_render_dropbutton', [
      'dropbutton_type' => 'small',
      'links' => 'title: "unclosed',
      'use_yaml' => TRUE,
      'validate_yaml' => TRUE,
      'name' => '',
      'token_name' => '',
      'weight' => '100',
      'mode' => 'append',
    ]);

    $valid_access = NULL;
    $invalid_access = NULL;
    $this->eventDispatcher->addListener(RenderBasicsEvents::BASIC, function (BasicRenderEvent $event) use ($valid, $invalid, &$valid_access, &$invalid_access) {
      $valid->setEvent($event);
      $invalid->setEvent($event);
      $valid_access = $valid->access(NULL);
      $invalid_access = $invalid->access(NULL);
    });

    $this->dispatchBasicRenderEvent([]);

    $this->assertTrue($valid_access, 'Valid YAML is allowed.');
    $this->assertFalse($invalid_access, 'Malformed YAML is forbidden.');
  }

}
