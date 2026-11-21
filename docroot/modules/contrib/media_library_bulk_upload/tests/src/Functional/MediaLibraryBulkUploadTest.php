<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_bulk_upload\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\media_library\MediaLibraryState;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests the media library bulk upload pages and their access.
 *
 * @group media_library_bulk_upload
 */
class MediaLibraryBulkUploadTest extends BrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_library_bulk_upload',
    'block',
    'help',
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
    $this->createMediaType('file', ['id' => 'document', 'label' => 'Document']);
    $this->createMediaType('file', ['id' => 'archive', 'label' => 'Archive']);
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * Tests access to the list of bulk upload forms.
   */
  public function testListPage(): void {
    $this->drupalGet('/admin/content/media/bulk-upload');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['view media']));
    $this->drupalGet('/admin/content/media/bulk-upload');
    $this->assertSession()->statusCodeEquals(403);

    // Only the media types the user is allowed to bulk upload are listed.
    $this->drupalLogin($this->drupalCreateUser([
      'use media document bulk upload form',
    ]));
    $this->drupalGet('/admin/content/media/bulk-upload');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkByHrefExists('/admin/content/media/bulk-upload/document');
    $this->assertSession()->linkByHrefNotExists('/admin/content/media/bulk-upload/archive');

    $this->drupalLogin($this->drupalCreateUser([
      'use media document bulk upload form',
      'use media archive bulk upload form',
    ]));
    $this->drupalGet('/admin/content/media/bulk-upload');
    $this->assertSession()->linkByHrefExists('/admin/content/media/bulk-upload/document');
    $this->assertSession()->linkByHrefExists('/admin/content/media/bulk-upload/archive');

    // Media types not enabled in the settings are not listed.
    $this->config('media_library_bulk_upload.settings')
      ->set('media_types', ['archive'])
      ->save();
    $this->drupalGet('/admin/content/media/bulk-upload');
    $this->assertSession()->linkByHrefNotExists('/admin/content/media/bulk-upload/document');
    $this->assertSession()->linkByHrefExists('/admin/content/media/bulk-upload/archive');

    // Media administrators can always access the list.
    $this->drupalLogin($this->drupalCreateUser(['administer media']));
    $this->drupalGet('/admin/content/media/bulk-upload');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests access to and rendering of a bulk upload form.
   */
  public function testUploadForm(): void {
    $this->drupalGet('/admin/content/media/bulk-upload/document');
    $this->assertSession()->statusCodeEquals(403);

    // Being able to create media is not enough to use the bulk upload.
    $this->drupalLogin($this->drupalCreateUser([
      'view media',
      'create document media',
    ]));
    $this->drupalGet('/admin/content/media/bulk-upload/document');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser([
      'view media',
      'create document media',
      'use media document bulk upload form',
    ]));
    $this->drupalGet('/admin/content/media/bulk-upload/document');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('files[upload][]');
    // The selection functionality of the media library is disabled.
    $this->assertSession()->elementNotExists('css', '.js-media-library-item input[type="checkbox"]');

    // The permission is per media type.
    $this->drupalGet('/admin/content/media/bulk-upload/archive');
    $this->assertSession()->statusCodeEquals(403);

    // The form is denied for media types not enabled in the settings.
    $this->config('media_library_bulk_upload.settings')
      ->set('media_types', ['archive'])
      ->save();
    $this->drupalGet('/admin/content/media/bulk-upload/document');
    $this->assertSession()->statusCodeEquals(403);

    // Unknown media types result in a 404.
    $this->drupalGet('/admin/content/media/bulk-upload/does_not_exist');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests access to the media library UI opened by the bulk upload opener.
   *
   * The media library forms (e.g. the upload form) are submitted to the
   * generic media library route, so the opener must apply the same access
   * rules as the bulk upload form route.
   */
  public function testMediaLibraryUiAccess(): void {
    $document_url = $this->getMediaLibraryUrl('document');
    $archive_url = $this->getMediaLibraryUrl('archive');

    $this->drupalGet($document_url);
    $this->assertSession()->statusCodeEquals(403);

    // Being able to view and create media is not enough.
    $this->drupalLogin($this->drupalCreateUser([
      'view media',
      'create document media',
    ]));
    $this->drupalGet($document_url);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser([
      'view media',
      'create document media',
      'create archive media',
      'use media document bulk upload form',
    ]));
    $this->drupalGet($document_url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('files[upload][]');

    // The permission is per media type.
    $this->drupalGet($archive_url);
    $this->assertSession()->statusCodeEquals(403);

    // Access is denied for media types not enabled in the settings.
    $this->config('media_library_bulk_upload.settings')
      ->set('media_types', ['archive'])
      ->save();
    $this->drupalGet($document_url);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Returns the media library URL as opened by a bulk upload form.
   *
   * @param string $media_type_id
   *   The media type ID.
   *
   * @return \Drupal\Core\Url
   *   The media library URL.
   */
  protected function getMediaLibraryUrl(string $media_type_id): Url {
    $state = MediaLibraryState::create('media_library.opener.bulk_upload', [$media_type_id], $media_type_id, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    return Url::fromRoute('media_library.ui', [], ['query' => $state->all()]);
  }

  /**
   * Tests the settings form.
   */
  public function testSettingsForm(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'use media document bulk upload form',
      'use media archive bulk upload form',
    ]));
    $this->drupalGet('/admin/config/media/media-library-bulk-upload-config');
    $this->assertSession()->statusCodeEquals(403);

    $admin = $this->drupalCreateUser([
      'administer media',
      'use media document bulk upload form',
      'use media archive bulk upload form',
    ]);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/config/media/media-library-bulk-upload-config');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm(['media_types[archive]' => TRUE], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->checkboxChecked('media_types[archive]');
    $this->assertSession()->checkboxNotChecked('media_types[document]');
    // Unselected media types are not stored.
    $this->assertSame(['archive'], $this->config('media_library_bulk_upload.settings')->get('media_types'));

    // Only the enabled media type can be used now.
    $this->drupalGet('/admin/content/media/bulk-upload');
    $this->assertSession()->linkByHrefExists('/admin/content/media/bulk-upload/archive');
    $this->assertSession()->linkByHrefNotExists('/admin/content/media/bulk-upload/document');

    // Unchecking all media types removes the limitation.
    $this->drupalGet('/admin/config/media/media-library-bulk-upload-config');
    $this->submitForm(['media_types[archive]' => FALSE], 'Save configuration');
    $this->assertSame([], $this->config('media_library_bulk_upload.settings')->get('media_types'));
    $this->drupalGet('/admin/content/media/bulk-upload');
    $this->assertSession()->linkByHrefExists('/admin/content/media/bulk-upload/archive');
    $this->assertSession()->linkByHrefExists('/admin/content/media/bulk-upload/document');
  }

  /**
   * Tests the local action and the help page.
   */
  public function testActionAndHelp(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access media overview',
      'access help pages',
      'use media document bulk upload form',
    ]));
    $this->drupalGet('/admin/content/media');
    $this->assertSession()->linkExists('Upload media in bulk');

    $this->drupalGet('/admin/help/media_library_bulk_upload');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('This module basically has the same idea as media_bulk_upload');
  }

}
