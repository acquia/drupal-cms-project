<?php

declare(strict_types=1);

namespace Drupal\canvas_full_html\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Canvas Full HTML settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->cacheTagsInvalidator = $container->get('cache_tags.invalidator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'canvas_full_html_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['canvas_full_html.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('canvas_full_html.settings');

    $description = $this->t(
      'When enabled, Canvas WYSIWYG editors will use the
      <em>Canvas Full HTML</em> text format (<code>canvas_full_html</code>)
      instead of the restricted Canvas formats. When disabled, Canvas will use
      its default formats (<code>canvas_html_block</code>,
      <code>canvas_html_inline</code>).'
    );

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Full HTML format in Canvas'),
      '#description' => $description,
      '#default_value' => $config->get('enabled') ?? TRUE,
    ];

    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('Information'),
      '#open' => TRUE,
    ];

    $info_text = '<p>' . $this->t(
      'This setting affects all components that use
      <code>contentMediaType: text/html</code> props.'
    ) . '</p>';
    $info_text .= '<p>' . $this->t(
      '<strong>Canvas Full HTML enabled:</strong> Canvas WYSIWYG editors use
      the dedicated <code>canvas_full_html</code> text format, which ships with
      a Canvas-safe CKEditor 5 toolbar. You can customise its toolbar at
      <a href=":url">Administration &gt; Configuration &gt; Content &gt;
      Text formats</a>.',
      [':url' => '/admin/config/content/formats/manage/canvas_full_html'],
    ) . '</p>';
    $info_text .= '<p>' . $this->t(
      '<strong>Canvas Full HTML disabled:</strong> Editors get the restricted
      Canvas formats with limited formatting options.'
    ) . '</p>';
    $info_text .= '<p>' . $this->t(
      '<em>Note: After changing this setting, clear all caches and add new
      component instances to see the change. Existing component instances may
      retain their previous format settings.</em>'
    ) . '</p>';
    $info_text .= '<p>' . $this->t(
      '<strong>CKEditor 5 compatibility:</strong> Both core and contrib
      CKEditor 5 plugins are supported. The module pre-loads all plugin
      libraries enabled on the <code>canvas_full_html</code> editor config,
      so plugins from <code>ckeditor5_plugin_pack</code> and similar modules
      work without any extra configuration.'
    ) . '</p>';

    $form['info']['description'] = [
      '#markup' => $info_text,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('canvas_full_html.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->save();

    parent::submitForm($form, $form_state);

    // Invalidate render and discovery cache tags so changes take effect.
    $this->cacheTagsInvalidator->invalidateTags([
      'rendered',
      'config:filter.format.full_html',
    ]);
  }

}
