<?php

namespace Drupal\eca_misc\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Builds a URL from a route.
 */
#[Action(
  id: 'eca_build_url_from_route',
  label: new TranslatableMarkup('Build URL from route'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Builds a URL from a route name, parameters and options.'),
  version_introduced: '3.1.3',
)]
class BuildUrlFromRoute extends ConfigurableActionBase {

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setYamlParser($container->get('eca.service.yaml_parser'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'route_name' => '',
      'parameters' => '',
      'options' => '',
      'absolute' => FALSE,
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['route_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Route name'),
      '#default_value' => $this->configuration['route_name'],
      '#description' => $this->t('Example: <code>entity.node.canonical</code>'),
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];

    $form['parameters'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Route parameters'),
      '#default_value' => $this->configuration['parameters'],
      '#description' => $this->t('Enter a key-value list of parameters. Supports YAML format. Example:<em><br />node: 1</em>. When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>node: "[node:nid]"</em>'),
      '#eca_token_replacement' => TRUE,
    ];

    $form['options'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional options'),
      '#default_value' => $this->configuration['options'],
      '#description' => $this->t('Enter a key-value list of options. Supports YAML format. Use dot notation for nested arrays. Example:<pre>query:
  foo: bar
fragment: comments</pre> When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>fragment: "[node:nid]"</em>'),
      '#eca_token_replacement' => TRUE,
    ];

    $form['absolute'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate absolute URL'),
      '#default_value' => $this->configuration['absolute'],
      '#description' => $this->t('Check this to always generate an absolute URL (with scheme and host).'),
    ];

    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -30,
      '#description' => $this->t('Provide the name of a token where the generated URL should be stored.'),
      '#required' => TRUE,
      '#eca_token_reference' => TRUE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['route_name'] = $form_state->getValue('route_name');
    $this->configuration['parameters'] = $form_state->getValue('parameters');
    $this->configuration['options'] = $form_state->getValue('options');
    $this->configuration['absolute'] = !empty($form_state->getValue('absolute'));
    $this->configuration['token_name'] = $form_state->getValue('token_name');
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    // Replace tokens in route name.
    $route_name = $this->tokenService->replace($this->configuration['route_name']);
    $params = $options = [];
    if (!empty($this->configuration['parameters'])) {
      $params = $this->yamlParser->parse($this->configuration['parameters']);
    }
    if (!empty($this->configuration['options'])) {
      $options = $this->yamlParser->parse($this->configuration['options']);
    }
    // Apply absolute flag from checkbox.
    if ($this->configuration['absolute']) {
      $options['absolute'] = TRUE;
    }

    $url = Url::fromRoute($route_name, $params, $options)->toString();

    $this->tokenService->addTokenData($this->configuration['token_name'], $url);
  }

  /**
   * Set the YAML parser.
   *
   * @param \Drupal\eca\Service\YamlParser $yaml_parser
   *   The YAML parser.
   */
  public function setYamlParser(YamlParser $yaml_parser): void {
    $this->yamlParser = $yaml_parser;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = parent::access($object, $account, TRUE);
    if ($result->isAllowed()) {
      $yml_data_options = ['parameters', 'options'];
      foreach ($yml_data_options as $option) {
        if (empty($this->configuration[$option])) {
          continue;
        }
        try {
          $this->yamlParser->parse($this->configuration[$option]);
        }
        catch (ParseException) {
          $result = AccessResult::forbidden($option . ': YAML data is not valid.');
          break;
        }
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

}
