<?php

namespace Drupal\sitemap\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Syndicate (sitemap)' block.
 *
 * @deprecated in sitemap:8.x-2.4 and is removed from sitemap:3.0.0. There is no
 *   replacement.
 *
 * @see https://www.drupal.org/node/3546065
 */
#[Block(
  id: "sitemap_syndicate",
  admin_label: new TranslatableMarkup("Syndicate (sitemap)")
)]
class SitemapSyndicateBlock extends BlockBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * A proxy for the current Drupal user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    @trigger_error('The Syndicate (sitemap) block is deprecated in sitemap:8.x-2.4 and is removed from sitemap:3.0.0. There is no replacement. See https://www.drupal.org/node/3546065', E_USER_DEPRECATED);
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    $instance->routeMatch = $container->get('current_route_match');
    $instance->configFactory = $container->get('config.factory');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'rss_feed_path' => '/rss.xml',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // The destination of the feed link displayed in the block.
    $form['rss_feed_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Feed URL'),
      '#default_value' => $this->configuration['rss_feed_path'],
      '#description' => $this->t('Specify the RSS feed to link to. Defaults to <code>@default</code>.', [
        '@default' => $this->defaultConfiguration()['rss_feed_path'],
      ]),
      '#access' => $this->currentUser->hasPermission('set front page rss link on sitemap'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['rss_feed_path'] = $form_state->getValue('rss_feed_path');

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $route_name = $this->routeMatch->getRouteName();

    if ($route_name == 'blog.user_rss') {
      $feedUrl = Url::fromRoute('blog.user_rss', [
        'user' => $this->routeMatch->getParameter('user'),
      ]);
    }
    elseif ($route_name == 'blog.blog_rss') {
      $feedUrl = Url::fromRoute('blog.blog_rss');
    }
    else {
      $feedUrl = $this->configuration['rss_feed_path'];
    }

    $feed_icon = [
      '#theme' => 'feed_icon',
      '#url' => $feedUrl,
      '#title' => $this->t('Syndicate'),
    ];
    $output = $this->renderer->render($feed_icon);
    // Re-use drupal core's render element.
    $more_link = [
      '#type' => 'more_link',
      '#url' => Url::fromRoute('sitemap.page'),
      '#attributes' => ['title' => $this->t('View the sitemap to see more RSS feeds.')],
    ];
    $output .= $this->renderer->render($more_link);

    return [
      '#type' => 'markup',
      '#markup' => $output,
    ];
  }

}
