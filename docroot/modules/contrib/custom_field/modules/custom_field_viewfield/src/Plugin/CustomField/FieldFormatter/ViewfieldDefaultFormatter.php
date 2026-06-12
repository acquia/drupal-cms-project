<?php

declare(strict_types=1);

namespace Drupal\custom_field_viewfield\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Drupal\views\Plugin\views\pager\None;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'viewfield_default' formatter.
 */
#[FieldFormatter(
  id: 'viewfield_default',
  label: new TranslatableMarkup('Viewfield'),
  field_types: [
    'viewfield',
  ],
)]
class ViewfieldDefaultFormatter extends CustomFieldFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenService = $container->get('token');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'always_build_output' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['always_build_output'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always build output'),
      '#default_value' => $this->getSetting('always_build_output'),
      '#description' => $this->t('Produce renderable output even if the view produces no results.<br>This option may be useful for some specialized cases, e.g., to force rendering of an attachment display even if there are no view results.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    $cacheability = new CacheableMetadata();
    $force_default = $this->customFieldDefinition->getFieldSetting('force_default') ?? FALSE;
    $always_build_output = $this->getSetting('always_build_output');
    $entity = $item->getEntity();
    $name = $this->customFieldDefinition->getName();
    if ($force_default) {
      $default_value = $item->getFieldDefinition()->getDefaultValue($entity)[$item->getName()] ?? NULL;
      if (!empty($default_value)) {
        $value = [
          'target_id' => $default_value[$name],
          'display_id' => $default_value[$name . '__display'],
          'arguments' => $default_value[$name . '__arguments'],
          'items_to_display' => $default_value[$name . '__items'],
        ];
      }
    }
    $target_id = $value['target_id'];
    $display_id = $value['display_id'];
    $items_to_display = $value['items_to_display'];

    if (!empty($value['arguments'])) {
      $arguments = $this->processArguments($value['arguments'], $entity);
    }
    else {
      $arguments = [];
    }

    // @see views_embed_view()
    // @see views_get_view_result()
    $view = Views::getView($target_id);
    if (!$view || !$view->access($display_id)) {
      return NULL;
    }

    // Set arguments if they exist.
    if (!empty($arguments)) {
      $view->setArguments($arguments);
    }

    $view->setDisplay($display_id);

    if (!empty($items_to_display)) {
      $view->setItemsPerPage($items_to_display);
    }

    $view->preExecute();
    $view->execute();

    // Disable pager, if items_to_display was set.
    if (!empty($items_to_display)) {
      $view->pager = new None([], '', []);
      $view->pager->init($view, $view->display_handler);
      $view->pager->setItemsPerPage($items_to_display);
    }

    $rendered_view = $view->buildRenderable($display_id);

    // Get cache metadata from view and merge.
    $view_cacheability = CacheableMetadata::createFromRenderArray($view->element);
    $cacheability = $cacheability->merge($view_cacheability);

    if (!empty($view->result || $always_build_output)) {
      // Merge existing cache keys from the view with the arguments used for
      // rendering.
      $cache_keys = array_unique(array_merge($rendered_view['#cache']['keys'] ?? [], $arguments));

      // Update the render array with new cache keys.
      $rendered_view['#cache']['keys'] = $cache_keys;

      // Apply cache metadata to the render array.
      $rendered_view['#cache'] = array_merge($rendered_view['#cache'] ?? [], [
        'tags' => $cacheability->getCacheTags(),
        'contexts' => $cacheability->getCacheContexts(),
        'max-age' => $cacheability->getCacheMaxAge(),
      ]);

      return $rendered_view;
    }

    return NULL;
  }

  /**
   * Perform argument parsing and token replacement.
   *
   * @param string $argument_string
   *   The raw argument string.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity containing this field.
   *
   * @return array
   *   The array of processed arguments.
   */
  protected function processArguments($argument_string, FieldableEntityInterface $entity): array {
    $arguments = [];

    if (!empty($argument_string)) {
      $pos = 0;
      while ($pos < strlen($argument_string)) {
        $found = FALSE;
        // If string starts with a quote, start after quote and get everything
        // before next quote.
        if (strpos($argument_string, '"', $pos) === $pos) {
          if (($quote = strpos($argument_string, '"', ++$pos)) !== FALSE) {
            // Skip pairs of quotes.
            while (!(($ql = strspn($argument_string, '"', (int) $quote)) & 1)) {
              $quote = strpos($argument_string, '"', $quote + $ql);
            }
            $arguments[] = str_replace('""', '"', substr($argument_string, $pos, $quote + $ql - $pos - 1));
            $pos = $quote + $ql + 1;
            $found = TRUE;
          }
        }
        else {
          $arguments = explode('/', $argument_string);
          $pos = strlen($argument_string) + 1;
          $found = TRUE;
        }
        if (!$found) {
          $arguments[] = substr($argument_string, $pos);
          $pos = strlen($argument_string);
        }
      }

      $token_data = [$entity->getEntityTypeId() => $entity];
      foreach ($arguments as $key => $value) {
        $arguments[$key] = $this->tokenService->replace($value, $token_data, ['clear' => TRUE]);
      }
    }

    return array_filter($arguments, function ($value) {
      return trim((string) $value) !== '';
    });
  }

}
