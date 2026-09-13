<?php

namespace Drupal\ai_dashboard\Plugin\Block;

use Drupal\ai_dashboard\Form\SetupAiProviderForm;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for "Setup AI Provider Form".
 *
 * @Block(
 *   id = "ai_setup_ai_provider",
 *   admin_label = @Translation("Setup AI Provider"),
 *   category = @Translation("AI Dashboard"),
 * )
 */
#[Block(
  id: "ai_setup_ai_provider",
  admin_label: new TranslatableMarkup("Setup AI Provider"),
  category: new TranslatableMarkup("AI Dashboard"),
)]
class SetupAiProviderBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
  public function build() {
    return $this->formBuilder->getForm(SetupAiProviderForm::class);
  }

}
