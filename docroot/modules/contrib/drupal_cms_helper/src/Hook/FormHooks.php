<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Implements form alter hooks for Drupal CMS.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class FormHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Simplifies the user registration form.
   *
   * @todo Remove when Drupal 11.5 is released.
   */
  #[Hook('form_user_register_form_alter')]
  public function alterAccountCreationForm(array &$form, FormStateInterface $form_state): void {
    // Only alter the form on the admin account creation route. On the public
    // registration form, setting notify to TRUE bypasses the admin-approval
    // registration workflow in RegisterForm::save().
    if ($this->routeMatch->getRouteName() === 'user.register') {
      return;
    }
    $visibility = [
      'visible' => [
        'input[name="notify"]' => ['checked' => FALSE],
      ],
    ];
    $form['account']['pass']['#states'] = $visibility;
    $form['account']['status']['#states'] = $visibility;

    // The password isn't required, we'll generate a random one if we need to.
    $form['account']['pass']['#required'] = FALSE;

    // Set element weights explicitly to match what we already have. This allows
    // us to explicitly put the `notify` checkbox right after the `name` field.
    foreach (Element::children($form) as $i => $key) {
      $form[$key]['#weight'] ??= $i * 5;
    }
    foreach (Element::children($form['account']) as $i => $key) {
      $form['account'][$key]['#weight'] ??= $i * 5;
    }
    $form['account']['notify']['#title'] = $this->t('Email user with password setup instructions');
    $form['account']['notify'] += [
      '#default_value' => TRUE,
      '#weight' => $form['account']['name']['#weight'] + 1,
    ];
    $form['#validate'][] = [self::class, 'setPassword'];
  }

  /**
   * Form validation handler: sets a password if needed.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public static function setPassword(array $form, FormStateInterface $form_state): void {
    // If no password was given, generate a random one.
    $password = $form_state->getValue('pass') ?: Crypt::randomBytesBase64(12);
    $form_state->setValue('pass', $password);
  }

  /**
   * Hides the display form when a Canvas template is active.
   *
   * @todo Remove when https://www.drupal.org/i/3551709 is released.
   */
  #[Hook('form_entity_view_display_edit_form_alter')]
  public function alterEntityViewDisplayEditForm(array &$form, FormStateInterface $form_state): void {
    $entity_type_id = $form['#entity_type'];
    $bundle = $form['#bundle'];
    $view_mode = $this->routeMatch->getParameter('view_mode_name');

    // For content, the "default" view mode is actually full. So we need to do
    // this dumb shim.
    if ($view_mode === 'default' && $entity_type_id === 'node') {
      $view_mode = 'full';
    }

    if ($this->entityTypeManager->hasDefinition('content_template')) {
      $template_exists = (bool) $this->entityTypeManager->getStorage('content_template')
        ->getQuery()
        ->accessCheck(FALSE)
        ->count()
        ->condition('content_entity_type_id', $entity_type_id)
        ->condition('content_entity_type_bundle', $bundle)
        ->condition('content_entity_type_view_mode', $view_mode)
        ->condition('status', TRUE)
        ->execute();
    }
    else {
      $template_exists = FALSE;
    }
    // If there's no content template, there's nothing else we need to do.
    if ($template_exists === FALSE) {
      return;
    }

    // Hide everything in the form except the Custom display settings.
    foreach (Element::children($form) as $key) {
      if ($key !== 'modes') {
        $form[$key]['#access'] = FALSE;
      }
      else {
        $form['modes']['#weight'] = 10;
      }
    }

    // If the user has permission to edit the content template, show them a
    // button where they can do that. Otherwise, just show them a message.
    if ($this->currentUser->hasPermission('administer content templates')) {
      $form['canvas_message']['#markup'] = '<p>' . $this->t('This display is handled by Drupal Canvas.') . '</p>';

      $url = Url::fromUri("base:/canvas/template/$entity_type_id/$bundle/$view_mode")
        ->setOption('attributes', [
          'class' => ['button', 'button--primary'],
        ]);
      $form['canvas_button'] = Link::fromTextAndUrl($this->t('Edit template'), $url)->toRenderable() + [
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      ];
    }
    else {
      $form['canvas_message']['#markup'] = $this->t('This display is handled by Drupal Canvas, but you do not have permission to edit it.');
    }
  }

  /**
   * Wraps checkbox elements to suppress Gin's toggle styling.
   *
   * @param array<mixed> $elements
   *   A render array to process, modified by reference.
   *
   * @todo Remove when https://www.drupal.org/i/3594809 is released and we have
   *   switched to use default_admin theme.
   */
  private function disableCheckboxToggles(array &$elements): void {
    foreach (Element::children($elements) as $key) {
      $element = &$elements[$key];
      if (($element['#type'] ?? NULL) === 'checkbox') {
        $element['#prefix'] = ($element['#prefix'] ?? '') . '<div class="form-checkboxes">';
        $element['#suffix'] = '</div>' . ($element['#suffix'] ?? '');
      }
      $this->disableCheckboxToggles($element);
      unset($element);
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for checklistapi_checklist_form.
   *
   * Gin styles checkboxes as toggles, which is confusing in the context of a
   * checklist. The styles use :not() to exclude certain instances, so we can
   * wrap each checklist item in a container with one of those classes.
   *
   * @phpstan-param array<mixed> $form
   */
  #[Hook('form_checklistapi_checklist_form_alter')]
  public function checklistFormAlter(array &$form, FormStateInterface $form_state): void {
    $this->disableCheckboxToggles($form);
  }

}
