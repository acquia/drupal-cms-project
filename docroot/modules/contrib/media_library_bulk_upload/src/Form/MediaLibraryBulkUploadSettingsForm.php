<?php

namespace Drupal\media_library_bulk_upload\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Media Library Bulk Upload settings for this site.
 */
class MediaLibraryBulkUploadSettingsForm extends ConfigFormBase {

  /**
   * Constructs a new MediaLibraryBulkUploadSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_library_bulk_upload_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'media_library_bulk_upload.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('media_library_bulk_upload.settings');

    $media_type = $this->entityTypeManager->getDefinition('media_type');
    $bundles_info = $this->entityTypeBundleInfo->getBundleInfo('media');
    $media_types = [];
    foreach ($bundles_info as $key => $bundle) {
      $media_types[$key] = $bundle['label'];
    }

    $form['media_types'] = [
      '#title' => $media_type->getCollectionLabel(),
      '#description' => $this->t('Limit media bulk upload only to the selected @media_types.', ['@media_types' => $media_type->getPluralLabel()]),
      '#type' => 'checkboxes',
      '#default_value' => $config->get('media_types'),
      '#options' => $media_types,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->config('media_library_bulk_upload.settings');
    $config->set('media_types', $form_state->getValue('media_types'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
