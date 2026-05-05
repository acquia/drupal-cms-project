<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests regarding ECA render CustomForm action.
 */
#[Group('eca')]
#[Group('eca_render')]
#[RunTestsInSeparateProcesses]
class CustomFormTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_custom_form".
   */
  public function testCustomForm(): void {
    /** @var \Drupal\eca_render\Plugin\Action\CustomForm $action */
    $action = $this->actionManager->createInstance('eca_render_custom_form', [
      'custom_form_id' => 'my_custom_form',
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

    $this->dispatchBasicRenderEvent([]);

    $this->assertTrue(isset($build[0]));
    $this->assertSame('eca_custom_my_custom_form', $build[0]['#form_id']);
  }

}
