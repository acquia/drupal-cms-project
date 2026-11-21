<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that google_tag replaces the google_analytics page attachments.
 *
 * @group google_tag
 * @requires module google_analytics
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class GoogleAnalyticsPageAttachmentsTest extends GoogleTagTestCase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['google_analytics', 'google_tag_test'];

  /**
   * Tests the page_attachments implementations google_tag leaves in place.
   */
  public function testPageAttachmentsImplementations(): void {
    // ModuleHandler::hasImplementations() is not used: before Drupal 11.1 it
    // returns TRUE whenever the function exists, ignoring the alter hook.
    $modules = [];
    $this->container->get('module_handler')->invokeAllWith('page_attachments', static function (callable $hook, string $module) use (&$modules): void {
      $modules[] = $module;
    });
    self::assertNotContains('google_analytics', $modules);
    self::assertSame('google_tag', end($modules));
    self::assertContains('google_tag_test', $modules);
  }

}
