<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_image_bulk_alt_text\Kernel;

use Drupal\ai_image_bulk_alt_text\Form\AltTextFixer;
use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests that the bulk alt text form only writes to entities it listed.
 *
 * @group ai_image_alt_text
 */
class AltTextFixerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'entity_test',
    'key',
    'ai',
    'ai_image_alt_text',
    'ai_image_bulk_alt_text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('entity_test');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'image']);

    FieldStorageConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'entity_test',
      'type' => 'image',
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => ['alt_field' => TRUE],
    ])->save();

    // Make sure uid 1 is taken, so test users are not super users.
    $this->createUser();
  }

  /**
   * Only images without alt text on updatable entities are listed.
   */
  public function testListing(): void {
    $missing = $this->createEntity(['', 'has alt', '']);
    $this->createEntity(['complete']);

    $this->setUpCurrentUser([], ['batch fix ai images alt text', 'administer entity_test content', 'access content']);
    $rows = $this->buildForm()['#ai_image_rows'];
    $this->assertCount(2, $rows);
    foreach ($rows as $row) {
      $this->assertEquals($missing->id(), $row['entity_id']);
    }
    // Deltas 0 and 2 are missing alt text, delta 1 is not.
    $this->assertEqualsCanonicalizing([0, 2], array_column($rows, 'delta'));

    // Without update access nothing is listed.
    $this->setUpCurrentUser([], ['batch fix ai images alt text', 'view test entity', 'access content']);
    $form = $this->buildForm();
    $this->assertArrayNotHasKey('table', $form);
  }

  /**
   * A listed row is saved on the delta it belongs to.
   */
  public function testSubmitWritesCorrectDelta(): void {
    $entity = $this->createEntity(['first', '']);
    $this->setUpCurrentUser([], ['batch fix ai images alt text', 'administer entity_test content', 'access content']);

    $rows = $this->buildForm()['#ai_image_rows'];
    $this->assertCount(1, $rows);
    $unique_id = array_key_first($rows);

    $this->submit([
      $unique_id => ['suggested_alt_text' => 'A generated alt text'],
    ]);

    $entity = EntityTest::load($entity->id());
    $this->assertSame('first', $entity->field_image[0]->alt);
    $this->assertSame('A generated alt text', $entity->field_image[1]->alt);
  }

  /**
   * Rows added by the client cannot target other entities or fields.
   */
  public function testInjectedRowsAreIgnored(): void {
    $this->createEntity(['']);
    $victim = $this->createEntity(['keep me']);
    $this->setUpCurrentUser([], ['batch fix ai images alt text', 'administer entity_test content', 'access content']);

    $this->submit([
      'injected' => [
        'base_entity_type' => 'entity_test',
        'base_entity_id' => $victim->id(),
        'field' => 'field_image',
        'suggested_alt_text' => 'overwritten',
      ],
      999 => [
        'base_entity_type' => 'entity_test',
        'base_entity_id' => $victim->id(),
        'field' => 'field_image',
        'suggested_alt_text' => 'overwritten',
      ],
    ]);

    $victim = EntityTest::load($victim->id());
    $this->assertSame('keep me', $victim->field_image[0]->alt);
  }

  /**
   * Entities the user may not update are not saved.
   */
  public function testSubmitChecksUpdateAccess(): void {
    $entity = $this->createEntity(['']);
    $this->setUpCurrentUser([], ['batch fix ai images alt text', 'administer entity_test content', 'access content']);
    $form = $this->buildForm();
    $unique_id = array_key_first($form['#ai_image_rows']);

    // Simulate the form being submitted by a user who lost update access, by
    // running the submit handler for a different user on the listed rows.
    $this->setUpCurrentUser([], ['batch fix ai images alt text', 'view test entity', 'access content']);
    $form_state = new FormState();
    $form_state->setValue(['table', $unique_id, 'suggested_alt_text'], 'not allowed');
    $form_object = AltTextFixer::create($this->container);
    $form_object->submitForm($form, $form_state);

    $entity = EntityTest::load($entity->id());
    $this->assertSame('', (string) $entity->field_image[0]->alt);
  }

  /**
   * Entity labels are output as plain text.
   */
  public function testLabelIsPlainText(): void {
    $this->createEntity([''], '<script>alert(1)</script>');
    $this->setUpCurrentUser([], ['batch fix ai images alt text', 'administer entity_test content', 'access content']);
    $form = $this->buildForm();
    $unique_id = array_key_first($form['#ai_image_rows']);
    $cell = $form['table'][$unique_id]['entity_type'];
    $this->assertArrayHasKey('#plain_text', $cell);
    $this->assertArrayNotHasKey('#markup', $cell);
    $html = (string) $this->container->get('renderer')->renderInIsolation($cell);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * Builds the bulk form.
   */
  protected function buildForm(): array {
    return $this->container->get('form_builder')->getForm(AltTextFixer::class);
  }

  /**
   * Submits the bulk form programmatically.
   */
  protected function submit(array $table): void {
    $form_state = (new FormState())->setValues([
      'table' => $table,
      'op' => 'Save changes',
    ]);
    $this->container->get('form_builder')->submitForm(AltTextFixer::class, $form_state);
    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Creates a test entity with one image per given alt text.
   */
  protected function createEntity(array $alts, ?string $label = NULL): EntityTest {
    $items = [];
    foreach ($alts as $alt) {
      $items[] = ['target_id' => $this->createImage()->id(), 'alt' => $alt];
    }
    $entity = EntityTest::create([
      'name' => $label ?? $this->randomMachineName(),
      'field_image' => $items,
    ]);
    $entity->save();
    return $entity;
  }

  /**
   * Creates a public image file.
   */
  protected function createImage(): FileInterface {
    $uri = 'public://' . $this->randomMachineName() . '.png';
    \Drupal::service('file_system')->copy($this->root . '/core/misc/druplicon.png', $uri);
    $file = File::create(['uri' => $uri, 'filename' => basename($uri)]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

}
