<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\Core\Render\Markup as RenderMarkup;
use Drupal\eca_render\Event\EcaRenderExtraFieldEvent;
use Drupal\eca_render\RenderEvents;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the ECA render extra field hook contract.
 *
 * Exercises the real code path through
 * \Drupal\eca_render\Hook\RenderHooks::triggerExtraField() via an entity view
 * and an entity form. ECA registers an extra field when an Eca config entity
 * subscribes to the "eca_render:extra_field" event. The shipped test model
 * (eca_test_render_extra_field) registers two extra fields:
 * - "eca_test_display" on the node view display.
 * - "eca_test_form" on the node form display.
 *
 * The test asserts that a content-free build (only cache metadata, no visible
 * children, no markup) is marked "#access => FALSE" and not turned into a
 * container, while a build with real content is rendered (and wrapped as a
 * container on a form display).
 *
 * @see \Drupal\eca_render\Hook\RenderHooks::triggerExtraField()
 */
#[Group('eca')]
#[Group('eca_render')]
#[RunTestsInSeparateProcesses]
class ExtraFieldTest extends RenderActionsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'options',
    'node',
    'image',
    'responsive_image',
    'serialization',
    'views',
    'breakpoint',
    'eca',
    'eca_base',
    'eca_render',
    'eca_test_render_basics',
    'modeler_api',
    'eca_test_render_extra_field',
  ];

  /**
   * The name of the view display extra field component.
   *
   * @var string
   */
  protected const DISPLAY_FIELD = 'eca_render__eca_test_display';

  /**
   * The name of the form display extra field component.
   *
   * @var string
   */
  protected const FORM_FIELD = 'eca_render__eca_test_form';

  /**
   * Tests that a content-free extra field build is marked inaccessible.
   *
   * On a view display, a build that only carries cache metadata produces no
   * output. The hook must mark it "#access => FALSE" so grouping modules treat
   * it as empty, and must not stamp a "#type" on it.
   */
  public function testEmptyExtraFieldOnViewDisplay(): void {
    $node = $this->createArticle();

    // The ECA event listener sets a content-free build: only cache metadata,
    // no visible children and no markup. This mimics e.g. an empty view whose
    // render action only bubbles cache metadata.
    $this->eventDispatcher->addListener(RenderEvents::EXTRA_FIELD, function (EcaRenderExtraFieldEvent $event): void {
      if ($event->getExtraFieldName() === 'eca_test_display') {
        $build = &$event->getRenderArray();
        $build['#cache'] = [
          'contexts' => ['user'],
          'tags' => ['config:eca_list'],
        ];
      }
    });

    $build = $this->buildEntityView($node);

    $this->assertArrayHasKey(self::DISPLAY_FIELD, $build, 'The extra field element is present in the build so its cache metadata still bubbles up.');
    $this->assertArrayHasKey('#access', $build[self::DISPLAY_FIELD], 'A content-free extra field carries an explicit access value.');
    $this->assertFalse($build[self::DISPLAY_FIELD]['#access'], 'A content-free extra field is marked inaccessible.');
    $this->assertArrayNotHasKey('#type', $build[self::DISPLAY_FIELD], 'A content-free extra field is not stamped as a container.');
    // The cache metadata is preserved on the element.
    $this->assertArrayHasKey('#cache', $build[self::DISPLAY_FIELD], 'The cache metadata is preserved for bubbling.');
  }

  /**
   * Tests that an extra field with real content is rendered on a view display.
   */
  public function testContentfulExtraFieldOnViewDisplay(): void {
    $node = $this->createArticle();

    $markup = $this->randomMachineName();
    $this->eventDispatcher->addListener(RenderEvents::EXTRA_FIELD, function (EcaRenderExtraFieldEvent $event) use ($markup): void {
      if ($event->getExtraFieldName() === 'eca_test_display') {
        $build = &$event->getRenderArray();
        $build['#markup'] = RenderMarkup::create($markup);
        $build['#cache'] = [
          'contexts' => ['user'],
          'tags' => ['config:eca_list'],
        ];
      }
    });

    $build = $this->buildEntityView($node);

    $this->assertArrayHasKey(self::DISPLAY_FIELD, $build, 'The extra field element is present in the build.');
    $this->assertArrayNotHasKey('#access', $build[self::DISPLAY_FIELD], 'A content-bearing extra field is not marked inaccessible.');
    // On a view display the hook does not wrap the element as a container.
    $this->assertArrayNotHasKey('#type', $build[self::DISPLAY_FIELD], 'A view display extra field is not wrapped as a container.');
    $this->assertSame($markup, (string) $build[self::DISPLAY_FIELD]['#markup'], 'The extra field carries the configured markup.');
  }

  /**
   * Tests that a content-free extra field on a form display is inaccessible.
   */
  public function testEmptyExtraFieldOnFormDisplay(): void {
    $node = $this->createArticle();

    $this->eventDispatcher->addListener(RenderEvents::EXTRA_FIELD, function (EcaRenderExtraFieldEvent $event): void {
      if ($event->getExtraFieldName() === 'eca_test_form') {
        $build = &$event->getRenderArray();
        $build['#cache'] = [
          'contexts' => ['user'],
          'tags' => ['config:eca_list'],
        ];
      }
    });

    $form = $this->buildEntityForm($node);

    $this->assertArrayHasKey(self::FORM_FIELD, $form, 'The extra field element is present in the form so its cache metadata still bubbles up.');
    $this->assertArrayHasKey('#access', $form[self::FORM_FIELD], 'A content-free extra field carries an explicit access value.');
    $this->assertFalse($form[self::FORM_FIELD]['#access'], 'A content-free extra field is marked inaccessible on a form display.');
    $this->assertArrayNotHasKey('#type', $form[self::FORM_FIELD], 'A content-free extra field is not stamped as a container, even on a form display.');
  }

  /**
   * Tests that a content-bearing extra field on a form display is a container.
   */
  public function testContentfulExtraFieldOnFormDisplay(): void {
    $node = $this->createArticle();

    $markup = $this->randomMachineName();
    $this->eventDispatcher->addListener(RenderEvents::EXTRA_FIELD, function (EcaRenderExtraFieldEvent $event) use ($markup): void {
      if ($event->getExtraFieldName() === 'eca_test_form') {
        $build = &$event->getRenderArray();
        $build['#markup'] = RenderMarkup::create($markup);
      }
    });

    $form = $this->buildEntityForm($node);

    $this->assertArrayHasKey(self::FORM_FIELD, $form, 'The extra field element is present in the form.');
    $this->assertArrayNotHasKey('#access', $form[self::FORM_FIELD], 'A content-bearing extra field is not marked inaccessible.');
    $this->assertSame('container', $form[self::FORM_FIELD]['#type'], 'A content-bearing form display extra field is wrapped as a container so it can be grouped.');
    $this->assertSame($markup, (string) $form[self::FORM_FIELD]['#markup'], 'The extra field carries the configured markup.');
  }

  /**
   * Creates a published article node.
   *
   * @return \Drupal\node\Entity\Node
   *   The saved node.
   */
  protected function createArticle(): Node {
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'type' => 'article',
      'status' => TRUE,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Builds the default view render array for a node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to view.
   *
   * @return array
   *   The render array as produced by the entity view builder, including any
   *   extra field components contributed by ECA via hook_entity_view().
   */
  protected function buildEntityView(Node $node): array {
    $this->switchToAdmin();
    $view_builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
    // ::view() defers the actual build (including hook_entity_view, where ECA
    // contributes its extra fields) to a #pre_render callback. Run that build
    // step synchronously via ::buildMultiple() so the extra field components
    // are present in the returned render array.
    $build = $view_builder->view($node, 'default');
    $build_list = $view_builder->buildMultiple([$build]);
    return $build_list[0];
  }

  /**
   * Builds the default edit form render array for a node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to edit.
   *
   * @return array
   *   The form render array, including any extra field components contributed
   *   by ECA via hook_form_alter().
   */
  protected function buildEntityForm(Node $node): array {
    $this->switchToAdmin();
    return $this->container->get('entity.form_builder')->getForm($node, 'default');
  }

  /**
   * Switches to the admin user (uid 1) so the entity view/form is accessible.
   */
  protected function switchToAdmin(): void {
    $this->container->get('account_switcher')->switchTo(User::load(1));
  }

}
