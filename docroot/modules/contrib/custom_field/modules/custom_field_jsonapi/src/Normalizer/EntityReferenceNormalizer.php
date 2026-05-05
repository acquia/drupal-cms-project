<?php

namespace Drupal\custom_field_jsonapi\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\TypedData\TypedDataInternalPropertiesHelper;
use Drupal\custom_field\Plugin\DataType\CustomFieldEntityReference;
use Drupal\custom_field\Plugin\DataType\CustomFieldImage;
use Drupal\serialization\Normalizer\ComplexDataNormalizer;

/**
 * Converts the entity_reference custom field value to a JSON:API structure.
 */
class EntityReferenceNormalizer extends ComplexDataNormalizer {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs the EntityReferenceNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository) {
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null {
    $entity = $object->getEntity();
    if ($entity instanceof EntityInterface) {
      // Set the entity in the correct language.
      if ($entity instanceof TranslatableInterface) {
        $entity = $this->entityRepository->getTranslationFromContext($entity);
      }
      $this->addCacheableDependency($context, $entity);

      $attributes = [];
      $typed = $entity->getTypedData();
      $properties = TypedDataInternalPropertiesHelper::getNonInternalProperties($typed);
      // Get the key identifiers.
      $id = $entity->getEntityType()->getKey('id');
      $vid = $entity->getEntityType()->getKey('revision');
      if (method_exists($this->serializer, 'normalize')) {
        foreach ($properties as $name => $property) {
          $attribute = $this->serializer->normalize($property, $format, $context);
          if (is_array($attribute)) {
            if (!empty($attribute) && count($attribute) == 1) {
              // Flatten out single items.
              $attribute = reset($attribute);
              // Flatten out the value.
              if (is_array($attribute) && count($attribute) == 1) {
                $attribute = $attribute['value'] ?? $attribute;
              }
            }
          }
          // Replace property names to be consistent with JSON:API output.
          switch ($name) {
            case $id:
              $name = 'drupal_internal__' . $id;
              break;

            case $vid:
              $name = 'drupal_internal__' . $vid;
              break;

            case 'created':
            case 'changed':
            case 'revision_timestamp':
              $attribute = $attribute['value'] ?? $attribute;
              break;
          }
          $attributes[$name] = $attribute;
        }
        // Standardize the uuid property.
        $attributes = ['id' => $attributes['uuid']] + $attributes;
        unset($attributes['uuid'], $attributes['uid']);

        // Add image metadata.
        if ($object instanceof CustomFieldImage) {
          $attributes['meta'] = [
            'alt' => $object->getAlt(),
            'title' => $object->getTitle(),
            'width' => (int) $object->getWidth(),
            'height' => (int) $object->getHeight(),
          ];
        }
      }

      return $attributes;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      CustomFieldEntityReference::class => TRUE,
      CustomFieldImage::class => TRUE,
    ];
  }

}
