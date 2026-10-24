<?php

namespace Drupal\ai_dashboard\Plugin\Block;

use Drupal\ai_dashboard\Form\PackageFilteredModulesListForm;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for module list reduced to given packages.
 *
 * @Block(
 *   id = "module_list_packages",
 *   admin_label = @Translation("Module List for Packages"),
 *   category = @Translation("AI Dashboard"),
 * )
 */
#[Block(
  id: "module_list_packages",
  admin_label: new TranslatableMarkup("Module List for Packages"),
  category: new TranslatableMarkup("AI Dashboard"),
)]
class ModuleListPackages extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'packages' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['packages'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Packages to display'),
      '#description' => $this->t('The list of packages that will be displayed. Each package should be a new line.'),
      '#default_value' => $this->configuration['packages'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['packages'] = $form_state->getValue('packages');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $packages = str_replace("\r\n", "\n", $this->configuration['packages']);
    $packages = explode("\n", $packages);
    if (!is_array($packages)) {
      $packages = [];
    }
    $packages = array_filter($packages);
    return [
      'help_text' => [
        '#markup' => '<p class="block-help">' . $this->t('Add new AI extensions and expand the capabilities of your site.') . '</p>',
      ],
      'form' => $this->formBuilder->getForm(PackageFilteredModulesListForm::class, $packages),
    ];
  }

}
