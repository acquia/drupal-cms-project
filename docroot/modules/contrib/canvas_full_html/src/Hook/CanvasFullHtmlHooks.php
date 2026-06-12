<?php

declare(strict_types=1);

namespace Drupal\canvas_full_html\Hook;

use Drupal\canvas\PropShape\CandidateStorablePropShape;
use Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Psr\Log\LoggerInterface;

/**
 * Hook implementations for canvas_full_html module.
 */
class CanvasFullHtmlHooks {

  /**
   * The text format to use for Canvas HTML props.
   */
  protected const TARGET_FORMAT = 'canvas_full_html';

  /**
   * Canvas text formats to replace.
   */
  protected const CANVAS_FORMATS = [
    'canvas_html_block',
    'canvas_html_inline',
  ];

  /**
   * Constructs a CanvasFullHtmlHooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface $ckeditor5PluginManager
   *   The CKEditor5 plugin manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly CKEditor5PluginManagerInterface $ckeditor5PluginManager,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Implements hook_canvas_storable_prop_shape_alter().
   *
   * Replaces Canvas text formats with canvas_full_html when enabled.
   */
  #[Hook('canvas_storable_prop_shape_alter')]
  public function storablePropShapeAlter(
    CandidateStorablePropShape $storable_prop_shape,
  ): void {
    // Check if the module is enabled.
    $config = $this->configFactory->get('canvas_full_html.settings');
    if (!$config->get('enabled')) {
      return;
    }

    // Check if this is a text/html content type prop.
    $schema = $storable_prop_shape->shape->schema ?? [];

    $is_html = isset($schema['contentMediaType'])
      && $schema['contentMediaType'] === 'text/html';
    if (!$is_html) {
      return;
    }

    // Check if fieldInstanceSettings has allowed_formats.
    $field_settings = $storable_prop_shape->fieldInstanceSettings;
    if (!isset($field_settings['allowed_formats'])) {
      return;
    }

    $allowed_formats = $field_settings['allowed_formats'];

    // Check if any Canvas format is present.
    $has_canvas_format = FALSE;
    foreach (self::CANVAS_FORMATS as $canvas_format) {
      if (in_array($canvas_format, $allowed_formats, TRUE)) {
        $has_canvas_format = TRUE;
        break;
      }
    }

    if (!$has_canvas_format) {
      return;
    }

    // Check if canvas_full_html format exists.
    $format_storage = $this->entityTypeManager->getStorage('filter_format');
    $format = $format_storage->load(self::TARGET_FORMAT);

    if (!$format) {
      return;
    }

    // Replace with canvas_full_html only.
    $storable_prop_shape->fieldInstanceSettings['allowed_formats'] = [
      self::TARGET_FORMAT,
    ];
  }

  /**
   * Implements hook_library_info_alter().
   *
   * Adds CKEditor fixes CSS as a dependency to the Canvas UI library.
   *
   * This is necessary because Canvas bypasses the normal page rendering
   * pipeline and hook_page_attachments() is never invoked on Canvas pages.
   *
   * @param array $libraries
   *   An associative array of libraries registered by $extension.
   * @param string $extension
   *   The name of the extension that registered the libraries.
   *
   * @see \Drupal\canvas\Controller\CanvasController::__invoke()
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension === 'canvas' && isset($libraries['canvas-ui'])) {
      $libraries['canvas-ui']['dependencies'][] =
        'canvas_full_html/ckeditor-fixes';
    }

    if ($extension === 'canvas_full_html' && isset($libraries['ckeditor-fixes'])) {
      $this->attachEditorLibraries($libraries['ckeditor-fixes']);
    }
  }

  /**
   * Attaches enabled CKEditor5 plugin libraries to the ckeditor-fixes library.
   *
   * Resolves a race condition where Canvas's React component
   * DrupalFormattedTextArea called selectPlugins() before contrib DLL chunks
   * had finished loading, causing null values in extraPlugins and silently
   * crashing the CKEditor5 instance.
   *
   * Only the CKEditor5 plugin DLL chunk libraries are added. The main Drupal
   * CKEditor5 integration library (ckeditor5/internal.drupal.ckeditor5) is
   * intentionally excluded because it depends on core/drupal.ajax and the
   * editor behavior system, which can conflict with Canvas's custom AJAX
   * handling and cause JS errors on Canvas pages.
   *
   * @param array $library
   *   The ckeditor-fixes library definition, passed by reference.
   */
  private function attachEditorLibraries(array &$library): void {
    $config = $this->configFactory->get('canvas_full_html.settings');
    if (!$config->get('enabled')) {
      return;
    }

    // Libraries that must not be added as Canvas-ui dependencies because they
    // pull in core/drupal.ajax and Drupal editor behaviors that conflict with
    // Canvas's own AJAX handling.
    $excluded = [
      'ckeditor5/internal.drupal.ckeditor5',
    ];

    try {
      $editor = $this->entityTypeManager
        ->getStorage('editor')
        ->load(self::TARGET_FORMAT);

      if (!$editor) {
        return;
      }

      foreach ($this->ckeditor5PluginManager->getEnabledLibraries($editor) as $pluginLibrary) {
        if (in_array($pluginLibrary, $excluded, TRUE)) {
          continue;
        }
        $library['dependencies'][] = $pluginLibrary;
      }
    }
    catch (\Exception $e) {
      // Skip if libraries cannot be determined (e.g. during install or when
      // a contrib CKEditor5 module has been uninstalled after the editor config
      // was saved). Log at debug level for diagnostics without showing errors.
      $this->logger->debug(
        'canvas_full_html: could not attach editor libraries: @message',
        ['@message' => $e->getMessage()],
      );
    }
  }

}
