<?php

declare(strict_types=1);

namespace Drupal\RecipeKit\Installer\Form;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for altering forms in the early installer.
 *
 * Before the database is created, any form alter hooks in the profile are
 * ignored. The only way to affect them is to extend (or decorate) the form
 * class itself. This class provides the necessary boilerplate to do that.
 *
 * Classes that extend this MUST change the DECORATES constant to the fully
 * qualified name of the form class being altered.
 */
abstract class AlterBase implements FormInterface, ContainerInjectionInterface {

  /**
   * The form class that is being decorated. Must be overridden.
   */
  protected const ?string DECORATES = NULL;

  public function __construct(
    private readonly FormInterface $decorated,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $decorated = $container->get(ClassResolverInterface::class)
      ->getInstanceFromDefinition(static::DECORATES);

    return new static($decorated);
  }

  /**
   * {@inheritdoc}
   */
  final public function getFormId(): string {
    // The form ID should not be alterable.
    return $this->decorated->getFormId();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    return $this->decorated->buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $this->decorated->validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->decorated->submitForm($form, $form_state);
  }

}
