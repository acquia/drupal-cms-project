<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Validation\Constraint;

use Drupal\custom_field\Plugin\CustomField\FieldType\LinkTypeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Constraint validator for links receiving data allowed by its settings.
 */
class LinkTypeConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   *
   * @param mixed $value
   *   The value to validate.
   * @param \Drupal\custom_field\Plugin\Validation\Constraint\LinkTypeConstraint $constraint
   *   The constraint to apply.
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (isset($value)) {
      $uri_is_valid = TRUE;
      /** @var \Drupal\custom_field\Plugin\DataType\CustomFieldLink $object */
      $object = $this->context->getObject();
      /** @var \Drupal\custom_field\Plugin\Field\FieldType\CustomItem $item */
      $item = $object->getParent();
      $name = $object->getName();
      $custom_items = $item->getCustomFieldManager()->getCustomFieldItems($item->getFieldDefinition()->getSettings());
      $subfield = $custom_items[$name] ?? NULL;
      $url = NULL;
      if (!$subfield instanceof LinkTypeInterface) {
        return;
      }
      $link_type = $subfield->getFieldSetting('link_type');
      if (!$link_type) {
        return;
      }

      // Try to resolve the given URI to a URL. It may fail if it's schemeless.
      try {
        $url = $subfield->getUrl($item);
      }
      catch (\InvalidArgumentException $e) {
        $uri_is_valid = FALSE;
      }

      // If the link field doesn't support both internal and external links,
      // check whether the URL (a resolved URI) is in fact violating either
      // restriction.
      if ($uri_is_valid && $link_type !== LinkTypeInterface::LINK_GENERIC) {
        if (!($link_type & LinkTypeInterface::LINK_EXTERNAL) && $url->isExternal()) {
          $uri_is_valid = FALSE;
        }
        if (!($link_type & LinkTypeInterface::LINK_INTERNAL) && !$url->isExternal()) {
          $uri_is_valid = FALSE;
        }
      }

      if (!$uri_is_valid) {
        $this->context->addViolation($constraint->message, ['@uri' => $value]);
      }
    }
  }

}
