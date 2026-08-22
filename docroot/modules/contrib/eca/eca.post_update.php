<?php

/**
 * @file
 * Contains post_update hooks for eca.
 */

use Drupal\Core\Utility\UpdateException;
use Drupal\eca\Entity\Eca;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Renames the "form_id" field of a form event in an ECA 2 BPMN diagram.
 *
 * ECA 3 uses "form_ids" instead.
 *
 * The diagram carries a copy of the form event configuration as a
 * camunda:field. A plain str_replace over the whole XML would rewrite every
 * camunda:field named "form_id", including one that belongs to a non-form
 * element or nested data field. This parses the XML and renames only the field
 * that is a direct child of the extension elements of a BPMN element whose
 * pluginid property identifies a form plugin.
 *
 * @param string $xml
 *   The raw BPMN diagram.
 *
 * @return string
 *   The diagram with the form event field renamed, or the original unchanged
 *   diagram when it could not be parsed or contained no form event field.
 */
function _eca_post_update_rename_diagram_form_id(string $xml): string {
  $document = new \DOMDocument();
  $previous = libxml_use_internal_errors(TRUE);
  $loaded = $document->loadXML($xml);
  libxml_clear_errors();
  libxml_use_internal_errors($previous);
  if (!$loaded) {
    return $xml;
  }
  $xpath = new \DOMXPath($document);
  $changed = FALSE;
  // Every BPMN element that has extension elements.
  $elements = $xpath->query('//*[local-name() = "extensionElements"]/..');
  foreach ($elements as $element) {
    if (!$element instanceof \DOMElement) {
      continue;
    }
    // Only form plugin elements carry the field to rename.
    $properties = $xpath->query('.//*[local-name() = "property" and @name = "pluginid"]', $element);
    if ($properties->length === 0) {
      continue;
    }
    $property = $properties->item(0);
    if (!$property instanceof \DOMElement || !str_starts_with($property->getAttribute('value'), 'form:')) {
      continue;
    }
    $extension_nodes = $xpath->query('*[local-name() = "extensionElements"]', $element);
    if ($extension_nodes->length === 0) {
      continue;
    }
    $extension = $extension_nodes->item(0);
    foreach ($extension->childNodes as $child) {
      if ($child instanceof \DOMElement && $child->localName === 'field' && $child->getAttribute('name') === 'form_id') {
        $child->setAttribute('name', 'form_ids');
        $changed = TRUE;
      }
    }
  }
  return $changed ? $document->saveXML() : $xml;
}

/**
 * Clear ECA process debugger temp store after switch to JSON storage.
 *
 * The process debugger now stores its history as JSON instead of PHP
 * serialized data. Any history already in the temp store is serialized and can
 * no longer be decoded, so it is removed explicitly rather than left as
 * orphaned, undecodable blobs until its TTL expires. The backend-agnostic
 * key-value-expirable API is used so non-database backends (e.g. Redis) are
 * covered too.
 *
 * @see https://www.drupal.org/project/eca/issues/3585744
 */
function eca_post_update_clear_process_debugger_tempstore(): void {
  \Drupal::keyValueExpirable('tempstore.shared.eca_process_debugger')->deleteAll();
  \Drupal::keyValueExpirable('tempstore.private.eca_process_debugger')->deleteAll();
}

