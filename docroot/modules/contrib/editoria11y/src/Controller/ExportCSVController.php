<?php

namespace Drupal\editoria11y\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ExportCSVController.
 *
 * Tip to https://www.mediacurrent.com/blog/custom-data-file-responses-drupal.
 *
 * @package Drupal\editoria11y\Controller
 */
final class ExportCSVController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Dashboard property.
   *
   * @var \Drupal\editoria11y\Dashboard
   */
  protected $dashboard;

  /**
   * Get date formatter copy.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  private DateFormatterInterface $dateFormatter;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the ExportCSVController object.
   *
   * @param mixed $dashboard
   *   Dashboard property.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct($dashboard, DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager) {
    $this->dashboard = $dashboard;
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($container) {
    return new self(
          $container->get('editoria11y.dashboard'),
          $container->get('date.formatter'),
          $container->get('entity_type.manager')
      );
  }

  /**
   * Exports a summary CSV of issue count per page.
   */
  public function pages(): Response {
    // Start using PHP's built in file handler functions to create a temporary
    // file.
    $handle = fopen('php://temp', 'w+');

    // Set up the header that will be displayed as the first line of the CSV
    // file.
    // Blank strings are used for multi-cell values where there is a count of
    // the "keys" and a list of the keys with the count of their usage.
    $header = [
      'Issues Found' => $this->t('Issues Found'),
      'Page' => $this->t('Page'),
      'Path' => $this->t('Path'),
      'Type' => $this->t('Type'),
      'Language' => $this->t('Language'),
      'Page report' => $this->t('Page report'),
    ];

    // Allow other modules to alter the header (e.g., add columns).
    $context_key = 'pages';
    $this->moduleHandler()->alter('editoria11y_export_header', $header, $context_key);

    // Add the header as the first line of the CSV.
    fputcsv($handle, $header);
    // Find and load all of the Article nodes we are going to include.
    $results = $this->dashboard->ExportPages();

    // Iterate through the nodes.  We want one row in the CSV per Article.
    foreach ($results as $result) {
      $url = UrlHelper::filterBadProtocol($result->page_path);
      $issuesByPage = Url::fromUserInput("/admin/reports/editoria11y/page?q=" . $result->page_path, ['absolute' => TRUE]);
      $data = [
        'Issues found' => $result->page_result_count,
        'Page' => $this->escapeStringForCsv($result->page_title),
        'Path' => $url,
        'Type' => $this->escapeStringForCsv($result->entity_type),
        'Language' => $this->escapeStringForCsv($result->page_language),
        'Page report' => $issuesByPage->toString(),
      ];

      // Allow other modules to alter the row data.
      // Please remember to escapeStringForCsv.
      $this->moduleHandler()->alter('editoria11y_export_row', $data, $result, $context_key);

      // Add the data we ExportCSVControllered to the next line of the CSV>.
      fputcsv($handle, array_values($data));
    }
    // Reset where we are in the CSV.
    rewind($handle);

    // Retrieve the data from the file handler.
    $csv_data = stream_get_contents($handle);

    // Close the file handler since we don't need it anymore. We are not storing
    // this file anywhere in the filesystem.
    fclose($handle);

    // This is the "magic" part of the code.  Once the data is built, we can
    // return it as a response.
    $response = new Response();

    // By setting these 2 header options, the browser will see the URL
    // used by this Controller to return a CSV file called "article-report.csv".
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="editoria11y-summary.csv"');
    $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');

    // This line physically adds the CSV data we created.
    $response->setContent($csv_data);

    return $response;
  }

  /**
   * Exports a CSV of all issues.
   */
  public function issues(): Response {
    // Start using PHP's built in file handler functions to create a temporary
    // file.
    $handle = fopen('php://temp', 'w+');

    // Set up the header that will be displayed as the first line of the CSV
    // file.
    // Blank strings are used for multi-cell values where there is a count of
    // the "keys" and a list of the keys with the count of their usage.
    $header = [
      'Issue' => $this->t('Issue'),
      'Page' => $this->t('Page'),
      'Path' => $this->t('Path'),
      'Type' => $this->t('Page Type'),
      'Language' => $this->t('Language'),
      'Page report' => $this->t('Page report'),
    ];

    // Allow other modules to alter the header (e.g., add columns).
    $context_key = 'issues';
    $this->moduleHandler()->alter('editoria11y_export_header', $header, $context_key);

    // Add the header as the first line of the CSV.
    fputcsv($handle, $header);
    // Find and load all of the Article nodes we are going to include.
    $results = $this->dashboard->ExportIssues();

    // Iterate through the nodes.  We want one row in the CSV per Article.
    foreach ($results as $result) {
      $url = UrlHelper::filterBadProtocol($result->page_path);
      $issuesByPage = Url::fromUserInput("/admin/reports/editoria11y/page?q=" . $result->page_path, ['absolute' => TRUE]);
      $data = [
        'Issue' => $this->escapeStringForCsv($result->result_name),
        'Page' => $this->escapeStringForCsv($result->page_title),
        'Path' => $url,
        'Type' => $this->escapeStringForCsv($result->entity_type),
        'Language' => $this->escapeStringForCsv($result->page_language),
        'Page report' => $issuesByPage->toString(),
      ];

      // Allow other modules to alter the row data.
      // Please remember to escapeStringForCsv.
      $this->moduleHandler()->alter('editoria11y_export_row', $data, $result, $context_key);

      // Add the data we ExportCSVControllered to the next line of the CSV>.
      fputcsv($handle, array_values($data));
    }
    // Reset where we are in the CSV.
    rewind($handle);

    // Retrieve the data from the file handler.
    $csv_data = stream_get_contents($handle);

    // Close the file handler since we don't need it anymore. We are not storing
    // this file anywhere in the filesystem.
    fclose($handle);

    // This is the "magic" part of the code.  Once the data is built, we can
    // return it as a response.
    $response = new Response();

    // By setting these 2 header options, the browser will see the URL
    // used by this Controller to return a CSV file called "article-report.csv".
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="editoria11y-issues.csv"');
    $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');

    // This line physically adds the CSV data we created.
    $response->setContent($csv_data);

    return $response;
  }

  /**
   * Exports a CSV of all issues.
   */
  public function dismissals(): Response {
    // Start using PHP's built in file handler functions to create a temporary
    // file.
    $handle = fopen('php://temp', 'w+');

    // Set up the header that will be displayed as the first line of the CSV
    // file.
    // Blank strings are used for multi-cell values where there is a count of
    // the "keys" and a list of the keys with the count of their usage.
    $header = [
      'Page' => $this->t('Page'),
      'Path' => $this->t('Path'),
      'Issue' => $this->t('Issue'),
      'Dismissal type' => $this->t('Dismissal type'),
      'By' => $this->t('By'),
      'On' => $this->t('On'),
      'Still on page' => $this->t('Still on page'),
    ];

    // Allow other modules to alter the header (e.g., add columns).
    $context_key = 'dismissals';
    $this->moduleHandler()->alter('editoria11y_export_header', $header, $context_key);

    // Add the header as the first line of the CSV.
    fputcsv($handle, $header);
    // Find and load all of the Article nodes we are going to include.
    $results = $this->dashboard->exportDismissals();

    // Iterate through the nodes. We want one row in the CSV per Article.
    foreach ($results as $result) {
      $user = $this->entityTypeManager->getStorage('user')->load($result->uid);
      $name = $user ? $user->getDisplayName() : $this->t('Anonymous');
      $stale = $result->stale ? "No" : "Yes";
      $date = $this->dateFormatter->format($result->created);
      $url = UrlHelper::filterBadProtocol($result->page_path);
      $data = [
        'Page' => $this->escapeStringForCsv($result->page_title),
        'Path' => $url,
        'Issue' => $this->escapeStringForCsv($result->result_name),
        'Dismissal type' => $result->dismissal_status,
        'By' => $this->escapeStringForCsv($name),
        'On' => $date,
        'Still on page' => $stale,
      ];

      // Allow other modules to alter the row data.
      // Please remember to escapeStringForCsv.
      $this->moduleHandler()->alter('editoria11y_export_row', $data, $result, $context_key);

      // Add the data we ExportCSVControllered to the next line of the CSV>.
      fputcsv($handle, array_values($data));
    }
    // Reset where we are in the CSV.
    rewind($handle);

    // Retrieve the data from the file handler.
    $csv_data = stream_get_contents($handle);

    // Close the file handler since we don't need it anymore. We are not storing
    // this file anywhere in the filesystem.
    fclose($handle);

    // This is the "magic" part of the code.  Once the data is built, we can
    // return it as a response.
    $response = new Response();

    // By setting these 2 header options, the browser will see the URL
    // used by this Controller to return a CSV file called "article-report.csv".
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="editoria11y-dismissals.csv"');
    $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');

    // This line physically adds the CSV data we created.
    $response->setContent($csv_data);

    return $response;
  }

  /**
   * Escape strings for CSV that may contain malicious CSV injections.
   *
   * @param string $string
   *   The input string.
   *
   * @return string
   *   The escaped string
   */
  protected function escapeStringForCsv(string $string): string {
    // @see https://owasp.org/www-community/attacks/CSV_Injection.
    // Replace quotes and breaks to prevent splitting cells.
    $escaped = preg_replace(['/"/', '/[\t\n\r]/'], ['""', ' '], $string);
    if (preg_match('/^[=\-@+"]/', $escaped)) {
      return "'" . $escaped;
    }
    return $string;
  }

}
