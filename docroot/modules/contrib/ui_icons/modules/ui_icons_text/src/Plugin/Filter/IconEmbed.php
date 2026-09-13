<?php

declare(strict_types=1);

namespace Drupal\ui_icons_text\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\ui_icons\IconSearch;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to embed icon items using a custom tag.
 *
 * @internal
 */
#[Filter(
  id: 'icon_embed',
  title: new TranslatableMarkup('Embed icon'),
  description: new TranslatableMarkup('Embeds icon items using a custom tag, <code>&lt;drupal-icon&gt;</code>.'),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
  weight: 100,
  settings: [
    'allowed_icon_pack' => [],
    'result_format' => 'list',
    // Default autocomplete result length. Multiple of 12 to match grid format.
    'max_result' => 24,
  ],
)]
class IconEmbed extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The ui icons service.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  protected $pluginManagerIconPack;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a IconEmbed object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface $plugin_manager_icon_pack
   *   The icon manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    IconPackManagerInterface $plugin_manager_icon_pack,
    RendererInterface $renderer,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginManagerIconPack = $plugin_manager_icon_pack;
    $this->renderer = $renderer;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.icon_pack'),
      $container->get('renderer'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['allowed_icon_pack'] = [
      '#title' => $this->t('Icon Pack selectable'),
      '#type' => 'checkboxes',
      '#options' => $this->pluginManagerIconPack->listIconPackOptions(TRUE),
      '#default_value' => $this->settings['allowed_icon_pack'],
      '#description' => $this->t('If none are selected, all will be allowed.'),
      '#element_validate' => [[static::class, 'validateOptions']],
    ];

    $form['result_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Result format'),
      '#options' => $this->getAutocompleteFormat(),
      '#default_value' => $this->settings['result_format'] ?? 'list',
    ];

    $form['max_result'] = [
      '#type' => 'number',
      '#min' => 2,
      '#max' => IconSearch::SEARCH_RESULT_MAX,
      '#title' => $this->t('Maximum results'),
      '#default_value' => $this->settings['max_result'] ?? IconSearch::SEARCH_RESULT,
    ];

    return $form;
  }

  /**
   * Form element validation handler.
   *
   * @param array $element
   *   The allowed_view_modes form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateOptions(array &$element, FormStateInterface $form_state): void {
    // Filters the #value property so only selected values appear in the
    // config.
    $form_state->setValueForElement($element, array_filter($element['#value']));
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    $result = new FilterProcessResult($text);

    if (stristr($text, '<drupal-icon') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    $query = $xpath->query('//drupal-icon[normalize-space(@data-icon-id)!=""]');

    if (FALSE === $query || empty($query->count())) {
      return $result;
    }

    foreach ($query as $node) {
      /** @var \DOMElement $node */
      $icon_id = $node->getAttribute('data-icon-id');

      // Because of Ckeditor attributes system, we use a single attribute with
      // serialized settings.
      $settings = [];
      /** @var \DOMElement $node */
      $data_settings = $node->getAttribute('data-icon-settings');
      if ($data_settings && json_validate($data_settings)) {
        $settings = json_decode($data_settings, TRUE);
      }

      $attributes = [];
      if ($class = $node->getAttribute('class')) {
        $attributes['class'] = explode(' ', $class);
      }
      if ($aria_label = $node->getAttribute('aria-label')) {
        $attributes['aria-label'] = $aria_label;
      }
      if ($node->getAttribute('aria-hidden')) {
        $attributes['aria-hidden'] = TRUE;
      }
      if ($role = $node->getAttribute('role')) {
        $attributes['role'] = $role;
        // The element with role="presentation" is not part of the accessibility
        // tree and should not have an accessible name.
        // @see https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Roles/presentation_role
        if (in_array($role, ['presentation', 'none'])) {
          unset($attributes['aria-label']);
        }
      }

      $build = $this->getWrappedRenderable($icon_id, $settings, $attributes);
      $this->renderIntoDomNode($build, $node, $result);
    }

    $result->setProcessedText(Html::serialize($dom));

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t('
      <p>You can embed icon:</p>
      <ul>
        <li>Choose which icon item to embed: <code>&lt;drupal-icon data-icon-id="pack_id:icon_id" /&gt;</code></li>
        <li>Optionally also pass settings with data-icon-settings: <code>data-icon-settings="{\'width\':100}"</code>, otherwise the default settings from the Icon Pack definition are used.</li>
      </ul>');
    }
    else {
      return $this->t('You can embed icon items (using the <code>&lt;drupal-icon&gt;</code> tag).');
    }
  }

  /**
   * Wrap icon renderable in a specific class.
   *
   * @param string $icon_full_id
   *   The icon full id to render.
   * @param array $settings
   *   Settings to pass as context to the rendered icon.
   * @param array $attributes
   *   Extra attributes to pass to the wrapper.
   *
   * @return array
   *   Renderable array.
   *
   * @todo wrapping, class and library as filter settings?
   */
  protected function getWrappedRenderable(string $icon_full_id, array $settings, array $attributes): array {
    $attributes['class'][] = 'drupal-icon';
    $attributes = new Attribute($attributes);

    $build = IconDefinition::getRenderable($icon_full_id, $settings);

    $build['#prefix'] = '<span' . $attributes . '>';
    $build['#suffix'] = '</span>';
    $build['#attached']['library'][] = 'ui_icons_text/icon.content';

    return $build;
  }

  /**
   * Renders the given render array into the given DOM node.
   *
   * @todo this is a copy from Media core, not sure we really need it.
   *
   * @param array $build
   *   The render array to render in isolation.
   * @param \DOMNode $node
   *   The DOM node to render into.
   * @param \Drupal\filter\FilterProcessResult $result
   *   The accumulated result of filter processing, updated with the metadata
   *   bubbled during rendering.
   */
  protected function renderIntoDomNode(array $build, \DOMNode $node, FilterProcessResult &$result): void {
    // We need to render the embedded entity:
    // - without replacing placeholders, so that the placeholders are
    //   only replaced at the last possible moment. Hence we cannot use
    //   either renderInIsolation() or renderRoot(), so we must use render().
    // - without bubbling beyond this filter, because filters must
    //   ensure that the bubbleable metadata for the changes they make
    //   when filtering text makes it onto the FilterProcessResult
    //   object that they return ($result). To prevent that bubbling, we
    //   must wrap the call to render() in a render context.
    $markup = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
      return $this->renderer->render($build);
    });

    // Empty rendered mean icon id is probably invalid or removed.
    if ('<span class="drupal-icon"></span>' === (string) $markup) {
      $param = ['@pack_id' => $build['#pack_id'], '@icon_id' => $build['#icon_id']];
      $this->loggerFactory->get('ui_icons')->error('Failed rendering of icon ID "@pack_id:@icon_id", does not exist or has been deleted.', $param);
    }

    $result = $result->merge(BubbleableMetadata::createFromRenderArray($build));
    static::replaceNodeContent($node, $markup);
  }

  /**
   * Replaces the contents of a DOMNode.
   *
   * @param \DOMNode $node
   *   A DOMNode object.
   * @param string $content
   *   The text or HTML that will replace the contents of $node.
   */
  protected static function replaceNodeContent(\DOMNode &$node, $content): void {
    if (strlen((string) $content)) {
      // Load the content into a new DOMDocument and retrieve the DOM nodes.
      $nodes = Html::load($content)->getElementsByTagName('body')
        ->item(0);
      if (NULL === $nodes) {
        return;
      }
      $replacement_nodes = $nodes->childNodes;
    }
    else {
      $node_document = $node->ownerDocument;
      if (NULL === $node_document) {
        return;
      }
      $replacement_nodes = [$node_document->createTextNode('')];
    }

    $node_document = $node->ownerDocument;
    if (NULL === $node_document) {
      return;
    }
    $node_parent = $node->parentNode;
    if (NULL === $node_parent) {
      return;
    }

    foreach ($replacement_nodes as $replacement_node) {
      // Import the replacement node from the new DOMDocument into the original
      // one, importing also the child nodes of the replacement node.
      $replacement_node = $node_document->importNode($replacement_node, TRUE);
      $node_parent->insertBefore($replacement_node, $node);
    }
    $node_parent->removeChild($node);
  }

  /**
   * Get the icon selector autocomplete format.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of format for selectors options.
   */
  private function getAutocompleteFormat(): array {
    return [
      'list' => $this->t('List'),
      'grid' => $this->t('Grid'),
    ];
  }

}
