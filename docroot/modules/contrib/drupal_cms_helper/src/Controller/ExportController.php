<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\drupal_cms_helper\SiteExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class ExportController extends ControllerBase {

  public function __construct(
    private readonly SiteExporter $exporter,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  public function exportArchive(): BinaryFileResponse {
    $destination = uniqid('temporary://site-export-');

    $base = $this->exporter->getRecipePath('drupal/drupal_cms_site_template_base');
    $this->exporter->export($destination, is_dir($base) ? $base : NULL);

    $destination = $this->fileSystem->realpath($destination);
    assert($destination && is_dir($destination));

    $archive = $destination . '.zip';
    (new \PharData($archive))->buildFromDirectory($destination);
    $this->fileSystem->deleteRecursive($destination);

    $response = new BinaryFileResponse($archive, headers: [
      'Content-Type' => 'application/zip',
      'Content-Disposition' => 'attachment',
    ]);
    $size = filesize($archive);
    if (is_int($size)) {
      $response->headers->set('Content-Length', (string) $size);
    }
    return $response;
  }

}
