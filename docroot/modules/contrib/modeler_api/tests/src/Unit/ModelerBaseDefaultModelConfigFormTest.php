<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Unit;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Render\Element;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerBase;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the grouping of the shared model configuration form.
 *
 * The resulting form array is a contract: the Workflow Modeler renders it
 * through its own form to JSON converter, so the groups, their child order and
 * the absence of #tree all have to stay as they are.
 */
#[CoversClass(ModelerBase::class)]
#[Group('modeler_api')]
class ModelerBaseDefaultModelConfigFormTest extends UnitTestCase {

  /**
   * The model configuration the form is built from.
   */
  private const array MODEL_CONFIG = [
    'label' => 'Metadata test',
    'model_id' => 'metadata_test',
    'version' => '1.0.0',
    'executable' => TRUE,
    'template' => FALSE,
    'storage' => '',
    'documentation' => 'Documents the model.',
    'summary' => 'Enriches articles on save.',
    'recipes' => ['core/recipes/article_tags'],
    'export_config' => ['core.entity_form_display.node.article.default'],
    'modules' => ['node'],
    'config_actions' => [
      ['config' => 'user.role.editor', 'actions' => ['grantPermission' => 'access content']],
    ],
    'tags' => 'demo, test',
    'changelog' => 'Initial version.',
  ];

  /**
   * Tests that both groups are collapsed details elements without #tree.
   */
  public function testGroupsAreCollapsedDetailsWithoutTree(): void {
    $form = $this->buildForm();

    foreach (['recipe_export', 'advanced'] as $group) {
      $this->assertArrayHasKey($group, $form);
      $this->assertSame('details', $form[$group]['#type']);
      $this->assertFalse($form[$group]['#open']);
      // The modeler binds inputs by their flat name attribute, so the groups
      // must not turn the values into a nested tree.
      $this->assertArrayNotHasKey('#tree', $form[$group]);
    }
  }

  /**
   * Tests the fields each group holds, in render order.
   */
  public function testGroupsHoldTheirFieldsInOrder(): void {
    $form = $this->buildForm();

    $this->assertSame(
      ['summary', 'recipes', 'export_config', 'modules', 'config_actions'],
      Element::children($form['recipe_export']),
    );
    $this->assertSame(
      ['version', 'storage', 'changelog'],
      Element::children($form['advanced']),
    );
  }

  /**
   * Tests the top level order when the owner supports status and templates.
   */
  public function testTopLevelOrderWithStatusAndTemplate(): void {
    $form = $this->buildForm(TRUE, TRUE);

    $this->assertSame([
      'label',
      'model_id',
      'executable',
      'template',
      'documentation',
      'tags',
      'recipe_export',
      'advanced',
    ], Element::children($form));
  }

  /**
   * Tests that an owner without status and template support omits both fields.
   */
  public function testTopLevelOrderWithoutStatusAndTemplate(): void {
    $form = $this->buildForm(FALSE, FALSE);

    $this->assertSame([
      'label',
      'model_id',
      'documentation',
      'tags',
      'recipe_export',
      'advanced',
    ], Element::children($form));
  }

  /**
   * Tests that the storage field explains what it does.
   */
  public function testStorageFieldIsDescribed(): void {
    $form = $this->buildForm();

    $this->assertArrayHasKey('#description', $form['advanced']['storage']);
    $this->assertNotSame('', (string) $form['advanced']['storage']['#description']);
  }

  /**
   * Tests that the owner alters the grouped form.
   */
  public function testOwnerAltersTheGroupedForm(): void {
    $owner = $this->owner(TRUE, TRUE);
    $owner->expects($this->once())
      ->method('modelConfigFormAlter')
      ->with($this->callback(static function (array $form): bool {
        return isset($form['recipe_export']['summary'], $form['advanced']['version']);
      }));

    $this->modeler()->callDefaultModelConfigForm($owner, self::MODEL_CONFIG, FALSE);
  }

  /**
   * Builds the default model configuration form.
   *
   * @param bool $supportsStatus
   *   Whether the owner supports the status field.
   * @param bool $supportsTemplate
   *   Whether the owner supports the template field.
   *
   * @return array
   *   The form.
   */
  private function buildForm(bool $supportsStatus = TRUE, bool $supportsTemplate = TRUE): array {
    return $this->modeler()->callDefaultModelConfigForm(
      $this->owner($supportsStatus, $supportsTemplate),
      self::MODEL_CONFIG,
      FALSE,
    );
  }

  /**
   * Builds a model owner mock.
   *
   * @param bool $supportsStatus
   *   Whether the owner supports the status field.
   * @param bool $supportsTemplate
   *   Whether the owner supports the template field.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The model owner mock.
   */
  private function owner(bool $supportsStatus, bool $supportsTemplate) {
    $owner = $this->createMock(ModelOwnerInterface::class);
    $owner->method('supportsStatus')->willReturn($supportsStatus);
    $owner->method('supportsTemplate')->willReturn($supportsTemplate);
    $owner->method('modelIdExistsCallback')->willReturn(['\\Drupal\\modeler_api\\Api', 'modelIdExists']);
    $owner->method('enforceDefaultStorageMethod')->willReturn(FALSE);
    return $owner;
  }

  /**
   * Builds a concrete modeler plugin exposing the shared form.
   *
   * @return object
   *   The modeler plugin.
   */
  private function modeler(): object {
    $modeler = new class(
      [],
      'test_modeler',
      ['label' => 'Test modeler', 'description' => 'Test modeler.'],
      new Request(),
      $this->createStub(UuidInterface::class),
      $this->createStub(ExtensionPathResolver::class),
      $this->createStub(FormBuilderInterface::class),
      $this->createStub(LoggerChannelInterface::class),
    ) extends ModelerBase {

      /**
       * {@inheritdoc}
       */
      public function parseData(ModelOwnerInterface $owner, string $data): void {}

      /**
       * {@inheritdoc}
       */
      public function getRawData(): string {
        return '';
      }

      /**
       * Exposes the shared model configuration form.
       *
       * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
       *   The model owner.
       * @param array $config
       *   The config for the form fields.
       * @param bool $isNew
       *   TRUE, if the model is new, FALSE otherwise.
       *
       * @return array
       *   The form.
       */
      public function callDefaultModelConfigForm(ModelOwnerInterface $owner, array $config, bool $isNew): array {
        return $this->defaultModelConfigForm($owner, $config, $isNew);
      }

    };
    $modeler->setStringTranslation($this->getStringTranslationStub());
    return $modeler;
  }

}
