<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests regarding ECA render GetActiveTheme action.
 */
#[Group('eca')]
#[Group('eca_render')]
#[RunTestsInSeparateProcesses]
class GetActiveThemeTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_get_active_theme".
   */
  public function testGetActiveTheme(): void {
    /** @var \Drupal\eca_render\Plugin\Action\GetActiveTheme $action */
    $action = $this->actionManager->createInstance('eca_get_active_theme', [
      'token_name' => 'theme_name',
    ]);

    $this->eventDispatcher->addListener(RenderBasicsEvents::BASIC, function (BasicRenderEvent $event) use (&$action, &$build) {
      $action->setEvent($event);
      $action->execute();
      $build = $event->getRenderArray();
    });

    \Drupal::service('theme.manager')->setActiveTheme(\Drupal::service('theme.initialization')->getActiveThemeByName('olivero'));
    $this->dispatchBasicRenderEvent([]);
    $this->assertEquals('olivero', $this->tokenService->replaceClear('[theme_name]'));
  }

}
