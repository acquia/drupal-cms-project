<?php

namespace Drupal\ai_seo;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\MainContent\HtmlRenderer;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;

/**
 * Service to render an entity's HTML output.
 */
class RenderEntityHtmlService {

  use StringTranslationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * HTML renderer.
   *
   * @var \Drupal\Core\Render\MainContent\HtmlRenderer
   */
  protected $htmlRenderer;

  /**
   * The route provider to load routes by name.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The HTML response attachments processor service.
   *
   * @var \Drupal\Core\Render\AttachmentsResponseProcessorInterface
   */
  protected $htmlResponseAttachmentsProcessor;

  /**
   * The Drupal kernel.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $drupalKernel;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The account switcher.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Creates the service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   A request stack symfony instance.
   * @param \Drupal\Core\Render\MainContent\HtmlRenderer $html_renderer
   *   HTML renderer.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Drupal\Core\Render\AttachmentsResponseProcessorInterface $html_response_attachments_processor
   *   The HTML response attachments processor service.
   * @param \Drupal\Core\DrupalKernelInterface $drupal_kernel
   *   The Drupal kernel.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
      EntityTypeManagerInterface $entity_type_manager,
      RequestStack $request_stack,
      HtmlRenderer $html_renderer,
      RouteProviderInterface $route_provider,
      AttachmentsResponseProcessorInterface $html_response_attachments_processor,
      DrupalKernelInterface $drupal_kernel,
      LoggerChannelFactoryInterface $logger,
      MessengerInterface $messenger,
      AccountSwitcherInterface $account_switcher,
      AccountProxyInterface $current_user
    ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->htmlRenderer = $html_renderer;
    $this->routeProvider = $route_provider;
    $this->htmlResponseAttachmentsProcessor = $html_response_attachments_processor;
    $this->drupalKernel = $drupal_kernel;
    $this->logger = $logger->get('ai_seo');
    $this->messenger = $messenger;
    $this->accountSwitcher = $account_switcher;
    $this->currentUser = $current_user;
  }


  /**
   * Renders HTML for a specified entity.
   *
   * @param string $entity_type_id
   *   The type of the entity (e.g., 'node', 'user').
   * @param int $entity_id
   *   The unique identifier of the entity to be rendered.
   * @param int|null $revision_id
   *   Optional entity revision ID. (optional)
   * @param string $view_mode
   *   The view mode in which the entity will be rendered. (optional)
   *   Defaults to 'full'. Other common view modes include 'teaser', 'compact'.
   * @param string|null $langcode
   *   The language code for the rendering of the entity. (optional)
   *   If NULL, the default site language will be used.
   * @param array $options
   *  Additional options for rendering. (optional)
   *
   * @return string
   *   The HTML rendering of the entity.
   */
  public function renderHtml(string $entity_type_id, int $entity_id, ?int $revision_id = NULL, string $view_mode = 'full', ?string $langcode = NULL, array $options = []): ?string {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    if (!empty($revision_id)) {
      // If revision ID is specified, load the entity at that revision.
      $entity = $storage->loadRevision($revision_id);
    }
    else {
      // Otherwise load the default entity.
      $entity = $storage->load($entity_id);
      if (!empty($langcode) && $entity->hasTranslation($langcode)) {
        // Get translation if necessary.
        $entity = $entity->getTranslation($langcode);
      }
    }

    if (empty($entity)) {
      $this->logger->error($this->t('Entity not found - Entity type ID: :entity_type_id - Entity ID: :entity_id - Revision ID: :revision_id - Langcode: :langcode', [
        ':entity_type_id' => $entity_type_id,
        ':entity_id' => $entity_id,
        ':revision_id' => $revision_id,
        ':langcode' => $langcode,
      ]));
      return NULL;
    }

    // Check whether to request as anonymous.
    $request_as_anonymous = $options['request_as_anonymous'] ?? TRUE;

    // Store original theme for restoration
    $original_theme = NULL;

    // Switch to the anonymous user.
    if ($request_as_anonymous) {
      $this->accountSwitcher->switchTo(new AnonymousUserSession());

      // Force default theme (not admin theme) for anonymous requests.
      $theme_manager = \Drupal::service('theme.manager');
      $original_theme = $theme_manager->getActiveTheme();

      $default_theme = \Drupal::config('system.theme')->get('default');
      $active_theme = \Drupal::service('theme.initialization')->getActiveThemeByName($default_theme);
      \Drupal::theme()->setActiveTheme($active_theme);
    }

    try {
      // Get the URL to either revision or full node.
      $url = (!empty($revision_id)) ? $entity->toUrl('revision') : $entity->toUrl();
      $url_string = $url->toString();

      // Create a sub request to fix the context.
      $current_request = $this->requestStack->getCurrentRequest();
      $included_cookies = !$request_as_anonymous ? $current_request->cookies->all() : [];

      // Create the request manually.
      $request = Request::create($url_string, 'GET', [], $included_cookies, [], $current_request->server->all());

      // Always set a session, even for anonymous requests
      if (!$request_as_anonymous) {
        $request->setSession($current_request->getSession());
      } else {
        // For anonymous requests, check if current session exists and use it
        if ($current_request->hasSession()) {
          $request->setSession($current_request->getSession());
        } else {
          // Only create a new session if none exists
          $session = new Session();
          if (session_status() === PHP_SESSION_NONE) {
            $session->start();
          }
          $request->setSession($session);
        }
      }

      // Create a RouteMatch object with the correct context.
      $route_parameters = [$entity_type_id => $entity_id];
      if (!empty($revision_id)) {
        $route_parameters[$entity_type_id . '_revision'] = $revision_id;
        $route_name = 'entity.' . $entity_type_id . '.revision';
      } else {
        $route_name = 'entity.' . $entity_type_id . '.canonical';
      }

      $route = $this->routeProvider->getRouteByName($route_name);

      // Create RouteMatch with entity object for controller resolution
      $route_match_parameters = [$entity_type_id => $entity];
      if (!empty($revision_id)) {
        $route_match_parameters[$entity_type_id . '_revision'] = $revision_id;
      }
      $route_match = new RouteMatch($route_name, $route, $route_match_parameters, $route_parameters);
      // Set the route parameters as request attributes for controller argument resolution.
      $request->attributes->set($entity_type_id, $entity);
      if (!empty($revision_id)) {
        $request->attributes->set($entity_type_id . '_revision', $revision_id);
      }
      // Also set route metadata - use entity IDs for URL generation
      $request->attributes->set('_route', $route_name);
      $request->attributes->set('_route_object', $route);
      $request->attributes->set('_route_params', $route_parameters);

      // Set raw parameters for URL generation but keep entity objects for controllers
      $request->attributes->set('_raw_variables', new \Symfony\Component\HttpFoundation\ParameterBag($route_parameters));

      // Push the new request to the stack for proper context.
      $this->requestStack->push($request);

      try {
        // Store original route match to restore later
        $original_route_match = \Drupal::service('current_route_match');

        // Create a proper current route match service that wraps our RouteMatch
        $current_route_match_service = new class($route_match) implements \Drupal\Core\Routing\RouteMatchInterface {
          private $routeMatch;

          public function __construct($routeMatch) {
            $this->routeMatch = $routeMatch;
          }

          public function getCurrentRouteMatch() {
            return $this->routeMatch;
          }

          public function getRouteName() {
            return $this->routeMatch->getRouteName();
          }

          public function getRouteObject() {
            return $this->routeMatch->getRouteObject();
          }

          public function getParameter($parameter_name) {
            return $this->routeMatch->getParameter($parameter_name);
          }

          public function getParameters() {
            return $this->routeMatch->getParameters();
          }

          public function getRawParameter($parameter_name) {
            return $this->routeMatch->getRawParameter($parameter_name);
          }

          public function getRawParameters() {
            return $this->routeMatch->getRawParameters();
          }

          public function __call($method, $args) {
            return call_user_func_array([$this->routeMatch, $method], $args);
          }
        };

        // Set the route match in the current route match service.
        \Drupal::getContainer()->set('current_route_match', $current_route_match_service);

        // Also update the request context for URL generation
        $request_context = \Drupal::service('router.request_context');
        $original_path_info = $request_context->getPathInfo();
        $request_context->fromRequest($request);

        // Build the entity view.
        $viewBuilder = $this->entityTypeManager->getViewBuilder($entity_type_id);
        $build = $viewBuilder->view($entity, $view_mode);

        // Get metatags using the proper metatag workflow
        if (\Drupal::moduleHandler()->moduleExists('metatag')) {
          $metatag_manager = \Drupal::service('metatag.manager');

          // Get entity-specific metatags
          $entity_metatags = $metatag_manager->tagsFromEntity($entity);

          // Get default metatags for this entity
          $default_metatags = $metatag_manager->defaultTagsFromEntity($entity);

          // Merge them (entity-specific takes precedence)
          $all_metatags = $entity_metatags + $default_metatags;

          // Filter out problematic schema metatags that require complex routing
          $filtered_metatags = [];
          foreach ($all_metatags as $tag_name => $tag_value) {
            // Skip schema breadcrumb tags that cause routing issues
            if (strpos($tag_name, 'schema_') === 0 &&
                (strpos($tag_name, 'breadcrumb') !== false ||
                 strpos($tag_name, 'breadcrumblist') !== false)) {
              continue;
            }
            $filtered_metatags[$tag_name] = $tag_value;
          }

          // Generate the metatag elements
          if (!empty($filtered_metatags)) {
            try {
              $metatag_elements = $metatag_manager->generateElements($filtered_metatags, $entity);
              if (!empty($metatag_elements['#attached']['html_head'])) {
                $build['#attached']['html_head'] = array_merge(
                  $build['#attached']['html_head'] ?? [],
                  $metatag_elements['#attached']['html_head']
                );
              }
            } catch (\Exception $e) {
              // Log the error but don't fail the entire rendering
              $this->logger->warning('Error generating metatags: @message', ['@message' => $e->getMessage()]);
            }
          }
        }

        // Render the page with full context.
        $response = $this->htmlRenderer->renderResponse($build, $request, $route_match);

        // Process attachments (this should include metatags).
        $response = $this->htmlResponseAttachmentsProcessor->processAttachments($response);

        // Get the content.
        $content = $response->getContent();

        // Restore original route match
        \Drupal::getContainer()->set('current_route_match', $original_route_match);
      }
      finally {
        // Pop the request from the stack.
        $this->requestStack->pop();
      }
    }
    finally {
      if ($request_as_anonymous) {
        // Restore original theme
        if ($original_theme) {
          \Drupal::theme()->setActiveTheme($original_theme);
        }

        // Revert back to the original user.
        $this->accountSwitcher->switchBack();
      }
    }

    return $content;
  }

}
