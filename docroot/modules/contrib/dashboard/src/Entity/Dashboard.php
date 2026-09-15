<?php

namespace Drupal\dashboard\Entity;

use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\dashboard\DashboardAccessControlHandler;
use Drupal\dashboard\DashboardInterface;
use Drupal\dashboard\DashboardListBuilder;
use Drupal\dashboard\DashboardStorageHandler;
use Drupal\dashboard\Form\DashboardForm;
use Drupal\dashboard\Form\DashboardLayoutBuilderForm;
use Drupal\layout_builder\SectionListInterface;
use Drupal\layout_builder\SectionListTrait;

/**
 * Defines the dashboard entity type.
 */
#[ConfigEntityType(
  id: 'dashboard',
  label: new TranslatableMarkup('Dashboard'),
  label_collection: new TranslatableMarkup('Dashboards'),
  label_singular: new TranslatableMarkup('dashboard'),
  label_plural: new TranslatableMarkup('dashboards'),
  config_prefix: 'dashboard',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
    'weight' => 'weight',
  ],
  handlers: [
    'access' => DashboardAccessControlHandler::class,
    'storage' => DashboardStorageHandler::class,
    'list_builder' => DashboardListBuilder::class,
    'form' => [
      'add' => DashboardForm::class,
      'edit' => DashboardForm::class,
      'delete' => EntityDeleteForm::class,
      'layout_builder' => DashboardLayoutBuilderForm::class,
    ],
  ],
  links: [
    'collection' => '/admin/structure/dashboard',
    'add-form' => '/admin/structure/dashboard/add',
    'edit-form' => '/admin/structure/dashboard/{dashboard}',
    'delete-form' => '/admin/structure/dashboard/{dashboard}/delete',
    'canonical' => '/admin/dashboard/{dashboard}',
    'preview' => '/admin/structure/dashboard/{dashboard}/preview',
  ],
  admin_permission: 'administer dashboard',
  label_count: [
    'singular' => '@count dashboard',
    'plural' => '@count dashboards',
  ],
  config_export: [
    'id',
    'label',
    'description',
    'layout',
    'weight',
  ]
)]
class Dashboard extends ConfigEntityBase implements DashboardInterface, SectionListInterface {

  use SectionListTrait;

  /**
   * The dashboard ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The dashboard label.
   *
   * @var string
   */
  protected $label;

  /**
   * The dashboard status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The dashboard description.
   *
   * @var string
   */
  protected $description;

  /**
   * The dashboard weight.
   *
   * @var int
   */
  protected $weight;

  /**
   * Layout.
   *
   * @var array
   */
  protected $layout = [];

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->get('weight');
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSections() {
    return $this->layout;
  }

  /**
   * Stores the information for all sections.
   *
   * Implementations of this method are expected to call array_values() to rekey
   * the list of sections.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   An array of section objects.
   *
   * @return $this
   */
  protected function setSections(array $sections) {
    $this->layout = array_values($sections);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'layout-builder', array $options = []) {
    if ($rel === 'layout-builder') {
      $options += [
        'language' => NULL,
        'entity_type' => 'dashboard',
        'entity' => $this,
      ];
      $parameters['dashboard'] = $this->id();
      $uri = new Url("layout_builder.{$this->getEntityTypeId()}.view", $parameters);
      $uri->setOptions($options);
      return $uri;
    }
    return parent::toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    Cache::invalidateTags(['local_task']);
    return parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Add dependency on Layout Builder module if layout is not empty.
    if (!empty($this->get('layout'))) {
      $this->addDependency('module', 'layout_builder');
    }

    // Calculate nested blocks dependencies and include them.
    foreach ($this->getSections() as $section) {
      $this->calculatePluginDependencies($section->getLayout());
      foreach ($section->getComponents() as $component) {
        $this->calculatePluginDependencies($component->getPlugin());
      }
    }

    return $this;
  }

  /**
   * Returns the plugin dependencies being removed.
   *
   * The function recursively computes the intersection between all plugin
   * dependencies and all removed dependencies.
   *
   * Note: The two arguments do not have the same structure.
   *
   * @param array[] $plugin_dependencies
   *   A list of dependencies having the same structure as the return value of
   *   ConfigEntityInterface::calculateDependencies().
   * @param array[] $removed_dependencies
   *   A list of dependencies having the same structure as the input argument of
   *   ConfigEntityInterface::onDependencyRemoval().
   *
   * @return array
   *   A recursively computed intersection.
   *
   * @see \Drupal\Core\Config\Entity\ConfigEntityInterface::calculateDependencies()
   * @see \Drupal\Core\Config\Entity\ConfigEntityInterface::onDependencyRemoval()
   */
  protected function getPluginRemovedDependencies(array $plugin_dependencies, array $removed_dependencies) {
    $intersect = [];
    foreach ($plugin_dependencies as $type => $dependencies) {
      if ($removed_dependencies[$type]) {
        // Config and content entities have the dependency names as keys while
        // module and theme dependencies are indexed arrays of dependency names.
        // @see \Drupal\Core\Config\ConfigManager::callOnDependencyRemoval()
        if (in_array($type, ['config', 'content'])) {
          $removed = array_intersect_key($removed_dependencies[$type], array_flip($dependencies));
        }
        else {
          $removed = array_values(array_intersect($removed_dependencies[$type], $dependencies));
        }
        if ($removed) {
          $intersect[$type] = $removed;
        }
      }
    }
    return $intersect;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);

    // Loop through all sections and determine if the removed dependencies are
    // used by their layout plugins.
    foreach ($this->getSections() as $delta => $section) {
      $layout_dependencies = $this->getPluginDependencies($section->getLayout());
      $layout_removed_dependencies = $this->getPluginRemovedDependencies($layout_dependencies, $dependencies);
      if ($layout_removed_dependencies) {
        // @todo Allow the plugins to react to their dependency removal in
        //   https://www.drupal.org/project/drupal/issues/2579743.
        $this->removeSection($delta);
        $changed = TRUE;
      }
      // If the section is not removed, loop through all components.
      else {
        foreach ($section->getComponents() as $uuid => $component) {
          $plugin_dependencies = $this->getPluginDependencies($component->getPlugin());
          $component_removed_dependencies = $this->getPluginRemovedDependencies($plugin_dependencies, $dependencies);
          if ($component_removed_dependencies) {
            // @todo Allow the plugins to react to their dependency removal in
            //   https://www.drupal.org/project/drupal/issues/2579743.
            $section->removeComponent($uuid);
            $changed = TRUE;
          }
        }
      }
    }
    return $changed;
  }

}