/**
 * Hand models coming from ECA 2 over to the Modeler API.
 *
 * In ECA 2, a model consisted of two pieces of configuration: the "eca.eca.*"
 * entity holding the executable model, and an "eca.model.*" entity holding the
 * raw diagram the modeler had drawn. ECA 3 keeps the former and hands
 * everything about the modeler over to the Modeler API, which stores it in the
 * third-party settings of the "eca.eca.*" entity instead.
 *
 * ECA 3.0 shipped this migration and ECA 3.1 dropped it again, which broke the
 * direct upgrade from ECA 2.1 to ECA 3.1. It lives here rather than in eca_ui,
 * where ECA 3.0 had it, because eca_ui is not guaranteed to be enabled and the
 * migration has to happen for every site.
 *
 * The old raw data is read as plain configuration, because the "eca_model"
 * config entity type that used to describe it no longer exists in ECA 3.1. That
 * also means no schema describes those objects any more, so they are deleted
 * once their content has been carried over, rather than left behind as
 * schema-less orphans.
 *
 * Sites that already went through ECA 3.0 are unaffected: the Modeler API is
 * then already recorded as a third-party provider of the model, which is the
 * signal used to skip a model that has been migrated before.
 *
 * @return string
 *   The message about what has been migrated.
 *
 * @throws \Drupal\Core\Utility\UpdateException
 *   When a model cannot be migrated. The raw model data of the models in
 *   question is kept and this update stays pending, so that it can be run again
 *   once the reported cause has been resolved.
 *
 * @see eca_update_8012()
 * @see https://www.drupal.org/project/eca/issues/3590389
 */
