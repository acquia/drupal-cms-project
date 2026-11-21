<?php

declare(strict_types=1);

namespace Drupal\google_tag\Hook;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ProceduralCall;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\Core\Hook\Attribute\ReorderHook;
use Drupal\Core\Hook\Order\Order;
use Drupal\google_tag\DimensionsMetricsProcessor;
use Drupal\google_tag\EventCollectorInterface;
use Drupal\google_tag\TagContainerResolver;

/**
 * Page attachments hook implementation for the Google Tag module.
 */
final class GoogleTagPageAttachments {

  /**
   * Constructs a GoogleTagPageAttachments object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TagContainerResolver $tagContainerResolver,
    private readonly DimensionsMetricsProcessor $dimensionsMetricsProcessor,
    private readonly EventCollectorInterface $eventCollector,
  ) {
  }

  /**
   * Implements hook_page_attachments().
   *
   * Runs last to capture events added by other page_attachments
   * implementations, and replaces the google_analytics implementation.
   *
   * The ordering and removal use separate attributes rather than the Hook
   * "order" parameter: Drupal 11.1.0 to 11.1.6 cannot instantiate a Hook
   * attribute with that parameter, but skip attributes whose class does not
   * exist. Before Drupal 11.2, google_tag_module_implements_alter() does the
   * same job.
   */
  #[Hook('page_attachments')]
  #[ReorderHook('page_attachments', self::class, 'pageAttachments', Order::Last)]
  #[RemoveHook('page_attachments', ProceduralCall::class, 'google_analytics_page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $definition = $this->entityTypeManager->getDefinition('google_tag_container');
    $cacheable_metadata = CacheableMetadata::createFromRenderArray($attachments);
    $cacheable_metadata->addCacheTags($definition->getListCacheTags());

    $config = $this->tagContainerResolver->resolve();

    if ($config === NULL) {
      $cacheable_metadata->applyTo($attachments);
      return;
    }
    $cacheable_metadata->addCacheableDependency($config);
    $cacheable_metadata->applyTo($attachments);

    // @todo Put this data into their own respective methods?
    // GTM JS embed.
    if ($config->getGtmIds() !== []) {
      $attachments['#attached']['library'][] = 'google_tag/gtm';
      $gtm = [
        'tagIds' => $config->getGtmIds(),
      ];
      $settings = $config->getGtmSettings();
      $gtm['settings'] = $settings;
      if (isset($settings['include_classes']) && $settings['include_classes'] === TRUE) {
        // Drop empty lines: an allowlist holding an empty class allows nothing.
        foreach (['allowlist_classes', 'blocklist_classes'] as $key) {
          $classes = array_map('trim', preg_split('/\R/', $settings[$key] ?? ''));
          $gtm['settings'][$key] = array_values(array_filter($classes, static fn ($class) => $class !== ''));
        }
      }
      $attachments['#attached']['drupalSettings']['gtm'] = $gtm;
    }

    $attachments['#attached']['library'][] = 'google_tag/gtag';
    $attachments['#attached']['drupalSettings']['gtag'] = [
      'tagId' => $config->getDefaultTagId(),
      'otherIds' => $config->getAdditionalIds(),
      'consentMode' => $config->getConsentMode(),
      'events' => [],
      'additionalConfigInfo' => $this->dimensionsMetricsProcessor->getValues($config),
    ];

    foreach ($this->eventCollector->getEvents() as $event) {
      $attachments['#attached']['drupalSettings']['gtag']['events'][] = [
        'name' => $event->getName(),
        'data' => $event->getData(),
      ];
      if ($event instanceof CacheableDependencyInterface) {
        $cacheable_metadata->addCacheableDependency($event);
      }
    }

    $cacheable_metadata->applyTo($attachments);
  }

}
