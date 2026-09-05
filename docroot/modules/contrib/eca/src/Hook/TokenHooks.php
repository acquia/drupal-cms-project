<?php

namespace Drupal\eca\Hook;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Utility\Token;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Token\TokenServices;

/**
 * Implements token hooks for the ECA module.
 */
class TokenHooks {

  /**
   * Constructs a new TokenHooks object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TokenServices $tokenService,
    protected Token $token,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $info = [];
    $info['types']['dto'] = [
      'name' => t('DTO'),
      'description' => t('Tokens containing arbitrary data (Data Transfer Objects).'),
      'needs-data' => 'dto',
      'nested' => TRUE,
      'dynamic' => TRUE,
    ];
    $info['types']['_eca_root_token'] = [
      'name' => t('ECA root-level token'),
      'description' => t('Support for tokens to access data on their root level, for example [list].'),
      'nested' => TRUE,
      'dynamic' => TRUE,
    ];
    $info['types']['plain'] = [
      'name' => t('Plain value'),
      'description' => t('Get the plain text value for any available token, without escaping HTML characters.'),
    ];
    // @see https://www.drupal.org/project/eca/issues/3306180
    $info['tokens']['dto']['dummy'] = [
      'name' => t('Just a dummy'),
    ];
    $info['tokens']['_eca_root_token']['dummy'] = [
      'name' => t('Just a dummy'),
    ];
    $info['tokens']['plain']['?'] = [
      'name' => '?',
      'description' => t('Use any available token. Example: <b>[plain:node:title]</b>'),
    ];
    return $info;
  }

  /**
   * Implements hook_token_info_alter().
   */
  #[Hook('token_info_alter')]
  public function tokenInfoAlter(array &$data): void {
    $definitions = $this->entityTypeManager->getDefinitions();
    foreach ($definitions as $definition) {
      if ($definition instanceof ContentEntityTypeInterface && isset($data['tokens'][$definition->id()])) {
        if ($definition->hasKey('id') && !isset($data['tokens'][$definition->id()]['id'])) {
          $data['tokens'][$definition->id()]['id'] = [
            'name' => t('Entity ID'),
            'description' => t('The ID of the entity.'),
          ];
        }
        if ($definition->hasKey('label') && !isset($data['tokens'][$definition->id()]['label'])) {
          $data['tokens'][$definition->id()]['label'] = [
            'name' => t('Entity label'),
            'description' => t('The label of the entity.'),
          ];
        }
        $data['tokens'][$definition->id()]['entity_type'] = [
          'name' => t('Entity type'),
          'description' => t('The type ID of the entity.'),
        ];
        if ($definition->hasKey('bundle')) {
          $data['tokens'][$definition->id()]['bundle_id'] = [
            'name' => t('Entity bundle'),
            'description' => t('The bundle ID of the entity.'),
          ];
        }
      }
    }
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];