function eca_post_update_migrate_to_v3(): string {
  $configFactory = \Drupal::configFactory();

  // Correct the executable configuration before attempting the optional model
  // ownership handover. The ECA model owner belongs to eca_ui, but form events
  // have to keep matching when no UI or modeler is installed. Work on the raw
  // configuration so that saving this correction does not discard legacy ECA
  // 2 properties which are needed when the ownership handover is retried.
  foreach ($configFactory->listAll('eca.eca.') as $name) {
    $config = $configFactory->getEditable($name);
    $events = $config->get('events') ?? [];
    $changed = FALSE;
    foreach ($events as $id => $event) {
      if (isset($event['configuration']['form_id'])) {
        $events[$id]['configuration']['form_ids'] = $event['configuration']['form_id'];
        unset($events[$id]['configuration']['form_id']);
        $changed = TRUE;
      }
    }
    if ($changed) {
      $config->set('events', $events)->save();
    }
  }

  $models = Eca::loadMultiple();
  $pending = [];
  $carriedOver = [];
  foreach ($models as $id => $eca) {
    if (in_array('modeler_api', $eca->getThirdPartyProviders(), TRUE)) {
      // The Modeler API already owns this model, so it came in either as an
      // ECA 3 model or through the equivalent migration of ECA 3.0.
      $carriedOver[] = $id;
    }
    else {
      $pending[$id] = $eca;
    }
  }

  if ($pending !== []) {
    /** @var \Drupal\modeler_api\Plugin\ModelOwnerPluginManager $ownerPluginManager */
    $ownerPluginManager = \Drupal::service('plugin.manager.modeler_api.model_owner');
    $owner = $ownerPluginManager->hasDefinition('eca')
      ? $ownerPluginManager->createInstance('eca')
      : NULL;
    if (!($owner instanceof ModelOwnerInterface)) {
      throw new UpdateException('The Modeler API model owner plugin for ECA is not available, so ECA models can not be migrated. Enable the ECA UI module, which provides that plugin, and run the database updates again.');
    }
    /** @var \Drupal\modeler_api\Plugin\ModelerPluginManager $modelerPluginManager */
    $modelerPluginManager = \Drupal::service('plugin.manager.modeler_api.modeler');
  }

  $prefix = 'eca.model.';
  $migrated = [];
  $unreadable = [];
  $failed = [];
  foreach ($pending as $eca) {
    $rawData = $configFactory->get($prefix . $eca->id());
    $data = $rawData->isNew() ? NULL : $rawData->get('modeldata');
    if (is_string($data) && $data !== '') {
      // Every model with raw data was drawn in BPMN, because that was the only
      // modeler ECA 2 had.
      $modelerId = 'bpmn_io';
      // The presence of the plugin has to be established before asking for an
      // instance of it. The modeler plugin manager of the Modeler API is a
      // fallback plugin manager, so an unknown modeler ID does not fail: it
      // quietly yields the generic "fallback" modeler instead. That one parses
      // nothing, which would leave the model with an empty label and no
      // diagram, and the diagram would then be deleted further down as if it
      // had been carried over.
      $modeler = $modelerPluginManager->hasDefinition($modelerId)
        ? $modelerPluginManager->createInstance($modelerId)
        : NULL;
      if (!($modeler instanceof ModelerInterface)) {
        // Without that modeler the diagram cannot be read, and guessing at its
        // contents would silently degrade the model. Remember it instead, so
        // that the whole update can be repeated once the modeler is available.
        $unreadable[] = $eca->id();
        continue;
      }
      // The form events of ECA 2 configured a single "form_id", which ECA 3
      // turned into a list called "form_ids". The diagram carries its own copy
      // of that configuration, so it has to be renamed there as well, not just
      // in the executable model further down.
      $data = _eca_post_update_rename_diagram_form_id($data);
      $modeler->parseData($owner, $data);
      $owner
        ->setModelerId($eca, $modelerId)
        ->setModelData($eca, $data)
        ->setStatus($eca, $modeler->getStatus())
        ->setChangelog($eca, $modeler->getChangelog())
        ->setLabel($eca, $modeler->getLabel())
        ->setDocumentation($eca, $modeler->getDocumentation())
        ->setTags($eca, $modeler->getTags())
        ->setVersion($eca, $modeler->getVersion());
    }
    else {
      // No diagram was ever stored for this model, so there is nothing for a
      // modeler to render. The Modeler API expresses that with its "fallback"
      // modeler, which presents the model through its own generic interface.
      // The label is the only property worth keeping: it used to be a property
      // of the entity, and is a third-party setting of the Modeler API now.
      $label = $eca->get('label');
      $owner
        ->setModelerId($eca, 'fallback')
        ->setModelData($eca, '')
        ->setChangelog($eca, '')
        ->setLabel($eca, is_string($label) && $label !== '' ? $label : (string) $eca->id())
        ->setDocumentation($eca, '')
        ->setTags($eca, [])
        ->setVersion($eca, '');
    }

    try {
      $eca->save();
    }
    catch (\Throwable) {
      // Saving instantiates every migrated ECA plugin via Eca::preSave(). A
      // legacy model that references a plugin removed in ECA 3.1 therefore
      // throws here. Keep the model out of $carriedOver and remember its id, so
      // this update stays pending and can be run again once the cause has been
      // resolved. The raw model data is preserved by the cleanup below, which
      // only deletes models proven to be owned by the Modeler API.
      $failed[] = $eca->id();
      continue;
    }
    $migrated[] = $eca->id();
    $carriedOver[] = $eca->id();
  }

  // Drop the raw model data of ECA 2. Everything worth keeping has been carried
  // over into the third-party settings by now, and ECA 3.1 no longer ships a
  // schema for these configuration objects. Limit deletion to models proven to
  // be owned by the Modeler API, so an unreadable diagram or an orphan whose
  // ownership cannot be established remains available for recovery.
  foreach ($configFactory->listAll($prefix) as $name) {
    if (in_array(substr($name, strlen($prefix)), $carriedOver, TRUE)) {
      $configFactory->getEditable($name)->delete();
    }
  }

  if ($unreadable !== []) {
    throw new UpdateException(sprintf('The diagrams of the following ECA models can not be read, because the BPMN.iO modeler that drew them is not installed: %s. Install and enable the bpmn_io module, then run the database updates again. The diagrams have been left untouched in the meantime.', implode(', ', $unreadable)));
  }

  if ($failed !== []) {
    throw new UpdateException(sprintf('The following ECA models could not be migrated, because one or more of their plugins is no longer available: %s. Their raw model data has been preserved. Resolve the cause (e.g. ensure every referenced plugin is installed) and run the database updates again.', implode(', ', $failed)));
  }

  return $migrated === []
    ? 'No ECA models needed to be handed over to the Modeler API.'
    : sprintf('Handed %d ECA model(s) over to the Modeler API: %s.', count($migrated), implode(', ', $migrated));
}
