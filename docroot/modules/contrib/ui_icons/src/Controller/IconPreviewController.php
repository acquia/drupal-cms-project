<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\ui_icons\IconPreview;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for preview of multiple icons.
 */
class IconPreviewController extends ControllerBase {

  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.icon_pack'),
      $container->get('renderer'),
    );
  }

  /**
   * Preview an icon.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions for Icons.
   */
  public function preview(Request $request): JsonResponse {
    $parameters = json_decode($request->getContent(), TRUE);
    if (!isset($parameters['icon_full_ids'])) {
      return new JsonResponse([]);
    }

    $settings = $parameters['settings'] ?? [];
    $result = [];
    foreach ($parameters['icon_full_ids'] as $icon_full_id) {
      if (empty($icon_full_id)) {
        continue;
      }

      if (!$icon = $this->pluginManagerIconPack->getIcon($icon_full_id)) {
        continue;
      }

      $icon_preview = IconPreview::getPreview($icon, $settings);
      $result[$icon_full_id] = $this->renderer->renderInIsolation($icon_preview);
    }

    return new JsonResponse($result);
  }

}
