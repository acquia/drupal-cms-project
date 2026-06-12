<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "custom_field_file",
 *   type_sdl = "File",
 * )
 */
class CustomFieldFile extends GraphQLComposeFieldTypeBase implements FieldProducerItemInterface, ContainerFactoryPluginInterface {

  use FieldProducerTrait;

  /**
   * File URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Drupal renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

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
    $instance->renderer = $container->get('renderer');

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

    return [
      'url' => $url,
      'name' => $file->getFilename(),
      'size' => (int) $file->getSize(),
      'mime' => $file->getMimeType(),
      'description' => NULL,
    ];
  }

}
