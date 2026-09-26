<?php

declare(strict_types=1);

namespace Drupal\custom_field_linkit\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\custom_field\Plugin\CustomField\FieldFormatter\LinkFormatter;
use Drupal\linkit\ProfileInterface;
use Drupal\linkit\SubstitutionManagerInterface;
use Drupal\linkit\Utility\LinkitHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'linkit' formatter for link fields.
 */
#[FieldFormatter(
  id: 'linkit',
  label: new TranslatableMarkup('Linkit'),
  field_types: [
    'link',
  ],
)]
class LinkitFormatter extends LinkFormatter {

  /**
   * The substitution manager.
   *
   * @var \Drupal\linkit\SubstitutionManagerInterface
   */
  protected SubstitutionManagerInterface $substitutionManager;

  /**
   * The linkit profile storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $linkitProfileStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->substitutionManager = $container->get('plugin.manager.linkit.substitution');
    $instance->linkitProfileStorage = $container->get('entity_type.manager')->getStorage('linkit_profile');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'linkit_profile' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $options = array_map(function ($linkit_profile) {
      return $linkit_profile->label();
    }, $this->linkitProfileStorage->loadMultiple());

    $elements['linkit_profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Linkit profile'),
      '#description' => $this->t('Must be the same as the profile selected on the form display for this field.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('linkit_profile'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    $build = parent::formatValue($item, $value);

    if ($build === NULL || empty($build['#url'])) {
      return $build;
    }

    // Try to resolve the entity from Linkit-specific attributes first.
    $entity = NULL;
    if (!empty($value['options']['data-entity-type']) && !empty($value['options']['data-entity-uuid'])) {
      $entity = $this->entityRepository->loadEntityByUuid($value['options']['data-entity-type'], $value['options']['data-entity-uuid']);
      if ($entity instanceof EntityInterface) {
        $entity = $this->entityRepository->getTranslationFromContext($entity);
      }
    }

    // Fall back to resolving entity from the URI.
    if (!$entity instanceof EntityInterface) {
      $entity = LinkitHelper::getEntityFromUserInput($value['uri']);
    }

    if (!$entity instanceof EntityInterface) {
      return $build;
    }

    $substituted_url = $this->getSubstitutedUrl($entity);
    if (!$substituted_url instanceof Url) {
      return $build;
    }

    // Preserve query and fragment from the original URI.
    $parsed_url = parse_url($value['uri']);
    if (!empty($parsed_url['query'])) {
      $parsed_query = [];
      parse_str($parsed_url['query'], $parsed_query);
      if (!empty($parsed_query)) {
        $substituted_url->setOption('query', $parsed_query);
      }
    }
    if (!empty($parsed_url['fragment'])) {
      $substituted_url->setOption('fragment', $parsed_url['fragment']);
    }

    // Merge attributes from the original URL.
    $original_options = $build['#url']->getOptions();
    $substituted_options = $substituted_url->getOptions();
    $attributes = array_merge(
      $original_options['attributes'] ?? [],
      $substituted_options['attributes'] ?? []
    );
    if (!empty($attributes)) {
      $substituted_url->setOption('attributes', $attributes);
    }

    $build['#url'] = $substituted_url;

    return $build;
  }

  /**
   * Returns a substituted URL for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the substituted URL for.
   *
   * @return \Drupal\Core\Url|null
   *   The substituted URL, or NULL if not available.
   */
  protected function getSubstitutedUrl(EntityInterface $entity): ?Url {
    $linkit_profile = $this->linkitProfileStorage->load($this->getSetting('linkit_profile'));

    if (!$linkit_profile instanceof ProfileInterface) {
      return NULL;
    }

    /** @var \Drupal\linkit\Plugin\Linkit\Matcher\EntityMatcher $matcher */
    $matcher = $linkit_profile->getMatcherByEntityType($entity->getEntityTypeId());
    $substitution_type = $matcher ? $matcher->getConfiguration()['settings']['substitution_type'] : SubstitutionManagerInterface::DEFAULT_SUBSTITUTION;
    $url = $this->substitutionManager->createInstance($substitution_type)->getUrl($entity);

    return $url instanceof Url ? $url : NULL;
  }

}
