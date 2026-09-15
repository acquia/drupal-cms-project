<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Component\Plugin\PluginBase;

/**
 * Applies ECA semantics to actions built on core's FieldUpdateActionBase.
 *
 * <p>Core's Drupal\Core\Field\FieldUpdateActionBase::execute() ends in
 * $entity->save(), which must not happen within an ECA context, least of all in
 * a content_entity:presave model. Its access() also dereferences the given
 * object unconditionally, which raises an \Error when ECA has no entity to
 * offer.</p>
 *
 * <p>ECA therefore does not let those plugins run their own code at all:
 * hook_action_info_alter() rewrites the definition of every action plugin whose
 * class extends core's base class, so that this class is instantiated instead.
 * The original class is preserved in the definition under
 * "eca_original_class", and this class reads the field map from it.</p>
 *
 * <p>This class intentionally carries no #[Action] attribute, so that plugin
 * discovery ignores it.</p>
 *
 * @see \Drupal\eca_content\Hook\PluginHooks::actionInfoAlter()
 */
class CoreFieldUpdateAction extends FieldUpdateActionBase {

  /**
   * The resolved field map, or NULL as long as it has not been resolved yet.
   *
   * @var array|null
   */
  protected ?array $fieldsToUpdate = NULL;

  /**
   * {@inheritdoc}
   */
  protected function getFieldsToUpdate() {
    // Both execute() and access() ask for the field map, and access() does so
    // in a loop, so the result is resolved only once per plugin instance.
    if ($this->fieldsToUpdate !== NULL) {
      return $this->fieldsToUpdate;
    }

    $definition = $this->getPluginDefinition();
    if (!is_array($definition)) {
      $definition = [];
    }
    $original = $definition['eca_original_class'] ?? NULL;
    if (!is_string($original) || !class_exists($original)) {
      // Returning an empty field map would silently turn the action into a
      // no-op, which is exactly the failure mode this class exists to prevent.
      throw new \LogicException(sprintf('The action plugin %s is handled by %s, but its definition does not name an existing original plugin class in "eca_original_class".', $this->getPluginId(), static::class));
    }

    $reflection = new \ReflectionClass($original);
    if (!$reflection->isInstantiable()) {
      throw new \LogicException(sprintf('The original class %s of the action plugin %s cannot be instantiated.', $original, $this->getPluginId()));
    }

    // The field map lives in the original class as a protected, non-static
    // method, so reflection plus some instance of that class is unavoidable.
    // Constructor signatures of foreign subclasses are unknown: core's node
    // actions inherit PluginBase::__construct(), but a contrib subclass may
    // well implement ContainerFactoryPluginInterface with arguments of its own.
    // Bypassing the constructor sidesteps that entirely and needs no container
    // access; the three PluginBase properties are then assigned directly, so
    // that an implementation reading $this->configuration still works.
    $instance = $reflection->newInstanceWithoutConstructor();
    // Hand over the definition with the original class restored, so that the
    // instance does not observe the swapped one.
    $definition['class'] = $original;
    $properties = [
      'configuration' => $this->configuration,
      'pluginId' => $this->getPluginId(),
      'pluginDefinition' => $definition,
    ];
    foreach ($properties as $name => $value) {
      (new \ReflectionProperty(PluginBase::class, $name))->setValue($instance, $value);
    }

    try {
      $fields = $reflection->getMethod('getFieldsToUpdate')->invoke($instance);
    }
    catch (\Throwable $e) {
      // Bypassing the constructor leaves any service property that a foreign
      // subclass may declare uninitialized, and reading such a property raises
      // an \Error. ECA only catches \Exception, so that would be an uncaught
      // fatal error. Convert it into something ECA can log instead.
      throw new \RuntimeException(sprintf('Could not resolve the fields to update of the action plugin %s from its original class %s: %s', $this->getPluginId(), $original, $e->getMessage()), 0, $e);
    }
    if (!is_array($fields)) {
      throw new \UnexpectedValueException(sprintf('%s::getFieldsToUpdate() of the action plugin %s must return an array.', $original, $this->getPluginId()));
    }
    $this->fieldsToUpdate = $fields;

    return $this->fieldsToUpdate;
  }

}
