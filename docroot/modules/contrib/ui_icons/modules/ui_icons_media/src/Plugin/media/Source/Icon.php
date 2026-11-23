<?php

declare(strict_types=1);

namespace Drupal\ui_icons_media\Plugin\media\Source;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinitionInterface;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\media\Attribute\MediaSource;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\ui_icons_media\Form\IconMediaAddForm;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Icon source plugin.
 */
#[MediaSource(
  id: 'ui_icon',
  label: new TranslatableMarkup('Icon'),
  description: new TranslatableMarkup('Use an icon in a reusable media entity.'),
  allowed_field_types: [
    'ui_icon',
  ],
  forms: [
    'media_library_add' => IconMediaAddForm::class,
  ],
  default_thumbnail_filename: 'no-thumbnail.png',
)]
class Icon extends MediaSourceBase {

  /**
   * The copied thumbnail base directory.
   */
  public const THUMBNAIL_DIRECTORY = 'public://ui_icons_thumbnails';

  /**
   * Key for "Pack ID" metadata attribute.
   *
   * @var string
   */
  public const METADATA_ATTRIBUTE_PACK_ID = 'pack_id';

  /**
   * Key for "Pack label" metadata attribute.
   *
   * @var string
   */
  public const METADATA_ATTRIBUTE_PACK_LABEL = 'pack_label';

  /**
   * Key for "Pack license" metadata attribute.
   *
   * @var string
   */
  public const METADATA_ATTRIBUTE_PACK_LICENSE = 'pack_license';

  /**
   * Key for "Icon ID" metadata attribute.
   *
   * @var string
   */
  public const METADATA_ATTRIBUTE_ICON_ID = 'icon_id';

  /**
   * Key for "Icon full ID" metadata attribute.
   *
   * @var string
   */
  public const METADATA_ATTRIBUTE_ICON_FULL_ID = 'icon_full_id';

  /**
   * Key for "Icon group" metadata attribute.
   *
   * @var string
   */
  public const METADATA_ATTRIBUTE_ICON_GROUP = 'icon_group';

  /**
   * Key for "Icon source" metadata attribute.
   *
   * @var string
   */
  public const METADATA_ATTRIBUTE_ICON_SOURCE = 'icon_source';

  /**
   * The icon pack manager.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  protected IconPackManagerInterface $iconPackManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->iconPackManager = $container->get('plugin.manager.icon_pack');
    $instance->fileSystem = $container->get('file_system');
    $instance->logger = $container->get('logger.channel.ui_icons_media');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes(): array {
    return [
      static::METADATA_ATTRIBUTE_PACK_ID => $this->t('Pack ID'),
      static::METADATA_ATTRIBUTE_PACK_LABEL => $this->t('Pack label'),
      static::METADATA_ATTRIBUTE_PACK_LICENSE => $this->t('Pack license'),
      static::METADATA_ATTRIBUTE_ICON_ID => $this->t('Icon ID'),
      static::METADATA_ATTRIBUTE_ICON_FULL_ID => $this->t('Icon full ID'),
      static::METADATA_ATTRIBUTE_ICON_GROUP => $this->t('Icon group'),
      static::METADATA_ATTRIBUTE_ICON_SOURCE => $this->t('Icon source'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    $iconFieldValue = $media->get($this->configuration['source_field'])->getValue();
    $icon_full_id = $iconFieldValue[0]['target_id'] ?? NULL;

    if ($icon_full_id == NULL) {
      return parent::getMetadata($media, $attribute_name);
    }

    $icon = $this->iconPackManager->getIcon($icon_full_id);
    if ($icon == NULL) {
      return parent::getMetadata($media, $attribute_name);
    }

    switch ($attribute_name) {
      case 'thumbnail_uri':
        return $this->getThumbnail($icon) ?: parent::getMetadata($media, $attribute_name);

      case static::METADATA_ATTRIBUTE_PACK_ID:
        return $icon->getPackId();

      case static::METADATA_ATTRIBUTE_PACK_LABEL:
        return $icon->getPackLabel();

      case static::METADATA_ATTRIBUTE_PACK_LICENSE:
        $licenseInfos = $icon->getData('license');
        return $licenseInfos['name'] ?? NULL;

      case static::METADATA_ATTRIBUTE_ICON_ID:
        return $icon->getIconId();

      case static::METADATA_ATTRIBUTE_ICON_FULL_ID:
        return $icon->getId();

      case static::METADATA_ATTRIBUTE_ICON_GROUP:
        return $icon->getGroup();

      case static::METADATA_ATTRIBUTE_ICON_SOURCE:
        return $icon->getSource();

      default:
        return parent::getMetadata($media, $attribute_name);
    }
  }

  /**
   * Gets the thumbnail image URI based on an icon.
   *
   * Do the same logic as in the icon--preview template.
   *
   * @param \Drupal\Core\Theme\Icon\IconDefinitionInterface $icon
   *   The icon definition.
   *
   * @return string|null
   *   File URI of the thumbnail image or NULL if there is no specific icon.
   */
  protected function getThumbnail(IconDefinitionInterface $icon): ?string {
    $extractor = $icon->getData('extractor');
    if (!\in_array($extractor, ['path', 'svg'])) {
      return NULL;
    }

    $source = $icon->getSource();
    if (!$source) {
      return NULL;
    }

    $source = ltrim($source, '/');

    // Detect if source is path or remote, parse_url will have no scheme for
    // a path.
    $url = parse_url($source);
    if (isset($url['scheme']) && isset($url['path'])) {
      // Remote icons are not handled currently.
      return NULL;
    }

    $filename = pathinfo($source, PATHINFO_BASENAME);
    $directory = $this::THUMBNAIL_DIRECTORY . DIRECTORY_SEPARATOR . $icon->getPackId();
    $destinationPath = $directory . DIRECTORY_SEPARATOR . $filename;
    try {
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $newPath = $this->fileSystem->copy($source, $destinationPath, FileExists::Replace);
      return $newPath;
    }
    catch (\Exception $exception) {
      $this->logger->error('Cannot copy icon to thumbnail: @message', ['@message' => $exception->getMessage()]);
      return NULL;
    }
  }

}
