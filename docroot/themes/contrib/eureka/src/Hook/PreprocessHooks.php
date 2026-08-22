<?php

namespace Drupal\eureka\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Hook implementations for preprocess.
 */
class PreprocessHooks {

  /**
   * The theme settings provider.
   *
   * @var \Drupal\Core\Extension\ThemeSettingsProvider
   */
  protected ThemeSettingsProvider $themeSettingsProvider;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a new PreprocessHooks object.
   *
   * @param \Drupal\Core\Extension\ThemeSettingsProvider $themeSettingsProvider
   *   The theme settings provider.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    ThemeSettingsProvider $themeSettingsProvider,
    ConfigFactoryInterface $configFactory,
    AccountProxyInterface $currentUser
  ) {
    $this->themeSettingsProvider = $themeSettingsProvider;
    $this->configFactory = $configFactory;
    $this->currentUser = $currentUser;
  }

  /**
   * Implements template_preprocess_html().
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $variables['scheme'] = $this->themeSettingsProvider->getSetting('scheme');
  }

  /**
   * Implements hook_preprocess_HOOK() for node.html.twig.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'];
    if ($node->bundle() === 'vacancy' && $variables['view_mode'] === 'full') {
      if ($this->currentUser->isAnonymous()) {
        $page_not_found_node = $this->configFactory->get('system.site')->get('page')['404'];
        $url = Url::fromUserInput($page_not_found_node)->toString();
        $response = new RedirectResponse($url);
        $response->send();
        exit;
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for region.html.twig.
   */
  #[Hook('preprocess_region')]
  public function preprocessRegion(array &$variables): void {
    $region = $variables['region'];

    // Get global settings.
    $variables['global_phone'] = $this->themeSettingsProvider->getSetting('telephone');
    $variables['global_email'] = $this->themeSettingsProvider->getSetting('email');
    $variables['global_location'] = $this->themeSettingsProvider->getSetting('location');

    // Get header-specific settings.
    if ($region == 'header') {
      $variables['header_info'] = $this->themeSettingsProvider->getSetting('header_info');
    }

    // Get footer-specific settings.
    if ($region == 'footer') {
      $variables['footer_contact_title'] = $this->themeSettingsProvider->getSetting('footer_contact_title');
      $variables['footer_times_title'] = $this->themeSettingsProvider->getSetting('footer_times_title');
      $variables['footer_quicklinks_title'] = $this->themeSettingsProvider->getSetting('footer_quicklinks_title');
      $footer_times = $this->themeSettingsProvider->getSetting('footer_times');
      $variables['footer_times'] = [
        '#type' => 'processed_text',
        '#text' => $footer_times['value'] ?? '',
        '#format' => $footer_times['format'] ?? 'full_html',
      ];
    }
  }

}
