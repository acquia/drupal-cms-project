<?php

declare(strict_types=1);

namespace Drupal\ui_icons_picker\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\ui_icons\IconPreview;
use Drupal\ui_icons\IconSearch;
use Drupal\ui_icons_picker\Ajax\UpdateIconSelectionCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an icon picker selector form.
 */
final class IconSelectForm extends FormBase {

  private const AJAX_WRAPPER_ID = 'icon-results-wrapper';
  private const MESSAGE_WRAPPER_ID = 'icon-message-wrapper';
  private const NUM_PER_PAGE = 247;
  private const PREVIEW_ICON_SIZE = 32;

  /**
   * The icon search service.
   *
   * @var \Drupal\ui_icons\IconSearch
   */
  private IconSearch $iconSearch;

  /**
   * Plugin manager for icons pack discovery and definitions.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  private ?IconPackManagerInterface $pluginManagerIconPack = NULL;

  public function __construct(
    IconPackManagerInterface $pluginManagerIconPack,
    IconSearch $iconSearch,
  ) {
    $this->pluginManagerIconPack = $pluginManagerIconPack;
    $this->iconSearch = $iconSearch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.icon_pack'),
      $container->get('ui_icons.search'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ui_icons_picker_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $options = NULL): array {
    $request = $this->getRequest();

    if (!$request->query->has('dialogOptions')) {
      $redirect = $this->redirect('<front>');
      $redirect->send();
      return [];
    }

    $options = $request->query->all('dialogOptions');
    $wrapper_id = $options['query']['wrapper_id'] ?? NULL;

    if (NULL === $wrapper_id) {
      $redirect = $this->redirect('<front>');
      $redirect->send();
      return [];
    }

    $allowed_icon_pack = $options['query']['allowed_icon_pack'] ?? [];
    if (!empty($allowed_icon_pack)) {
      $allowed_icon_pack = explode('+', $allowed_icon_pack);
    }
    else {
      $allowed_icon_pack = [];
    }

    if (!$modal_state = static::getModalState($form_state)) {
      $icon_list = $this->getIconPackManager()->getIcons($allowed_icon_pack);
      $modal_state = [
        'page' => 0,
        'icon_list' => $icon_list,
        'total_available' => count($icon_list),
      ];
      static::setModalState($form_state, $modal_state);
    }

    $input = $form_state->getUserInput();
    $query = $input['filter'] ?? '';
    $icon_list = $modal_state['icon_list'] ?? [];
    $total_available = $modal_state['total_available'] ?? 0;

    if (empty($query)) {
      $icons = array_keys($icon_list);
      $pager = $this->createPager($modal_state['page'], $total_available);
    }
    else {
      $icons = $this->getIconSearch()->search($query, $allowed_icon_pack, $total_available);
      $pager = $this->createPager($modal_state['page'], count($icons));
    }

    $icons = array_slice($icons, $pager['offset'], self::NUM_PER_PAGE);

    $form['#prefix'] = '<div id="' . self::AJAX_WRAPPER_ID . '"><div id="' . self::MESSAGE_WRAPPER_ID . '"></div>';
    $form['#suffix'] = '</div>';

    $form['wrapper_id'] = [
      '#type' => 'hidden',
      '#value' => $wrapper_id,
    ];

    $ajax_settings = [
      'callback' => [$this, 'searchAjax'],
      'wrapper' => self::AJAX_WRAPPER_ID,
      'effect' => 'fade',
      'progress' => [
        'type' => 'throbber',
      ],
    ];

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];

    $form['filters']['filter'] = [
      '#type' => 'search',
      '#title' => $this->t('Filter'),
      '#placeholder' => $this->t('Filter by name'),
      '#title_display' => 'invisible',
      '#default_value' => !empty($input['filter']) ? $input['filter'] : '',
      '#attributes' => [
        'class' => [
          'icon-filter-input',
        ],
      ],
    ];

    $form['filters']['search'] = [
      '#type' => 'submit',
      '#submit' => [[$this, 'searchSubmit']],
      '#ajax' => $ajax_settings,
      '#value' => $this->t('Search'),
      '#attributes' => [
        'class' => [
          'icon-ajax-search-submit',
          'hidden',
        ],
      ],
    ];

    if (empty($icons)) {
      $form['list'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No icon found, please adjust your filters and try again.'),
      ];
      return $form;
    }

    // Add the generic mass preview library.
    // Set a specific key to have the list of icons to load for preview.
    $form['list'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['icon-picker-modal__content'],
      ],
      '#attached' => [
        'library' => [
          'ui_icons_picker/library',
          'ui_icons/ui_icons.preview',
        ],
        'drupalSettings' => [
          'ui_icons_preview_data' => [
            'icon_full_ids' => $icons,
            'settings' => ['size' => self::PREVIEW_ICON_SIZE],
            'target_input_label' => TRUE,
          ],
        ],
      ],
    ];

    foreach ($this->getIconPackManager()->getDefinitions() as $pack_definition) {
      if (isset($pack_definition['library']) && isset($form['list']['#attached']['library'])) {
        $form['list']['#attached']['library'][] = $pack_definition['library'];
      }
    }

    // Build a list of radio for each icon, without preview for performance. So
    // the modal is displayed as fast as possible.
    // Script js/library.js will handle lazy preview.
    $options = [];
    // Empty icon to allow deletion of selection.
    $options['_none_'] = '<img src="/core/themes/claro/images/icons/e34f4f/crossout.svg" title="Select none" width="32" height="32">';
    foreach ($icons as $icon_data) {
      if (is_array($icon_data)) {
        $options[$icon_data['value']] = $icon_data['label'];
        continue;
      }
      $options[$icon_data] = '<img src="' . IconPreview::SPINNER_ICON . '" title="' . $icon_data . '" width="32" height="32">';
    }

    $form['list']['icon_full_id'] = [
      '#type' => 'radios',
      '#options' => $options,
      '#attributes' => [
        'class' => ['icon-preview-load'],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select'),
      '#submit' => [],
      '#ajax' => [
        'callback' => [$this, 'selectIconAjax'],
        'event' => 'click',
        'disable-refocus' => TRUE,
      ],
      '#attributes' => [
        'class' => [
          'icon-ajax-select-submit',
          'hidden',
        ],
      ],
    ];

    if ($pager['total_page'] <= 1) {
      return $form;
    }

    $form['pagination'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['pagination'],
      ],
    ];

    $form['pagination']['page_previous'] = $pager['page_previous'];
    $form['pagination']['page_next'] = $pager['page_next'];
    $form['pagination']['page_info'] = $pager['page_info'];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    if (!isset($triggering_element['#parents'])) {
      return;
    }

    $clicked_button = end($triggering_element['#parents']);
    if ('submit' !== $clicked_button) {
      return;
    }

    $icon_full_id = $form_state->getValue('icon_full_id');
    if (!$icon_full_id) {
      $form_state->setError($form['list'], $this->t('Pick an icon to insert.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Submission handler for the "Previous page" button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function previousPageSubmit(array $form, FormStateInterface $form_state): void {
    $modal_state = self::getModalState($form_state);
    $modal_state['page']--;
    self::setModalState($form_state, $modal_state);

    $form_state->setRebuild();
  }

  /**
   * Submission handler for the "Next page" button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function nextPageSubmit(array $form, FormStateInterface $form_state): void {
    $modal_state = self::getModalState($form_state);
    $modal_state['page']++;
    self::setModalState($form_state, $modal_state);

    $form_state->setRebuild();
  }

  /**
   * Submission handler for the "Search" button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function searchSubmit(array $form, FormStateInterface $form_state): void {
    $modal_state = self::getModalState($form_state);
    $modal_state['page'] = 0;
    self::setModalState($form_state, $modal_state);

    $form_state->setRebuild();
  }

  /**
   * When searching, simply return a ajax response.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response to replace the form.
   */
  public function searchAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    return $this->ajaxRenderFormAndMessages($form);
  }

  /**
   * Renders form and status messages and returns an ajax response.
   *
   * Used for both submission buttons.
   *
   * @param array $form
   *   The form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response to replace the form.
   */
  protected function ajaxRenderFormAndMessages(array &$form): AjaxResponse {
    $response = new AjaxResponse();
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $renderer = \Drupal::service('renderer');

    $status_messages = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $output = $renderer->renderRoot($form);
    $messages = $renderer->renderRoot($status_messages);

    $message_wrapper_id = '#' . self::MESSAGE_WRAPPER_ID;

    $response->setAttachments($form['#attached']);
    $response->addCommand(new ReplaceCommand('', $output));
    $response->addCommand(new HtmlCommand($message_wrapper_id, ''));
    $response->addCommand(new AppendCommand($message_wrapper_id, $messages));

    return $response;
  }

  /**
   * Handles the AJAX request to select an icon.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response object.
   */
  public function selectIconAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $errors = $form_state->getErrors();
    if ($errors) {
      return self::ajaxRenderFormAndMessages($form);
    }
    $response = new AjaxResponse();

    $icon_full_id = $form_state->getValue('icon_full_id');
    $wrapper_id = $form_state->getValue('wrapper_id');

    // Allow remove the value to delete.
    if ('_none_' === $icon_full_id) {
      $icon_full_id = '';
    }

    $response->addCommand(new UpdateIconSelectionCommand($icon_full_id, $wrapper_id));
    $response->addCommand(new CloseModalDialogCommand(TRUE));

    return $response;
  }

  /**
   * Retrieves the modal state from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state from which to retrieve the modal state.
   *
   * @return mixed
   *   The modal state value.
   */
  public static function getModalState(FormStateInterface $form_state) {
    return NestedArray::getValue($form_state->getStorage(), ['list_state']);
  }

  /**
   * Sets the modal state in the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state in which to set the modal state.
   * @param array $field_state
   *   The modal state value to set.
   */
  public static function setModalState(FormStateInterface $form_state, array $field_state): void {
    NestedArray::setValue($form_state->getStorage(), ['list_state'], $field_state);
  }

  /**
   * Create the pager list.
   *
   * @param int $current_page
   *   The current page.
   * @param int $total
   *   The icons total.
   *
   * @return array
   *   The pager information and form elements.
   */
  private function createPager(int $current_page, int $total): array {
    $ajax_pager = [
      'callback' => [$this, 'searchAjax'],
      'wrapper' => self::AJAX_WRAPPER_ID,
    ];

    $total_page = (int) round($total / self::NUM_PER_PAGE) + 1;
    $arg = ['@current_page' => $current_page + 1, '@total_page' => $total_page];

    return [
      'current_page' => $current_page + 1,
      'total_page' => $total_page,
      'offset' => self::NUM_PER_PAGE * $current_page,
      'total' => $total,
      // Form elements.
      'page_previous' => [
        '#type' => 'submit',
        '#value' => $this->t('Previous page'),
        '#submit' => [[$this, 'previousPageSubmit']],
        '#ajax' => $ajax_pager,
        '#disabled' => !($current_page > 0),
      ],
      'page_next' => [
        '#type' => 'submit',
        '#value' => $this->t('Next page'),
        '#submit' => [[$this, 'nextPageSubmit']],
        '#ajax' => $ajax_pager,
        '#disabled' => !($total_page > $current_page + 1),
      ],
      'page_info' => [
        '#markup' => $this->t('Page @current_page/@total_page', $arg),
      ],
    ];
  }

  /**
   * Get the icon pack plugin manager.
   *
   * @return \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   *   Plugin manager for icon pack discovery and definitions.
   */
  private function getIconPackManager(): IconPackManagerInterface {
    if (!isset($this->pluginManagerIconPack)) {
      // @phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
      $this->pluginManagerIconPack = \Drupal::service('plugin.manager.icon_pack');
    }

    return $this->pluginManagerIconPack;
  }

  /**
   * Get the icon search service.
   *
   * @return \Drupal\ui_icons\IconSearch
   *   Icon search service.
   */
  private function getIconSearch(): IconSearch {
    if (!isset($this->iconSearch)) {
      // @phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
      $this->iconSearch = \Drupal::service('ui_icons.search');
    }

    return $this->iconSearch;
  }

}
