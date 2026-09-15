<?php

namespace Drupal\ai_dashboard\Plugin\Block;

use Drupal\ai_dashboard\AiDocumentationManager;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for module list reduced to given packages.
 *
 * @Block(
 *   id = "ai_documentation",
 *   admin_label = @Translation("AI Documentation Links"),
 *   category = @Translation("AI Dashboard"),
 * )
 */
#[Block(
  id: "ai_documentation",
  admin_label: new TranslatableMarkup("AI Documentation Links"),
  category: new TranslatableMarkup("AI Dashboard"),
)]
class Documentation extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The AI Documentation manager.
   *
   * @var \Drupal\ai_dashboard\AiDocumentationManager
   */
  protected AiDocumentationManager $aiDocumentationManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.ai_documentation')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, AiDocumentationManager $aiDocumentationManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aiDocumentationManager = $aiDocumentationManager;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'soft_limit' => 4,
      'hard_limit' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['soft_limit'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Soft limit'),
      '#description' => $this->t('The number of links to be displayed by default. If the number of links is bigger than this, the "Expand" button will appear. Use 0 to display all links.'),
      '#default_value' => $this->configuration['soft_limit'],
      '#required' => TRUE,
    ];
    $form['hard_limit'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Hard limit'),
      '#description' => $this->t('The number of links to be displayed by the block. If the number of links is bigger than this, the other links will not be displayed. Use 0 or leave empty to display all links.'),
      '#default_value' => $this->configuration['hard_limit'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['soft_limit'] = $form_state->getValue('soft_limit');
    $this->configuration['hard_limit'] = $form_state->getValue('hard_limit');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $items = [];
    $links = $this->aiDocumentationManager->getDefinitions();
    foreach ($links as $link) {
      $items[] = [
        'url' => Url::fromUri($link['url']),
        'title' => $link['label'],
        'description' => $link['description'],
      ];
      if (!empty($this->configuration['hard_limit']) && count($items) >= $this->configuration['hard_limit']) {
        break;
      }
    }
    if (empty($items)) {
      return [];
    }
    $build['links'] = [
      '#theme' => 'admin_block_content',
      '#content' => $items,
    ];
    if (!empty($this->configuration['soft_limit']) && count($items) > $this->configuration['soft_limit']) {
      $build['expand'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Expand'),
        '#attributes' => [
          'class' => [
            'expand',
            'expand-ai-documentation',
            'button',
            'button--small',
            'button--action',
          ],
          'data-soft-limit' => $this->configuration['soft_limit'],
          'aria-label' => $this->t('Show all documentation links'),
          'title' => $this->t('Show all documentation links'),
        ],
      ];

      $build['#attached']['library'][] = 'ai_dashboard/ai_documentation_expand';
    }
    return $build;
  }

}
