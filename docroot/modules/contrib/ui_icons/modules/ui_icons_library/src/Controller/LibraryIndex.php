<?php

declare(strict_types=1);

namespace Drupal\ui_icons_library\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\Core\Url;
use Drupal\ui_icons\IconPreview;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for UI Icons routes.
 */
class LibraryIndex extends ControllerBase {

  private const PREVIEW_ICON_NUM = 32;
  private const PREVIEW_ICON_SIZE = 40;
  private const CACHE_KEY = 'icon_library_card';

  /**
   * Handle the pack disabled appearance.
   *
   * @var string
   */
  private string $showDisable = 'off';

  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
    private SharedTempStoreFactory $tempStoreFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.icon_pack'),
      $container->get('tempstore.shared'),
    );
  }

  /**
   * Index of Pack list.
   *
   * @return array
   *   Render array of packs.
   */
  public function index(): array {
    $temp_store = $this->tempStoreFactory->get('icon_library');
    $this->showDisable = $temp_store->get('disabled') ?? 'off';

    $link = $this->t('hide disabled pack');
    $url = Url::fromRoute('ui_icons_library.mode')->toString();
    if ('off' === $this->showDisable) {
      $link = $this->t('show disabled pack');
      $url = Url::fromRoute('ui_icons_library.mode', ['mode' => 'on'])->toString();
    }

    $build = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['class' => ['description']],
      '#value' => $this->t(
        'List of Icon packs available (<a href="@url">@link</a>).',
        ['@url' => $url, '@link' => $link]
      ),
    ];

    $build['grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card_grid']],
      '#attached' => ['library' => ['ui_icons_library/ui_icons_library.admin']],
    ];

    if ($icon_cache = $this->cache()->get(self::CACHE_KEY)) {
      $icon_data = $icon_cache->data;
      $build['grid']['content'] = $this->buildMultipleCard($icon_data);
      return $build;
    }

    $icon_pack = $this->pluginManagerIconPack->getDefinitions();
    $icon_data = [];
    foreach ($icon_pack as $pack_id => $pack_definition) {
      if (isset($pack_definition['icons'])) {
        $icons = $pack_definition['icons'];
        unset($pack_definition['icons']);
      }
      else {
        $icons = $this->pluginManagerIconPack->getIcons([$pack_id]);
      }

      $icon_data[$pack_id]['pack_definition'] = $pack_definition;
      $total_icons = count($icons);
      $icon_data[$pack_id]['total_icons'] = $total_icons;

      // Keep only a limited number of icons for preview.
      if ($total_icons > self::PREVIEW_ICON_NUM) {
        $rand_keys = array_rand($icons, self::PREVIEW_ICON_NUM);
        $icons = array_intersect_key($icons, array_flip($rand_keys));
      }

      $icon_data[$pack_id]['icons'] = $icons;
      $build['grid']['content'][$pack_id] = $this->buildCard($icon_data[$pack_id]);
    }

    $this->cache()->set(self::CACHE_KEY, $icon_data);

    return $build;
  }

  /**
   * Create the render array for multiple cards.
   *
   * @param array $icon_data
   *   The data to build card with, indexed by icon pack ids.
   */
  public function buildMultipleCard(array $icon_data): array {
    $build = [];
    foreach ($icon_data as $pack_id => $card_data) {
      $build[$pack_id] = $this->buildCard($card_data);
    }
    return $build;
  }

  /**
   * Create the render array for a single card.
   *
   * @param array $card_data
   *   The data to build card with, must include `pack_definition` and `icons`.
   */
  public function buildCard(array $card_data): array {
    $pack_definition = $card_data['pack_definition'];

    if ('off' === $this->showDisable) {
      if (isset($pack_definition['enabled']) && FALSE === $pack_definition['enabled']) {
        return [];
      }
    }

    $build = [
      '#theme' => 'ui_icons_library_card',
      '#label' => $pack_definition['label'] ?? $pack_definition['id'],
      '#description' => $pack_definition['description'] ?? $pack_definition['id'],
      '#version' => $pack_definition['version'] ?? '',
      '#enabled' => $pack_definition['enabled'] ?? TRUE,
      '#link' => Url::fromRoute('ui_icons_library.pack', ['pack_id' => $pack_definition['id']]),
      '#license_name' => $pack_definition['license']['name'] ?? '',
      '#license_url' => $pack_definition['license']['url'] ?? '',
      '#total' => $card_data['total_icons'] ?? NULL,
    ];

    foreach (array_keys($card_data['icons']) as $icon_full_id) {
      $icon = $this->pluginManagerIconPack->getIcon((string) $icon_full_id);
      $build['#icons'][] = IconPreview::getPreview($icon, ['size' => self::PREVIEW_ICON_SIZE]);
    }

    return $build;
  }

  /**
   * Sets whether the library show disabled icon pack.
   *
   * @param string $mode
   *   Valid values are 'on' and 'off'.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the index page.
   */
  public function modeLibrary(string $mode): RedirectResponse {
    $temp_store = $this->tempStoreFactory->get('icon_library');
    $temp_store->set('disabled', $mode);
    return $this->redirect('ui_icons_library.index');
  }

  /**
   * Gets the title of the icon pack.
   *
   * @param string $pack_id
   *   The ID of the icon pack.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title of the icon pack
   */
  public function getTitle(string $pack_id): TranslatableMarkup {
    $icon_pack = $this->pluginManagerIconPack->getDefinitions();

    if (isset($icon_pack[$pack_id])) {
      return $this->t('Icons @name Pack', ['@name' => $icon_pack[$pack_id]['label'] ?? $pack_id]);
    }

    return $this->t('View Icon Pack');
  }

}
