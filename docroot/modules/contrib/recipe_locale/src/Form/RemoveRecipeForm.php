<?php

declare(strict_types=1);

namespace Drupal\recipe_locale\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\recipe_locale\LocaleIntegration;
use Drupal\recipe_locale\RecipeTracker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Asks before a recipe is removed from the list.
 */
final class RemoveRecipeForm extends ConfirmFormBase {

  /**
   * The project name of the recipe to remove.
   */
  protected string $name = '';

  /**
   * The recipe's name, as shown to people.
   */
  protected string $label = '';

  public function __construct(
    protected RecipeTracker $tracker,
    protected LocaleIntegration $locale,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(RecipeTracker::class),
      $container->get(LocaleIntegration::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'recipe_locale_remove_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string|null $recipe
   *   The project name of the recipe, from the URL.
   *
   * @return array<string, mixed>
   *   The form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $recipe = NULL): array {
    $recipes = $this->tracker->getAll();
    if ($recipe === NULL || !isset($recipes[$recipe])) {
      throw new NotFoundHttpException();
    }
    $this->name = $recipe;
    $this->label = $recipes[$recipe]['label'];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Remove %recipe from the list of recipes with translations?', ['%recipe' => $this->label]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('The recipe stays applied. The translation files and status stored for it are deleted, along with the stored copy of the configuration it shipped, and no more translation updates are downloaded for it. Applying the recipe again puts it back on the list.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('recipe_locale.recipes');
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->locale->remove($this->name);
    $this->messenger()->addStatus($this->t('%recipe was removed from the list.', ['%recipe' => $this->label]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
