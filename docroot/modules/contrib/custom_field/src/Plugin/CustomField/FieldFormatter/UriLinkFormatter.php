<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\custom_field\Plugin\CustomField\FieldType\LinkTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Drupal\link\AttributeXss;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'uri_link' formatter.
 */
#[FieldFormatter(
  id: 'uri_link',
  label: new TranslatableMarkup('Link'),
  field_types: [
    'uri',
  ],
)]
class UriLinkFormatter extends CustomFieldFormatterBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityRepository = $container->get('entity.repository');
    $instance->tokenService = $container->get('token');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'link_text' => '',
      'trim_length' => '80',
      'url_plain' => FALSE,
      'url_only' => FALSE,
      'rel' => '',
      'noopener' => '',
      'noreferrer' => '',
      'target' => '',
      'title' => '',
      'class' => '',
      'aria-label' => '',
      'accesskey' => '',
      'name' => '',
      'id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $visibility_path = $form['#visibility_path'];
    $elements['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#description' => $this->t('This field can serve as the link text.'),
      '#default_value' => $this->getSetting('link_text'),
      '#placeholder' => $this->t('e.g. Learn More'),
    ];
    $elements['trim_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Trim link text length'),
      '#field_suffix' => $this->t('characters'),
      '#default_value' => $this->getSetting('trim_length'),
      '#min' => 1,
      '#description' => $this->t('Leave blank to allow unlimited link text lengths.'),
    ];
    $elements['url_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('URL only'),
      '#default_value' => $this->getSetting('url_only'),
      '#access' => $this->getPluginId() == 'link',
    ];
    $elements['url_plain'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show URL as plain text'),
      '#default_value' => $this->getSetting('url_plain'),
    ];
    $elements['rel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add rel="nofollow" to links'),
      '#return_value' => 'nofollow',
      '#default_value' => $this->getSetting('rel'),
    ];
    $elements['target'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open external link in new window'),
      '#description' => $this->t('Adds target="_blank" to external links.'),
      '#return_value' => '_blank',
      '#default_value' => $this->getSetting('target'),
    ];
    $elements['noopener'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add rel="noopener" to links'),
      '#description' => $this->t('Recommended when "Open external link in new window" is checked.'),
      '#states' => [
        'visible' => [
          ':input[name="' . $visibility_path . '[target]"]' => ['checked' => TRUE],
        ],
      ],
      '#return_value' => 'noopener',
      '#default_value' => $this->getSetting('noopener'),
    ];
    $elements['noreferrer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add rel="noreferrer" to links'),
      '#description' => $this->t('Recommended when "Open external link in new window" is checked.'),
      '#states' => [
        'visible' => [
          ':input[name="' . $visibility_path . '[target]"]' => ['checked' => TRUE],
        ],
      ],
      '#return_value' => 'noreferrer',
      '#default_value' => $this->getSetting('noreferrer'),
    ];
    $elements['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->getSetting('title'),
      '#maxlength' => 255,
    ];
    $elements['aria-label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ARIA label'),
      '#default_value' => $this->getSetting('aria-label'),
      '#maxlength' => 255,
    ];
    $elements['class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Class'),
      '#description' => $this->t('Separate multiple classes by a single space.'),
      '#default_value' => $this->getSetting('class'),
    ];
    $elements['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID'),
      '#default_value' => $this->getSetting('id'),
    ];
    $elements['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getSetting('name'),
      '#maxlength' => 255,
    ];
    $elements['accesskey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access key'),
      '#description' => $this->t('Must be a single alphanumeric character. Each access key on a page should be unique to avoid browser conflicts.'),
      '#default_value' => $this->getSetting('accesskey'),
      '#maxlength' => 1,
      '#size' => 1,
      '#pattern' => '[a-zA-Z0-9]',
    ];

    return $elements;
  }

  /**
   * Builds the \Drupal\Core\Url object for a link field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item being rendered.
   * @param array $value
   *   The custom field value.
   *
   * @return \Drupal\Core\Url
   *   A Url object.
   */
  protected function buildUrl(FieldItemInterface $item, array $value): Url {
    assert($this->customFieldDefinition instanceof LinkTypeInterface);
    $settings = $this->getSettings();
    try {
      $url = $this->customFieldDefinition->getUrl($item);
    }
    catch (\InvalidArgumentException $e) {
      // @todo Add logging here in https://www.drupal.org/project/drupal/issues/3348020
      $url = Url::fromRoute('<none>');
    }

    $options = $value['options'] ?? [];
    $options += $url->getOptions();

    // Check for widget attributes.
    $rel = [];
    if (!empty($options['attributes']['rel'])) {
      $rel = explode(' ', $options['attributes']['rel']);
    }
    $class = $options['attributes']['class'] ?? [];
    $id = trim($options['attributes']['id'] ?? '');
    $target = $options['attributes']['target'] ?? '';
    $accesskey = trim($options['attributes']['accesskey'] ?? '');
    $ariaLabel = trim($options['attributes']['aria-label'] ?? '');
    $name = trim($options['attributes']['name'] ?? '');
    $title = trim($options['attributes']['title'] ?? '');

    // Check for rel attributes from settings.
    if (!empty($settings['rel'])) {
      $rel[] = $settings['rel'];
    }
    // Set ID attribute if not already set.
    if (empty($id) && !empty($settings['id'])) {
      $options['attributes']['id'] = $settings['id'];
    }
    // Merge classes.
    if (!empty($settings['class'])) {
      $class = array_merge($class, explode(' ', $settings['class']));
      $options['attributes']['class'] = $class;
    }
    // Set 'target' if not already set and external rel attributes.
    if (empty($target) && !empty($settings['target']) && $url->isExternal()) {
      $options['attributes']['target'] = $settings['target'];
      if (!empty($settings['noopener'])) {
        $rel[] = $settings['noopener'];
      }
      if (!empty($settings['noreferrer'])) {
        $rel[] = $settings['noreferrer'];
      }
    }
    // Set 'accesskey' if not already set.
    if (empty($accesskey) && !empty($settings['accesskey'])) {
      $options['attributes']['accesskey'] = $settings['accesskey'];
    }
    // Set 'aria-label' if not already set.
    if (empty($ariaLabel) && !empty($settings['aria-label'])) {
      $options['attributes']['aria-label'] = $settings['aria-label'];
    }
    // Set 'name' if not already set.
    if (empty($name) && !empty($settings['name'])) {
      $options['attributes']['name'] = $settings['name'];
    }
    // Set 'title' if not already set.
    if (empty($title) && !empty($settings['title'])) {
      $options['attributes']['title'] = $title;
    }
    // Merge all rel attributes as string.
    if (!empty($rel)) {
      $options['attributes']['rel'] = implode(' ', $rel);
    }
    if (!empty($options['attributes'])) {
      $options['attributes'] = AttributeXss::sanitizeAttributes($options['attributes']);
    }
    $url->setOptions($options);

    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    $settings = $this->getSettings();
    $entity = $item->getEntity();
    $langcode = $entity->language()->getId();
    $url = $this->buildUrl($item, $value);
    // Use the full URL as the link title by default.
    $link_title = $url->toString();
    $title = !empty($value['title']) ? $value['title'] : $settings['link_text'];

    // Check for access for linked entities.
    $link_entity = NULL;
    if ($url->isRouted() && preg_match('/^entity\.(\w+)\.canonical$/', $url->getRouteName(), $matches)) {
      // Check access to the canonical entity route.
      $link_entity_type = $matches[1];
      if (!empty($url->getRouteParameters()[$link_entity_type])) {
        $link_entity_param = $url->getRouteParameters()[$link_entity_type];
        if ($link_entity_param instanceof EntityInterface) {
          $link_entity = $link_entity_param;
        }
        elseif (is_string($link_entity_param) || is_numeric($link_entity_param)) {
          try {
            $link_entity_type_storage = $this->entityTypeManager->getStorage($link_entity_type);
            $link_entity = $link_entity_type_storage->load($link_entity_param);
          }
          catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
          }
        }
        // Set the entity in the correct language for display.
        if ($link_entity instanceof TranslatableInterface) {
          $link_entity = $this->entityRepository->getTranslationFromContext($link_entity, $langcode);
        }
        if ($link_entity instanceof EntityInterface) {
          $access = $link_entity->access('view', NULL, TRUE);
          if (!$access->isAllowed()) {
            return NULL;
          }
          if (empty($title)) {
            $title = $link_entity->label();
          }
        }
      }
    }

    // If the title field value is available, use it for the link text.
    if (empty($settings['url_only']) && !empty($title)) {
      // Unsanitized token replacement here because the entire link title
      // gets auto-escaped during link generation in
      // \Drupal\Core\Utility\LinkGenerator::generate().
      $link_title = $this->tokenService->replace($title, [$entity->getEntityTypeId() => $entity], ['clear' => TRUE]);
    }

    // Trim the link text to the desired length.
    if (!empty($settings['trim_length'])) {
      $link_title = Unicode::truncate($link_title, $settings['trim_length'], FALSE, TRUE);
    }

    // For link formatter.
    if ($this->getPluginId() === 'link' && !empty($settings['url_only']) && !empty($settings['url_plain'])) {
      $build = [
        '#plain_text' => $link_title,
      ];
    }
    elseif ($this->getPluginId() === 'uri' && !empty($settings['url_plain'])) {
      $build = [
        '#plain_text' => $link_title,
      ];
    }
    else {
      $build = [
        '#type' => 'link',
        '#title' => $link_title,
        '#options' => $url->getOptions(),
        '#url' => $url,
      ];
    }

    return $build;
  }

}
