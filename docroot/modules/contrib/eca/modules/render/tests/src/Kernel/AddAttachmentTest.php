<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\Core\Render\Element;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests regarding ECA attachment actions.
 */
#[Group('eca')]
#[Group('eca_render')]
#[RunTestsInSeparateProcesses]
class AddAttachmentTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_add_attached_library".
   */
  #[DataProvider('attachLibraryDataProvider')]
  public function testAttachedLibrary($target_name, $library, $build, $build_result): void {
    /** @var \Drupal\eca_render\Plugin\Action\AddAttachedLibrary $action */
    $action = $this->actionManager->createInstance('eca_render_add_attached_library', [
      'name' => $target_name,
      'value' => $library,
    ]);

    $event = new BasicRenderEvent($build);
    $action->setEvent($event);
    $action->execute();
    $build = $event->getRenderArray();

    $this->assertSame($build_result, $build);
  }

  /**
   * Tests the action plugin "eca_render_add_attached_setting".
   */
  public function testAttachedSetting(): void {
    /** @var \Drupal\eca_render\Plugin\Action\AddAttachedSetting $action */
    $action = $this->actionManager->createInstance('eca_render_add_attached_setting', [
      'name' => 'test_element',
      'collection' => 'test_collection',
      'key' => 'test_key',
      'value' => '[data]',
    ]);

    $build = [
      'test_element' => [
        '#type' => 'markup',
        '#markup' => "Hello I am a markup",
      ],
    ];
    $event = new BasicRenderEvent($build);
    $action->setEvent($event);

    $token_data = [
      'test_1' => 'test_string',
      'test_2' => [
        'test_arr',
      ],
    ];
    $this->tokenService->addTokenData('data', DataTransferObject::create($token_data));
    $action->execute();
    $build = $event->getRenderArray();

    $build = array_intersect_key($build, array_flip(Element::children($build)));
    $this->assertSame([
      'test_element' => [
        '#type' => 'markup',
        '#markup' => "Hello I am a markup",
        '#attached' => [
          'drupalSettings' => [
            'test_collection' => [
              'test_key' => $token_data,
            ],
          ],
        ],
      ],
    ], $build);
  }

  /**
   * Provides multiple attachment test cases for testAttachedLibrary method.
   *
   * @return \Generator
   *   The library attachment test cases.
   */
  public static function attachLibraryDataProvider(): \Generator {
    $element_name = 'test_element';
    $build = [
      $element_name => [
        '#type' => 'markup',
        '#markup' => "Hello I am a markup",
      ],
    ];
    $library = 'core/drupal.debounce';
    $attachment = [
      '#attached' => [
        'library' => [
          $library,
        ],
      ],
    ];

    yield 'with plain library name' => [
      $element_name,
      $library,
      $build,
      array_merge_recursive($build, [$element_name => $attachment]),
    ];

    yield 'with blank library token' => [
      $element_name,
      '[nonexistent_token]',
      $build,
      array_merge_recursive($build, [$element_name => ['#attached' => []]]),
    ];

    yield 'with root of build array' => [
      '',
      $library,
      $build,
      $build + $attachment,
    ];
  }

}
