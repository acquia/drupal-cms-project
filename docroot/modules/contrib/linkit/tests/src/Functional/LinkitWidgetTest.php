<?php

declare(strict_types=1);

namespace Drupal\Tests\linkit\Functional;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\link\LinkItemInterface;
use Drupal\linkit\Tests\ProfileCreationTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the linkit widget form submission and default values.
 *
 * @group linkit
 */
#[RunTestsInSeparateProcesses]
class LinkitWidgetTest extends BrowserTestBase {

  use ProfileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'file',
    'link',
    'linkit',
    'linkit_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createProfile(['id' => 'test_linkit_profile']);

    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'type' => 'link',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
      'bundle' => 'entity_test',
      'settings' => ['link_type' => LinkItemInterface::LINK_GENERIC],
    ])->save();

    $this->container->get('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test')
      ->setComponent('field_test', [
        'type' => 'linkit',
        'settings' => ['linkit_profile' => 'test_linkit_profile'],
      ])
      ->save();

    $account = $this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
      'link to any page',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Tests that percent-encoded URLs are not double-encoded on widget save.
   *
   * Regression test for https://www.drupal.org/project/linkit/issues/3436733
   *
   * Submits a form value with an already-encoded URL through the linkit widget
   * and asserts the stored URI is decoded exactly once (no double encoding),
   * and that the stored URI resolves back to the original submitted string.
   *
   * @dataProvider uriProvider
   */
  #[DataProvider('uriProvider')]
  public function testNoDoubleEncoding(string $input, string $expected_uri): void {
    $this->drupalGet('entity_test/add');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm(['field_test[0][uri]' => $input], 'Save');

    preg_match('|entity_test/manage/(\d+)|', $this->getUrl(), $matches);
    $id = $matches[1];

    $entity = EntityTest::load($id);
    $this->assertSame($expected_uri, $entity->field_test->uri);
  }

  /**
   * Provides percent-encoded user inputs and their expected stored URIs.
   *
   * @return array<string, array{string, string}>
   *   The submitted URLs and their expected stored URIs.
   */
  public static function uriProvider(): array {
    return [
      'public file path' => [
        '/sites/default/files/file%20name.pdf',
        'internal:/sites/default/files/file name.pdf',
      ],
      'internal path' => [
        '/internal%20path',
        'internal:/internal path',
      ],
    ];
  }

  /**
   * Tests file links keep query and fragment when the form is reopened.
   *
   * Regression test for https://www.drupal.org/project/linkit/issues/3632299
   */
  public function testFileLinkQueryAndFragmentPersist(): void {
    file_put_contents('public://document.pdf', '%PDF-1.4');
    $file = File::create([
      'uri' => 'public://document.pdf',
      'status' => 1,
    ]);
    $file->save();
    $file_url = $this->container->get('file_url_generator')->generateString($file->getFileUri());
    $full_url = $file_url . '?download=1#page=2';

    // Simulate selecting the file from the autocomplete suggestions, which
    // fills the hidden entity attributes, then appending a query and fragment.
    $this->drupalGet('entity_test/add');
    $hidden_values = [
      'data-entity-type' => 'file',
      'data-entity-uuid' => $file->uuid(),
      'data-entity-substitution' => 'file',
    ];
    foreach ($hidden_values as $name => $value) {
      $this->assertSession()
        ->hiddenFieldExists("field_test[0][attributes][$name]")
        ->setValue($value);
    }
    $this->submitForm(['field_test[0][uri]' => $full_url], 'Save');
    preg_match('|entity_test/manage/(\d+)|', $this->getUrl(), $matches);
    $id = $matches[1];
    $this->assertFileLinkOptions((int) $id);

    // Reopen the edit form: the URL field must show the complete file URL.
    $this->drupalGet("entity_test/manage/$id/edit");
    $this->assertSession()->fieldValueEquals('field_test[0][uri]', $full_url);

    // Save without changing the link: query and fragment must survive.
    $this->submitForm([], 'Save');
    $this->assertFileLinkOptions((int) $id);
    $this->drupalGet("entity_test/manage/$id/edit");
    $this->assertSession()->fieldValueEquals('field_test[0][uri]', $full_url);
  }

  /**
   * Asserts the stored link options contain the expected query and fragment.
   *
   * @param int $id
   *   The test entity ID.
   */
  protected function assertFileLinkOptions(int $id): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_test');
    $storage->resetCache([$id]);
    $options = $storage->load($id)->field_test->options;
    $this->assertSame(['download' => '1'], $options['query'] ?? NULL);
    $this->assertSame('page=2', $options['fragment'] ?? NULL);
  }

}
