<?php

declare(strict_types=1);

namespace Drupal\ui_icons_library\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\ui_icons\IconSearch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function Symfony\Component\String\u;

/**
 * Provides a UI Icons form.
 *
 * @codeCoverageIgnore
 */
final class LibrarySearchForm extends FormBase {

  private const NUM_PER_PAGE = 100;
  private const ICON_DEFAULT_SIZE = 64;

  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
    private readonly PagerManagerInterface $pagerManager,
    private readonly IconSearch $iconSearch,
    private SharedTempStoreFactory $tempStoreFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.icon_pack'),
      $container->get('pager.manager'),
      $container->get('ui_icons.search'),
      $container->get('tempstore.shared'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ui_icons_library_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $pack_id = ''): array {
    $temp_store = $this->tempStoreFactory->get('ui_icons_library_search');
    $values = $temp_store->get($pack_id);

    // Build default settings and try to override size for display.
    $default = [
      $pack_id => array_merge(
        $this->pluginManagerIconPack->getExtractorFormDefaults($pack_id),
        [
          'size' => self::ICON_DEFAULT_SIZE,
          'width' => self::ICON_DEFAULT_SIZE,
          'height' => self::ICON_DEFAULT_SIZE,
        ],
      ),
    ];

    $settings = $values['settings'] ?? $default;
    $search = $values['search'] ?? '';
    $group = $values['group'] ?? '';

    $form['#theme'] = 'form_icon_pack';
    $form['pack_id'] = [
      '#type' => 'hidden',
      '#value' => $pack_id,
    ];

    $form['search'] = [
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => $search,
      '#title' => $this->t('Keywords'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Keywords'),
      '#attributes' => [
        'minlength' => IconSearch::SEARCH_MIN_LENGTH,
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => ['class' => ['button--primary']],
    ];

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#name' => 'reset',
      '#value' => $this->t('Reset'),
    ];

    $form['actions']['#weight'] = -9;

    // Load all icons to get a total and find the groups.
    $icons_list = $this->pluginManagerIconPack->getIcons([$pack_id]);
    $total_available = count($icons_list);

    $group_options = $this->getGroupList($icons_list);
    if (!empty($group_options)) {
      $form['group'] = [
        '#type' => 'select',
        '#title_display' => 'invisible',
        '#title' => $this->t('Group'),
        '#default_value' => $group,
        '#options' => ['' => $this->t('- Select group -')] + $group_options,
      ];
    }

    if (empty($search)) {
      if (!empty($group)) {
        foreach ($icons_list as $key => $icon) {
          if (isset($icon['group']) && $group !== $icon['group']) {
            unset($icons_list[$key]);
          }
        }
      }
      $icons = array_keys($icons_list);
    }
    else {
      $icons = $this->iconSearch->search($search, [$pack_id], $total_available);
    }

    $total = count($icons);
    $pager = $this->pagerManager->createPager($total, self::NUM_PER_PAGE);
    $page = $pager->getCurrentPage();
    $offset = self::NUM_PER_PAGE * $page;

    $icons = array_slice($icons, $offset, self::NUM_PER_PAGE);

    $form['list'] = [
      '#theme' => 'ui_icons_library',
      '#search' => $search,
      '#icons' => $icons,
      '#settings' => $settings[$pack_id] ?? [],
      '#total' => $total,
      '#available' => $total_available,
    ];

    $form['settings'] = ['#tree' => TRUE];
    $this->pluginManagerIconPack->getExtractorPluginForms(
      $form['settings'],
      $form_state,
      $settings,
      [$pack_id => $pack_id],
    );

    $form['pager_top'] = [
      '#type' => 'pager',
    ];

    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $temp_store = $this->tempStoreFactory->get('ui_icons_library_search');
    $values = $form_state->getValues();
    $pack_id = $values['pack_id'];

    $trigger = $form_state->getTriggeringElement();
    if (isset($trigger['#name']) && 'reset' === $trigger['#name']) {
      $temp_store->delete($pack_id);
      return;
    }

    foreach (array_keys($values) as $key) {
      if (!in_array($key, ['search', 'group', 'settings'])) {
        unset($values[$key]);
      }
    }

    $temp_store->set($pack_id, $values);
  }

  /**
   * Build a group list options.
   *
   * @param array $icons_list
   *   The list of icons.
   *
   * @return array
   *   The list of groups.
   */
  private function getGroupList(array $icons_list): array {
    $result = [];
    foreach ($icons_list as $icon) {
      $group_id = $icon['group'] ?? NULL;
      if (empty($group_id)) {
        continue;
      }
      $group_name = u($group_id)->snake()->replace('_', ' ')->title(allWords: TRUE);
      $result[$group_id] = $group_name;
    }

    if (!empty($result)) {
      ksort($result);
    }

    return $result;
  }

}
