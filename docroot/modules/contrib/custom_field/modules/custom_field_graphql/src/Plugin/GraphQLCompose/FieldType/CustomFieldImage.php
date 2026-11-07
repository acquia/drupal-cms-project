<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Plugin\GraphQLCompose\FieldType;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\file\FileInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use enshrined\svgSanitize\Sanitizer;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "custom_field_image",
 *   type_sdl = "Image",
 * )
 */
class CustomFieldImage extends GraphQLComposeFieldTypeBase implements FieldProducerItemInterface, ContainerFactoryPluginInterface {

  use FieldProducerTrait;

  /**
   * File URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Drupal image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected ImageFactory $imageFactory;

  /**
   * Drupal renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * SVG sanitizer, provided by svg_image module.
   *
   * @var \enshrined\svgSanitize\Sanitizer
   */
  protected Sanitizer $sanitizer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->imageFactory = $container->get('image.factory');
    $instance->renderer = $container->get('renderer');

    if (class_exists('\enshrined\svgSanitize\Sanitizer')) {
      $instance->sanitizer = new Sanitizer();
    }

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    $property = $context->getContextValue('property_name');
    /** @var \Drupal\file\FileInterface $file */
    $file = $item->{$property . '__entity'};
    if (!$file) {
      return NULL;
    }

    $access = $file->access('view', NULL, TRUE);
    $context->addCacheableDependency($access);

    if (!$access->isAllowed()) {
      return NULL;
    }

    $render_context = new RenderContext();
    $url = $this->renderer->executeInRenderContext($render_context, function () use ($file) {
      return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
    });

    if (!$render_context->isEmpty()) {
      $context->addCacheableDependency($render_context->pop());
    }

    $context->addCacheableDependency($file);

    $width = $item->{$property . '__width'} ?? NULL;
    $height = $item->{$property . '__height'} ?? NULL;
    $alt = $item->{$property . '__alt'} ?? NULL;
    $title = $item->{$property . '__title'} ?? NULL;

    if (is_null($width) || is_null($height)) {
      $image = $this->imageFactory->get($file->getFileUri());
      if ($image->isValid()) {
        $width = $image->getWidth();
        $height = $image->getHeight();
      }
    }

    $fields = [
      'url' => $url,
      'width' => $width ?: 0,
      'height' => $height ?: 0,
      'alt' => $alt,
      'title' => $title,
      'size' => (int) $file->getSize(),
      'mime' => $file->getMimeType(),
    ];

    $config = $this->configFactory->get('graphql_compose.settings');
    if ($config->get('settings.svg_image')) {
      $fields['svg'] = $this->getSvgContent($file);
    }

    return $fields;
  }

  /**
   * Get SVG content.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file object.
   *
   * @return \Drupal\Component\Render\MarkupInterface|null
   *   The SVG content or NULL.
   */
  protected function getSvgContent(FileInterface $file): ?MarkupInterface {
    if (!isset($this->sanitizer)) {
      return NULL;
    }

    if ($file->getMimeType() !== 'image/svg+xml') {
      return NULL;
    }

    // Apply max filesize limit.
    $file_size = $file->getSize();
    $config = $this->configFactory->get('graphql_compose.settings');
    $svg_max = $config->get('settings.svg_filesize') ?: 100;
    if (!$file_size || $file_size > $svg_max * 1024) {
      return NULL;
    }

    if (!file_exists($file->getFileUri())) {
      return NULL;
    }

    $raw = file_get_contents($file->getFileUri()) ?: NULL;

    // SVG content cant be trusted,
    // Sanitize SVG content to prevent XSS attacks.
    $content = $this->sanitizer->sanitize($raw);
    if (!$content) {
      return NULL;
    }

    // Strip XML declaration and doctype.
    $content = preg_replace(['/<\?xml.*\?>/i', '/<!DOCTYPE((.|\n|\r)*?)">/i'], '', $content);
    $content = trim($content);

    return Markup::create($content);
  }

}
