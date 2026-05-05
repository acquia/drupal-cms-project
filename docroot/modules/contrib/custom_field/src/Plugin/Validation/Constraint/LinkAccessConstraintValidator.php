<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\custom_field\Plugin\CustomField\FieldType\LinkTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the CustomFieldLinkAccess constraint.
 */
class LinkAccessConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Proxy for the current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs an instance of the LinkAccessConstraintValidator class.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user account.
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $value
   *   The value to validate.
   * @param \Drupal\custom_field\Plugin\Validation\Constraint\LinkAccessConstraint $constraint
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
      // If the URL is malformed this constraint cannot check access.
      catch (\InvalidArgumentException $e) {
        return;
      }
      // Disallow URLs if the current user doesn't have the 'link to any page'
      // permission nor can access this URI.
      $allowed = $this->currentUser->hasPermission('link to any page') || $url->access();
      if (!$allowed) {
        $this->context->addViolation($constraint->message, ['@uri' => $value]);
      }
    }
  }

}
