<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\Component\Render\MarkupInterface;
use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use Drupal\node\Entity\Node;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests regarding ECA render Views action.
 */
#[Group('eca')]
#[Group('eca_render')]
#[RunTestsInSeparateProcesses]
class ViewsTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_views".
   */
  public function testViews(): void {
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'body' => $this->randomMachineName(),
      'type' => 'article',
      'status' => TRUE,
    ]);
    $node->save();

    View::create([
      'id' => 'test_view',
      'label' => 'Test View',
      'base_table' => 'node_field_data',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [
            'fields' => [
              'title' => [
                'id' => 'title',
                'table' => 'node_field_data',
                'field' => 'title',
              ],
            ],
            'filters' => [
              'status' => [
                'id' => 'status',
                'table' => 'node_field_data',
                'field' => 'status',
                'value' => '1',
              ],
            ],
          ],
        ],
      ],
    ])->save();

    /** @var \Drupal\eca_render\Plugin\Action\Views $action */
    $action = $this->actionManager->createInstance('eca_render_views:views', [
      'view_id' => 'test_view',
      'display_id' => 'default',
      'arguments' => '',
      'token_name' => '',
      'name' => '',
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
    $this->assertInstanceOf(MarkupInterface::class, $build[0]['#markup']);
    // Change node status to unpublished so that the view returns an empty.
    $node->setUnpublished()->save();

    $build = [];
    $this->eventDispatcher->addListener(RenderBasicsEvents::BASIC, function (BasicRenderEvent $event) use (&$action, &$build) {
      $action->setEvent($event);
      $action->execute();
      $build = $event->getRenderArray();
    });

    $this->dispatchBasicRenderEvent([]);
    $this->assertEmpty($build);
  }

}
