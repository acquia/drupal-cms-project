<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Validation\Constraint;

use Drupal\Component\Utility\UrlHelper;
use Drupal\custom_field\Plugin\CustomField\FieldType\LinkTypeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the CustomFieldLinkExternalProtocols constraint.
 */
class LinkExternalProtocolsConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   *
   * @param mixed $value
   *   The value to validate.
   * @param \Drupal\custom_field\Plugin\Validation\Constraint\LinkExternalProtocolsConstraint $constraint
   *   The constraint to apply.
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (isset($value)) {
      /** @var \Drupal\custom_field\Plugin\DataType\CustomFieldLink $object */
      $object = $this->context->getObject();
      /** @var \Drupal\custom_field\Plugin\Field\FieldType\CustomItem $item */
      $item = $object->getParent();
      $name = $object->getName();
      $custom_items = $item->getCustomFieldManager()->getCustomFieldItems($item->getFieldDefinition()->getSettings());
      $subfield = $custom_items[$name] ?? NULL;
      if (!$subfield instanceof LinkTypeInterface) {
        return;
      }
      try {
        $url = $subfield->getUrl($item);
      }
      // If the URL is malformed this constraint cannot check further.
      catch (\InvalidArgumentException $e) {
        return;
      }
      // Disallow external URLs using untrusted protocols.
      if ($url->isExternal() && !in_array(parse_url($url->getUri(), PHP_URL_SCHEME), UrlHelper::getAllowedProtocols())) {
        $this->context->addViolation($constraint->message, ['@uri' => $value]);
      }
    }
  }

}
