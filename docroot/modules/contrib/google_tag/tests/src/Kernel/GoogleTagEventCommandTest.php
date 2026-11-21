<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CommandWithAttachedAssetsInterface;
use Drupal\google_tag\Ajax\GoogleTagEventCommand;
use Drupal\google_tag\Entity\TagContainer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the gtag.ajax library is attached by the Ajax command.
 *
 * The library is only needed to handle the gtagEvent command, so it must ride
 * along with the Ajax response that carries such a command instead of being
 * attached to every page, where it pulls in the whole core/drupal.ajax
 * dependency chain - including jQuery - for nothing.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class GoogleTagEventCommandTest extends GoogleTagTestCase {

  /**
   * The library handled by the gtagEvent command.
   */
  private const AJAX_LIBRARY = 'google_tag/gtag.ajax';

  /**
   * Tests that the command declares the library it needs.
   */
  public function testCommandDeclaresLibrary(): void {
    $command = new GoogleTagEventCommand('purchase', ['value' => 42]);
    self::assertInstanceOf(CommandWithAttachedAssetsInterface::class, $command);
    self::assertSame([self::AJAX_LIBRARY], $command->getAttachedAssets()->getLibraries());
  }

  /**
   * Tests that adding the command attaches the library to the response.
   */
  public function testCommandAttachesLibraryToResponse(): void {
    $response = new AjaxResponse();
    self::assertSame([], $response->getAttachments());

    $response->addCommand(new GoogleTagEventCommand('purchase', ['value' => 42]));

    $attachments = $response->getAttachments();
    self::assertArrayHasKey('library', $attachments);
    self::assertContains(self::AJAX_LIBRARY, $attachments['library']);
  }

  /**
   * Tests that the command is still rendered as before.
   */
  public function testCommandRender(): void {
    $command = new GoogleTagEventCommand('purchase', ['value' => 42]);
    self::assertSame([
      'command' => 'gtagEvent',
      'event_name' => 'purchase',
      'data' => ['value' => 42],
    ], $command->render());
  }

  /**
   * Tests that the library is not attached to regular page responses.
   */
  public function testLibraryIsNotAttachedToPages(): void {
    TagContainer::create([
      'id' => 'foo',
      'tag_container_ids' => ['GT-XXXXXX'],
    ])->save();

    $page = [];
    $this->container
      ->get('main_content_renderer.html')
      ->invokePageAttachmentHooks($page);

    // The tag itself is still needed on every page, the Ajax handler is not.
    self::assertContains('google_tag/gtag', $page['#attached']['library']);
    self::assertNotContains(self::AJAX_LIBRARY, $page['#attached']['library']);
  }

}
