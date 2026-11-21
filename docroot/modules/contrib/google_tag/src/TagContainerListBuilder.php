<?php

namespace Drupal\google_tag;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\google_tag\Entity\TagContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a listing of tag container configuration entities.
 *
 * @see \Drupal\google_tag\Entity\TagContainer
 */
class TagContainerListBuilder extends DraggableListBuilder {

  /**
   * The config factory that knows what is overwritten.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'google_tag_container_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['container_ids'] = $this->t('Container ID(s)');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    assert($entity instanceof TagContainer);
    $row['label'] = $entity->label();
    // The draggable table renders each cell as a form element, so anything
    // other than the label has to be a render array.
    $row['id'] = ['#plain_text' => $entity->id()];
    $row['container_ids'] = [
      '#plain_text' => implode(', ', array_map(
        static fn ($id) => trim((string) $id),
        $entity->get('tag_container_ids')
      )),
    ];
    $config = $this->configFactory->get('google_tag.container.' . $entity->id());
    $status = $config->get('status') ? 'active' : 'inactive';
    if ($config->get('status') != $entity->status()) {
      $status .= ' (overwritten)';
    }
    $row['status'] = ['#plain_text' => $status];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('The container order has been saved.'));
  }

}
