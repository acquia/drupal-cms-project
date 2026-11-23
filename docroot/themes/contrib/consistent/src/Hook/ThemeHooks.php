<?php

declare(strict_types=1);

namespace Drupal\consistent\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Contains hook implementations for the Consistent theme.
 */
final class ThemeHooks {

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly ThemeExtensionList $themeList,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RouteMatchInterface $routeMatch,
    private readonly TitleResolverInterface $titleResolver,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ThemeSettingsProvider $themeSettingsProvider,
  ) {}

  /**
   * Implements hook_form_system_theme_settings_alter().
   */
  #[Hook('form_system_theme_settings_alter')]
  public function formSystemThemeSettingsAlter(array &$form, FormStateInterface $form_state): void {
    $form['consistent_palette'] = [
      '#type' => 'select',
      '#title' => t('Colour palette'),
      '#description' => t('Select Classic blue and white palette or Bright pink and orange palette.'),
      '#default_value' => $this->themeSettingsProvider->getSetting('consistent_palette') ?? 'classic',
      '#options' => [
        'classic' => t('Classic (default)'),
        'bright' => t('Bright'),
      ],
    ];
  }

  /**
   * Implements hook_preprocess_html().
   *
   * Attaches the appropriate palette library only if the compiled CSS file
   * exists in the build folder. Falls back to Classic when the setting is
   * missing or unrecognised.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $palette = $this->themeSettingsProvider->getSetting('consistent_palette') ?? 'classic';

    if (!in_array($palette, ['classic', 'bright'], TRUE)) {
      $palette = 'classic';
    }

    $theme_path = $this->themeList->getPath('consistent');
    $build_dir = DRUPAL_ROOT . '/' . $theme_path . '/build/';

    $map = [
      'classic' => ['file' => $build_dir . 'classic.css', 'library' => 'consistent/theme-classic'],
      'bright'  => ['file' => $build_dir . 'bright.css', 'library' => 'consistent/theme-bright'],
    ];

    $entry = $map[$palette];

    if (file_exists($entry['file'])) {
      $variables['#attached']['library'][] = $entry['library'];
    }

    $variables['html_attributes']->setAttribute('data-palette', $palette);
  }

  /**
   * Implements hook_preprocess_page().
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $theme_path = $this->themeList->getPath('consistent');
    $using_default = $this->themeSettingsProvider->getSetting('logo.use_default');

    $variables['logo'] = $this->themeSettingsProvider->getSetting('logo.url');

    $variables['logo_white'] = $using_default
      ? '/' . $theme_path . '/images/logo-white.svg'
      : NULL;

    $variables['site_name'] = $this->configFactory->get('system.site')->get('name');

    $variables['title'] = [
      '#type' => 'page_title',
      '#title' => $variables['page']['#title'] ?? $this->titleResolver->getTitle(
        $this->requestStack->getCurrentRequest(),
        $this->routeMatch->getRouteObject(),
      ),
    ];

    $route_name = $this->routeMatch->getRouteName();
    if ($route_name === 'entity.canvas_page.canonical' || str_starts_with($this->routeMatch->getRouteObject()?->getPath() ?? '', '/canvas/')) {
      $variables['rendered_by_canvas'] = TRUE;
    }
    elseif ($route_name === 'entity.node.canonical') {
      $node = $this->routeMatch->getParameter('node');
      assert($node instanceof NodeInterface);

      $variables['rendered_by_canvas'] = (bool) $this->entityTypeManager
        ->getStorage('content_template')
        ->getQuery()
        ->accessCheck(FALSE)
        ->count()
        ->condition('content_entity_type_id', 'node')
        ->condition('content_entity_type_bundle', $node->getType())
        ->condition('content_entity_type_view_mode', 'full')
        ->condition('status', TRUE)
        ->execute();
    }
    else {
      $variables['rendered_by_canvas'] = FALSE;
    }
  }

  /**
   * Implements hook_preprocess_node().
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    if ($variables['node']->bundle() !== 'service_landing_page') {
      return;
    }

    $node = $variables['node'];
    $visible_items = [];

    foreach ($node->field_in_this_section as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) {
        continue;
      }

      $variables['#cache']['tags'] = Cache::mergeTags(
        $variables['#cache']['tags'] ?? [],
        $paragraph->getCacheTags(),
      );

      $link = $paragraph->field_link->first();
      if (!$link) {
        continue;
      }

      try {
        $url = $link->getUrl();
      }
      catch (\Exception $e) {
        continue;
      }

      if (!$url->isRouted()) {
        continue;
      }

      $params = $url->getRouteParameters();
      if (!isset($params['node'])) {
        continue;
      }

      $linked_node = Node::load($params['node']);
      if ($linked_node) {
        $variables['#cache']['tags'] = Cache::mergeTags(
          $variables['#cache']['tags'] ?? [],
          $linked_node->getCacheTags(),
        );

        if ($linked_node->isPublished()) {
          $visible_items[] = $item;
        }
      }
    }

    $variables['service_items'] = $visible_items;
  }

}