    $definitions = $this->entityTypeManager->getDefinitions();
    if (isset($definitions[$type]) && !empty($data[$type]) && $data[$type] instanceof ContentEntityInterface) {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'id':
            if ($definitions[$type]->hasKey('id')) {
              $replacements[$original] = $data[$type]->id();
            }
            break;

          case 'label':
            if ($definitions[$type]->hasKey('label')) {
              $replacements[$original] = $data[$type]->label();
            }
            break;

          case 'entity_type':
            $replacements[$original] = $data[$type]->getEntityTypeId();
            break;

          case 'bundle_id':
            $replacements[$original] = $data[$type]->bundle();
            break;

        }
      }
    }

    if ($type === 'dto' && !empty($data['dto'])) {
      /** @var \Drupal\eca\Plugin\DataType\DataTransferObject $dto */
      $dto = $data['dto'];
      foreach ($tokens as $name => $original) {
        $access_allowed = TRUE;
        $parts = explode(':', $name);
        $typed_object = $dto;
        $entity = NULL;
        $token_type = NULL;
        $value = NULL;

        // Traverse through the property path, possibly until the leaf point or
        // when we found a token type that may take over for token replacements.
        while (($typed_object instanceof TraversableTypedDataInterface) && !empty($parts)) {
          $property = array_shift($parts);
          if (method_exists($typed_object, 'get') && (isset($typed_object->$property) || ($typed_object instanceof \ArrayAccess && isset($typed_object[$property])))) {
            $typed_object = $typed_object->get($property);
          }
          elseif ($typed_object instanceof ListInterface && !ctype_digit((string) $property)) {
            // Allow implicitly jumping to the first item of the list.
            array_unshift($parts, $property);
            $typed_object = $typed_object->first();
          }
          else {
            $typed_object = NULL;
          }
          if (!($typed_object instanceof TypedDataInterface)) {
            $typed_object = NULL;
            break;
          }

          $value = $typed_object->getValue();

          // Perform access checks and add existing cacheability metadata.
          foreach ([$typed_object, $value] as $subject) {
            if ($subject instanceof CacheableDependencyInterface) {
              $bubbleable_metadata->addCacheableDependency($subject);
            }
            if ($subject instanceof AccessibleInterface) {
              $access_result = $subject->access('view', NULL, TRUE);
              $access_allowed = $access_allowed && $access_result->isAllowed();
              if ($access_result instanceof CacheableDependencyInterface) {
                $bubbleable_metadata->addCacheableDependency($access_result);
              }
            }
          }

          if ($value instanceof EntityInterface) {
            $entity = $value;
          }
          elseif (method_exists($typed_object, 'getEntity') && $entity = $typed_object->getEntity()) {
            // Field items and arbitrary item lists may belong to an entity.
            $bubbleable_metadata->addCacheableDependency($entity);
            $access_result = $entity->access('view', NULL, TRUE);
            $access_allowed = $access_allowed && $access_result->isAllowed();
            if ($access_result instanceof CacheableDependencyInterface) {
              $bubbleable_metadata->addCacheableDependency($access_result);
            }
          }

          if ($entity && $token_type = $this->tokenService->getTokenType($entity)) {
            if (isset($entity->$property)) {
              array_unshift($parts, $property);
            }
            break;
          }

          if ($token_type = $this->tokenService->getTokenType($value)) {
            if ((is_object($value) && isset($value->$property)) || ((is_array($value) || $value instanceof \ArrayAccess) && isset($value[$property]))) {
              array_unshift($parts, $property);
            }
            break;
          }
        }
        if (!$access_allowed || !$typed_object || !$value) {
          continue;
        }

        if ($token_type && $parts) {
          $chained_token = implode(':', $parts);
          if ($entity) {
            $replacements += $this->token->generate($token_type, [$chained_token => $original], [$token_type => $entity], $options, $bubbleable_metadata);
            if (isset($replacements[$original])) {
              continue;
            }
          }

          $replacements += $this->token->generate($token_type, [$chained_token => $original], [$token_type => $value], $options, $bubbleable_metadata);
          if (isset($replacements[$original])) {
            continue;
          }
        }

        if (empty($parts)) {
          // Within the scope of DTOs, we assume conscious handling of string
          // values by their users. Therefore we allow passing through the
          // replacement values as safe markup.
          // @todo Reconsider this once https://www.drupal.org/node/2580723
          // got fixed.
          $replacements[$original] = Markup::create($typed_object->getString());
        }
      }
    }

    if ($type === '_eca_root_token') {
      $available = $data + TokenServices::get()->getTokenData();
      foreach ($tokens as $name => $original) {
        if (!isset($available[$name])) {
          continue;
        }
        $value = $available[$name];
        // Every branch below needs the string form: either as the replacement
        // itself, or - for markup objects - at least to decide whether there
        // is anything to replace at all. Markup::create('') must keep behaving
        // like an empty string, so the emptiness guard is shared by all of
        // them and skips recording a replacement entirely. That leaves the
        // token text in place, unless the "clear" option asks otherwise.
        $replacement = $this->rootTokenValueToString($value);
        if ($replacement === '') {
          continue;
        }
        $replacements[$original] = match (TRUE) {
          // Already-safe markup passes through as the original object, so
          // Token::replace() legitimately skips escaping and any rendering
          // behavior attached to that object stays intact.
          $value instanceof MarkupInterface => $value,
          // DTOs deliberately share the author-trust assumption of the "dto"
          // token type above, so that [mydto] and [mydto:some:property] behave
          // consistently. TokenInterface::addTokenData() wraps every
          // non-entity value into a DTO, which makes this the common case for
          // root-level tokens.
          // @todo Reconsider this once https://www.drupal.org/node/2580723
          // got fixed.
          $value instanceof DataTransferObject => Markup::create($replacement),
          // Anything else - raw scalars and stringable objects handed in
          // through $data, plus entity IDs - carries no safety promise, so
          // leave it unwrapped and let Token::replace() escape it.
          default => $replacement,
        };
      }
    }

    if ($type === 'plain') {
      // Bypassing the auto-escaping of Token::replace() is the whole point of
      // the "plain" token type: [plain:*] is documented to return the plain
      // text value of any available token "without escaping HTML characters".
      // Wrapping the replacement as safe markup is therefore intentional and
      // must not be "hardened" away.
      // @see \Drupal\eca\Hook\TokenHooks::tokenInfo()
      // @see \Drupal\Tests\eca\Kernel\TokenTest::testPlainText()
      foreach ($tokens as $name => $original) {
        $replacement = $this->token->replacePlain('[' . $name . ']', $data, ['clear' => TRUE] + $options, $bubbleable_metadata);
        if ($replacement !== '') {
          $replacements[$original] = Markup::create($replacement);
        }
      }
    }

    return $replacements;
  }

  /**
   * Builds the string form of a root-level token value.
   *
   * The order of the checks matters: a stringable object wins over the entity
   * ID, so that entities implementing __toString() keep rendering through it.
   *
   * @param mixed $value
   *   The value that a root-level token resolved to.
   *
   * @return string
   *   The string form of the value, or an empty string if the value cannot be
   *   expressed as a token replacement at all. That covers NULL, arrays,
   *   FALSE, objects without a string representation, and entities that have
   *   no ID yet because they were never saved.
   */
  private function rootTokenValueToString(mixed $value): string {
    if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
      return (string) $value;
    }
    if ($value instanceof EntityInterface) {
      return (string) $value->id();
    }
    return '';
  }

}
