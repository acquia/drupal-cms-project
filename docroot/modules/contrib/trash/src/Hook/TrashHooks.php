<?php

declare(strict_types=1);

namespace Drupal\trash\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\trash\Handler\TrashHandlerInterface;
use Drupal\trash\TrashEntityPurger;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Various hook implementations for Trash.
 */
class TrashHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
    protected TrashEntityPurger $trashEntityPurger,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected AccountInterface $currentUser,
    protected DateFormatterInterface $dateFormatter,
    protected TimeInterface $time,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id): void {
    $form_object = $form_state->getFormObject();
    $is_entity_delete_form = $form_object instanceof ContentEntityDeleteForm;
    $is_entity_multiple_delete_form = $form_object instanceof DeleteMultipleForm;
    $is_vbo_confirm_action_form = $form_id === 'views_bulk_operations_confirm_action';

    if (!($is_entity_delete_form || $is_entity_multiple_delete_form || $is_vbo_confirm_action_form)) {
      return;
    }

    if ($this->trashManager->getTrashContext() !== 'active') {
      throw new HttpException(Response::HTTP_NOT_ACCEPTABLE, 'Delete operations are not allowed outside a Trash context.');
    }

    $entity_type = $bundle = $entity = NULL;
    if ($is_entity_delete_form) {
      assert($form_object instanceof ContentEntityDeleteForm);
      $entity = $form_object->getEntity();
      $entity_type = $entity->getEntityType();
      $bundle = $entity->bundle();
    }
    elseif ($is_entity_multiple_delete_form) {
      assert($form_object instanceof DeleteMultipleForm);
      $entity_type_call = function () {
        // @phpstan-ignore-next-line
        return $this->entityType;
      };
      $entity_type = $entity_type_call->call($form_object);
    }
    elseif ($is_vbo_confirm_action_form) {
      $vbo_form_data = $form_state->getStorage()['views_bulk_operations'];
      if ($vbo_form_data['action_id'] !== 'views_bulk_operations_delete_entity') {
        return;
      }

      // Get the first item in the VBO form data to check its entity type.
      $first_item = reset($vbo_form_data['list']);
      // @see \Drupal\views_bulk_operations\Form\ViewsBulkOperationsFormTrait::calculateEntityBulkFormKey()
      $entity_type = $this->entityTypeManager->getDefinition($first_item[2]);
    }

    if ($entity_type && !$this->trashManager->isEntityTypeEnabled($entity_type, $bundle)) {
      return;
    }

    // Change the message of the delete confirmation form to mention the actual
    // action that is about to happen.
    $params = [
      '@label' => !$is_entity_delete_form ? $entity_type->getPluralLabel() : $entity_type->getSingularLabel(),
      ':link' => Url::fromRoute('trash.admin_content_trash_entity_type', [
        'entity_type_id' => $entity_type->id(),
      ])->toString(),
    ];

    // Use different messages based on the user's access level.
    if ($this->currentUser->hasPermission('restore ' . $entity_type->id() . ' entities')) {
      $trash_settings = $this->configFactory->get('trash.settings');
      if ($trash_settings->get('auto_purge.enabled')) {
        $timestamp = strtotime(sprintf('+%s', $trash_settings->get('auto_purge.after')));
        $params['@time_period'] = $this->dateFormatter->formatDiff($this->time->getCurrentTime(), $timestamp);

        $entity_delete_label = $this->t('Deleting this @label will move it to the <a href=":link">trash</a>. You can restore it from the trash for a limited period of time (@time_period) if necessary.', $params);
        $entity_multiple_delete_label = $this->t('Deleting these @label will move them to the <a href=":link">trash</a>. You can restore them from the trash for a limited period of time (@time_period) if necessary.', $params);
      }
      else {
        $entity_delete_label = $this->t('Deleting this @label will move it to the <a href=":link">trash</a>. You can restore it from the trash at a later date if necessary.', $params);
        $entity_multiple_delete_label = $this->t('Deleting these @label will move them to the <a href=":link">trash</a>. You can restore them from the trash at a later date if necessary.', $params);
      }
    }
    elseif ($this->currentUser->hasPermission('access trash')) {
      $entity_delete_label = $this->t('Deleting this @label will move it to the <a href=":link">trash</a>.', $params);
      $entity_multiple_delete_label = $this->t('Deleting these @label will move them to the <a href=":link">trash</a>.', $params);
    }
    else {
      $entity_delete_label = $this->t('Deleting this @label will move it to the trash.', $params);
      $entity_multiple_delete_label = $this->t('Deleting these @label will move them to the trash.', $params);
    }

    // Prevent deleting individual translations.
    // @todo Remove this after https://www.drupal.org/i/3376216 is fixed.
    if ($is_entity_delete_form && $entity instanceof TranslatableInterface && $entity->isTranslatable() && !$entity->isDefaultTranslation()) {
      $entity_delete_label = $this->t('Deleting a translation of a @label is currently not supported by Trash. Unpublish the translation instead.', $params);
      $form['actions']['submit']['#access'] = FALSE;
    }
    elseif ($is_entity_multiple_delete_form && $entity_type->isTranslatable()) {
      $storage = $this->entityTypeManager->getStorage($entity_type->id());

      $can_delete = TRUE;
      foreach (array_keys($form['entities']['#items'] ?? []) as $item) {
        [$id, $langcode] = explode(':', $item, 2);

        // All entities have been loaded already in the static cache by the
        // delete multiple form, so it's ok to single-load them again.
        $entity = $storage->load($id);
        assert($entity instanceof TranslatableInterface);

        // Deleting the default translation is considered the same as deleting
        // the entire entity. When all translations are selected, only the
        // default langcode will show up in the selections.
        if (!$entity->getTranslation($langcode)->isDefaultTranslation()) {
          // If any of the selected translations are not the default translation
          // of the entity, the multiple deletion can not proceed.
          $can_delete = FALSE;
          break;
        }
      }

      if (!$can_delete) {
        $entity_multiple_delete_label = $this->t('Deleting translations of @label is currently not supported by Trash. Unpublish the translations instead.', $params);
        $form['actions']['submit']['#access'] = FALSE;
      }
    }

    $trash_handler = $this->trashManager->getHandler($entity_type->id());
    assert($trash_handler instanceof TrashHandlerInterface);
    if (isset($form['description']['#markup']) && $form['description']['#markup'] instanceof TranslatableMarkup) {
      if (str_contains($form['description']['#markup']->getUntranslatedString(), 'This action cannot be undone.')) {
        if ($is_entity_delete_form) {
          $form['description']['#markup'] = $entity_delete_label;
          $trash_handler->deleteFormAlter($form, $form_state);
        }
        elseif ($is_entity_multiple_delete_form) {
          $form['description']['#markup'] = $entity_multiple_delete_label;
          $trash_handler->deleteFormAlter($form, $form_state, TRUE);
        }
      }
    }
    elseif ($is_vbo_confirm_action_form) {
      $form['description'] = [
        '#markup' => $entity_multiple_delete_label,
        '#weight' => -10,
      ];
      $trash_handler->deleteFormAlter($form, $form_state, TRUE);
    }
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->trashEntityPurger->cronPurge();
  }

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(&$links): void {
    $links['navigation.trash'] = [
      'route_name' => 'trash.admin_content_trash',
      'title' => $this->t('Trash'),
      'menu_name' => 'content',
      'provider' => 'navigation',
      'weight' => 1000,
      'options' => [
        'icon' => [
          'pack_id' => 'trash',
          'icon_id' => 'trash',
          'settings' => [
            'class' => 'toolbar-button__icon',
            'size' => 25,
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled($modules, $is_syncing): void {
    if (!$is_syncing && (
        in_array('node', $modules, TRUE) ||
        (in_array('trash', $modules, TRUE) && $this->moduleHandler->moduleExists('node'))
      )
    ) {
      $trash_settings = $this->configFactory->getEditable('trash.settings');
      $enabled_entity_types = $trash_settings->get('enabled_entity_types') ?? [];
      $enabled_entity_types['node'] ??= [];

      if (in_array('path_alias', $modules, TRUE) ||
        (in_array('trash', $modules, TRUE) && $this->moduleHandler->moduleExists('path_alias'))
      ) {
        $enabled_entity_types['path_alias'] ??= [];
      }

      if (in_array('menu_link_content', $modules, TRUE) ||
        (in_array('trash', $modules, TRUE) && $this->moduleHandler->moduleExists('menu_link_content'))
      ) {
        $enabled_entity_types['menu_link_content'] ??= [];
      }

      $trash_settings->set('enabled_entity_types', $enabled_entity_types)->save();
    }
  }

}
