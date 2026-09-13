<?php

namespace Drupal\custom_field_jsonapi\Normalizer;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Url;
use Drupal\custom_field\Plugin\DataType\CustomFieldLink;
use Drupal\serialization\Normalizer\PrimitiveDataNormalizer;

/**
 * Converts the uri custom field value to object including url.
 */
class UriNormalizer extends PrimitiveDataNormalizer {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs the UriNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null {
    assert($object instanceof CustomFieldLink);
    if ($value = $object->getValue()) {
      $url = $this->getUrl($value);
      $entity = NULL;
      $title = $object->getTitle();
      $field_type = $object->getDataDefinition()->getSetting('field_type');

      if ($url->isRouted() && preg_match('/^entity\.(\w+)\.canonical$/', $url->getRouteName(), $matches)) {
        // Check access to the canonical entity route.
        $entity_type = $matches[1];
        if ($entity_param = $url->getRouteParameters()[$entity_type]) {
          if ($entity_param instanceof EntityInterface) {
            $entity = $entity_param;
          }
          elseif (is_string($entity_param) || is_numeric($entity_param)) {
            try {
              $storage = $this->entityTypeManager->getStorage($entity_type);
              $entity = $storage->load($entity_param);
            }
            catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
              // Invalid entity.
            }
          }
          // Set the entity in the correct language.
          if ($entity instanceof TranslatableInterface) {
            $entity = $this->entityRepository->getTranslationFromContext($entity);
          }
        }
      }
      if ($entity instanceof EntityInterface) {
        $this->addCacheableDependency($context, $entity);
        $access = $entity->access('view', NULL, TRUE);
        if (!$access->isAllowed()) {
          return NULL;
        }
        $url = $entity->toUrl();
        if (empty($title)) {
          $title = $entity->label();
        }
      }
      $return = [
        'uri' => (string) $value,
        'url' => $url->isExternal() ? $url->toString() : $url->toString(TRUE)->getGeneratedUrl(),
        'title' => $title,
      ];
      if ($field_type === 'link') {
        $return['options'] = $object->getOptions();
      }
      return $return;
    }

    return NULL;
  }

  /**
   * Helper function to get a Url from given string value.
   *
   * @param string $value
   *   The field value.
   *
   * @return \Drupal\Core\Url|null
   *   The Url object or null.
   */
  protected function getUrl(string $value): ?Url {
    try {
      $url = Url::fromUri($value);
    }
    catch (\InvalidArgumentException $e) {
      $url = NULL;
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      CustomFieldLink::class => TRUE,
    ];
  }

}
