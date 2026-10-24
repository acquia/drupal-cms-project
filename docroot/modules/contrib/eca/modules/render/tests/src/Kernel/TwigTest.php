<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests regarding ECA render Twig action.
 */
#[Group('eca')]
#[Group('eca_render')]
#[RunTestsInSeparateProcesses]
class TwigTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_twig".
   */
  public function testTwig(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Twig $action */
    $action = $this->actionManager->createInstance('eca_render_twig', [
      'template' => 'Hello {{ user.name.value }}!',
      'value' => '[user]',
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

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));

    $this->dispatchBasicRenderEvent();

    $this->assertEquals('Hello admin!', $build[0]['#markup']);
  }

  /**
   * Tests that the template source is not subject to raw [token] replacement.
   *
   * This proves the Server-Side Template Injection (SSTI) hole is closed.
   *
   * Under the OLD vulnerable behavior, the template source was run through
   * the token service before being compiled by Twig. A token whose value
   * contained Twig syntax (here "{{ 7 * 7 }}") would therefore be injected
   * into the template source and then EXECUTED as template code, yielding
   * "49" — classic SSTI / information disclosure.
   *
   * Under the FIXED behavior, the template source is compiled verbatim and
   * dynamic data only reaches the template via the safe #context variables.
   * Twig has no "[my_payload]" construct, so a template of "[my_payload]"
   * is plain text and must pass through literally. The injected token value
   * is never evaluated, so the output must NOT contain "49".
   */
  public function testTwigNoSourceInjection(): void {
    // A token whose value is a Twig-injection payload. Under the old behavior
    // this value would have been spliced into the template source and run.
    $this->tokenService->addTokenData('my_payload', '{{ 7 * 7 }}');

    /** @var \Drupal\eca_render\Plugin\Action\Twig $action */
    $action = $this->actionManager->createInstance('eca_render_twig', [
      'template' => '[my_payload]',
      'value' => '',
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

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));

    $this->dispatchBasicRenderEvent();

    $markup = (string) $build[0]['#markup'];
    // No raw [token] source replacement: the template is compiled verbatim.
    $this->assertEquals('[my_payload]', $markup);
    // The injection payload was never evaluated as template code.
    $this->assertStringNotContainsString('49', $markup);
  }

}
