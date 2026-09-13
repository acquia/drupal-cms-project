<?php

declare(strict_types=1);

namespace Drupal\ui_icons_ckeditor5\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\Icon\IconDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller which renders a preview of the provided icon.
 */
final class IconFilterController implements ContainerInjectionInterface {

  public function __construct(
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('renderer'),
    );
  }

  /**
   * Preview an icon.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The icon string rendered.
   */
  public function preview(Request $request): Response {
    $icon_full_id = (string) $request->query->get('icon_id');
    if ($icon_full_id == '') {
      throw new NotFoundHttpException();
    }

    $settings = [];
    $query_settings = (string) $request->query->get('settings');
    if ($query_settings !== '' && json_validate($query_settings)) {
      $settings = json_decode($query_settings, TRUE);
    }

    $build = IconDefinition::getRenderable($icon_full_id, $settings);
    $html = $this->renderer->renderInIsolation($build);

    if (empty($html)) {
      return (new Response('Icon not found!', 404));
    }

    return (new Response((string) $html, 200))
      // Do not allow any intermediary to cache the response, only the end user.
      ->setPrivate()
      // Allow the end user to cache it for up to 5 minutes.
      ->setMaxAge(300);
  }

}
