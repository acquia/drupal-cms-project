<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Validation\Constraint;

use Drupal\custom_field\Plugin\CustomField\FieldType\LinkTypeInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the CustomFieldLinkNotExistingInternal constraint.
 */
class LinkNotExistingInternalConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   *
   * @param mixed $value
   *   The value to validate.
   * @param \Drupal\custom_field\Plugin\Validation\Constraint\LinkNotExistingInternalConstraint $constraint
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

      if ($url->isRouted()) {
        $allowed = TRUE;
        try {
          $url->toString(TRUE);
        }
        // The following exceptions are all possible during URL generation, and
        // should be considered as disallowed URLs.
        catch (RouteNotFoundException $e) {
          $allowed = FALSE;
        }
        catch (InvalidParameterException $e) {
          $allowed = FALSE;
        }
        catch (MissingMandatoryParametersException $e) {
          $allowed = FALSE;
        }
        if (!$allowed) {
          $this->context->addViolation($constraint->message, ['@uri' => $value]);
        }
      }
    }
  }

}
