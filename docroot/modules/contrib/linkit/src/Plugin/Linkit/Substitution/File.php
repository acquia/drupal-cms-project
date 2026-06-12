<?php

namespace Drupal\linkit\Plugin\Linkit\Substitution;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileInterface;
use Drupal\linkit\SubstitutionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A substitution plugin for the URL to a file.
 *
 * @Substitution(
 *   id = "file",
 *   label = @Translation("Direct File URL"),
 * )
 */
class File extends PluginBase implements SubstitutionInterface, ContainerFactoryPluginInterface {

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(EntityInterface $entity) {
    if (!($entity instanceof FileInterface)) {
      return NULL;
    }

    return $this->fileUrlGenerator->generate($entity->getFileUri());
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(EntityTypeInterface $entity_type) {
    return $entity_type->entityClassImplements('Drupal\file\FileInterface');
  }

}
