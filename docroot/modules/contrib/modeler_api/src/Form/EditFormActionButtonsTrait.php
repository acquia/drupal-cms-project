<?php

namespace Drupal\modeler_api\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Provides action button for modeler edit forms.
 */
trait EditFormActionButtonsTrait {

  use StringTranslationTrait;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|null
   */
  protected ?ModuleHandlerInterface $myModuleHandler = NULL;

  /**
   * The modeler API.
   *
   * @var \Drupal\modeler_api\Api|null
   */
  protected ?Api $modelerApi = NULL;

  /**
   * Gets the module handler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  protected function moduleHandler(): ModuleHandlerInterface {
    if ($this->myModuleHandler === NULL) {
      $this->myModuleHandler = \Drupal::service('module_handler');
    }
    return $this->myModuleHandler;
  }

  /**
   * Gets the modeler API.
   *
   * @return \Drupal\modeler_api\Api
   *   The modeler API.
   */
  protected function modelerApi(): Api {
    if ($this->modelerApi === NULL) {
      $this->modelerApi = \Drupal::service('modeler_api.service');
    }
    return $this->modelerApi;
  }

  /**
   * Provides all the action buttons for modelers.
   *
   * @return array
   *   The render array for action buttons.
   */
  protected function actionButtons(ModelOwnerInterface $owner, string $id, bool $readOnly = FALSE): array {
    $basePath = $owner->configEntityBasePath();
    if ($basePath === NULL) {
      return [];
    }
    $modelType = $owner->configEntityTypeId();
    $options = [
      'query' => [
        'destination' => $this->modelerApi()->editUrl($modelType, $id)->toString(),
      ],
    ];
    $buttons = [
      '#type' => 'actions',
      'export_archive' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.' . $modelType . '.export', [$modelType => $id], $options),
        '#title' => $this->t('Archive'),
        '#attributes' => [
          'class' => ['button button--small', 'modeler_api-export-archive'],
          'title' => $this->t('Exports the saved version of the model as an archive file.'),
        ],
        '#weight' => -20,
      ],
      'export_recipe' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.' . $modelType . '.export_recipe', [$modelType => $id], $options),
        '#title' => $this->t('Recipe'),
        '#attributes' => [
          'class' => ['button button--small', 'modeler_api-export-recipe use-ajax'],
          'data-dialog-options' => Json::encode([
            'width' => 400,
            'title' => $this->t('Export Recipe'),
          ]),
          'data-dialog-type' => 'modal',
          'title' => $this->t('Exports the saved version of the model as a recipe.'),
        ],
        '#weight' => -10,
        '#attached' => [
          'library' => [
            'core/drupal.dialog.ajax',
          ],
        ],
      ],
    ];
    if (!$readOnly) {
      $buttons['save'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#attributes' => [
          'class' => ['button--primary', 'modeler_api-save'],
        ],
        '#weight' => -30,
      ];
      if ($this->moduleHandler()->moduleExists('token_browser')) {
        $buttons['token_browser'] = [
          '#theme' => 'token_browser_link',
          '#token_types' => 'all',
          '#global_types' => TRUE,
          '#text' => $this->t('Tokens'),
          '#options' => [
            'attributes' => [
              'class' => ['button', 'button--small'],
              'title' => $this->t('Opens the token browser'),
            ],
          ],
          '#weight' => -5,
        ];
      }
      elseif ($this->moduleHandler()->moduleExists('token')) {
        $buttons['token_browser'] = [
          '#theme' => 'token_tree_link',
          '#token_types' => 'all',
          '#text' => $this->t('Tokens'),
          '#options' => [
            'attributes' => [
              'class' => ['button', 'button--small'],
              'title' => $this->t('Opens the token browser'),
            ],
          ],
          '#weight' => -5,
        ];
      }
    }
    return $buttons;
  }

}
