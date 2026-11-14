<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "custom_field_viewfield",
 *   type_sdl = "CustomFieldViewfield"
 * )
 */
class CustomFieldViewfield extends GraphQLComposeFieldTypeBase implements FieldProducerItemInterface {

  use FieldProducerTrait;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityRepository = $container->get('entity.repository');
    $instance->tokenService = $container->get('token');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    $property = (string) $context->getContextValue('property_name');
    $view_id = $item->{$property};
    $display_id = $item->{$property . '__display'};

    if (empty($view_id) || empty($display_id)) {
      return NULL;
    }

    $view = Views::getView($view_id);

    if (!$view) {
      return NULL;
    }

    $view->setDisplay($display_id);

    if (!$view->access($display_id)) {
      return NULL;
    }

    $context->addCacheableDependency($view);

    $args = $item->{$property . '__arguments'};
    $arguments = [];
    if (!empty($args)) {
      $arguments = $this->processArguments($args, $item->getEntity());
    }

    $size = $item->{$property . '__items'};
    $page_size = is_numeric($size) ? (int) $size : NULL;
    return [
      'view' => $view_id,
      'display' => $display_id,
      'arguments' => $arguments,
      'pageSize' => $page_size,
    ];
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
  protected function processArguments(string $argument_string, FieldableEntityInterface $entity): array {
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
