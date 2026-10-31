<?php

/**
 * @file
 * Contains post_update hooks for eca.
 */

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
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
 * @see https://git.drupalcode.org/project/eca/-/work_items/3590389
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

/**
 * Drops the translated copies of the technical identifiers of ECA models.
 *
 * Token names, forwarded token lists and cache tags used to be typed as
 * "label" or "text", which offered them for translation. A translator who
 * edited one of them there wrote a per-language copy that silently changed
 * what the model does in that language. The schema no longer offers those
 * keys, but an override written while it did is still stored and still wins at
 * runtime, so it is removed here and the model falls back to its source value.
 *
 * Language overrides live in the "language.<langcode>" collections of the
 * active configuration storage, which is read directly: an override left
 * behind by a language that has since been removed has to be cleaned up too.
 * Only the affected keys are unset, so translated labels of the same model
 * survive.
 *
 * @return string
 *   The message about which language overrides have been corrected.
 *
 * @see https://git.drupalcode.org/project/eca/-/work_items/3590455
 */
function eca_post_update_drop_translated_technical_identifiers(): string {
  $identifiers = [
    'token_name',
    'result_token_name',
    'token_mime_type',
    'tokens',
    'tags',
  ];
  $storage = \Drupal::service('config.storage');
  $corrected = [];

  foreach ($storage->getAllCollectionNames() as $collection) {
    if (!str_starts_with($collection, 'language.')) {
      continue;
    }
    $overrides = $storage->createCollection($collection);
    foreach ($overrides->listAll('eca.eca.') as $name) {
      $data = $overrides->read($name);
      $changed = FALSE;
      foreach (['events', 'conditions', 'actions'] as $componentType) {
        foreach (array_keys($data[$componentType] ?? []) as $id) {
          foreach ($identifiers as $identifier) {
            if (array_key_exists($identifier, $data[$componentType][$id]['configuration'] ?? [])) {
              unset($data[$componentType][$id]['configuration'][$identifier]);
              $changed = TRUE;
            }
          }
          // Leave no empty remains behind: an override that only carried a
          // technical identifier has nothing left to say about the model.
          if (($data[$componentType][$id]['configuration'] ?? NULL) === []) {
            unset($data[$componentType][$id]['configuration']);
          }
          if ($data[$componentType][$id] === []) {
            unset($data[$componentType][$id]);
          }
        }
        if (($data[$componentType] ?? NULL) === []) {
          unset($data[$componentType]);
        }
      }
      if (!$changed) {
        continue;
      }
      if ($data === []) {
        $overrides->delete($name);
      }
      else {
        $overrides->write($name, $data);
      }
      $corrected[] = $collection . ':' . $name;
    }
  }

  if ($corrected !== []) {
    \Drupal::configFactory()->reset();
  }

  return $corrected === []
    ? 'No translated ECA model contained a technical identifier.'
    : sprintf('Removed technical identifiers from %d translated ECA model(s): %s.', count($corrected), implode(', ', $corrected));
}

/**
 * Maps the configuration of an ECA Helper "Http Request" action.
 *
 * Every key of the "eca_http_request" action is filled, so that the result is
 * complete without the plugin defaults, which may not even be available while
 * the update runs.
 *
 * The Guzzle request options of ECA Helper are read as plain YAML without any
 * token replacement. Only the timeout and a basic or digest authentication have
 * an equivalent, everything else is left out. The same applies to cookies and
 * multipart uploads. Only the names of the settings left out are reported,
 * never their values, which may contain credentials.
 *
 * Some bodies are carried over unchanged but may no longer work: the new
 * action decodes a JSON body before replacing tokens, and sends a form body
 * only from a YAML mapping, while ECA Helper replaced tokens in the raw data
 * first. Those are reported for review.
 *
 * @param array $old
 *   The configuration of the "eca_helper_http_request" action.
 * @param string[] $dropped
 *   Receives the names of the settings that have been left out.
 * @param string[] $review
 *   Receives the reasons why the converted action needs a manual review.
 *
 * @return array
 *   The configuration of the "eca_http_request" action.
 */
function _eca_post_update_http_request_configuration(array $old, array &$dropped, array &$review): array {
  $string = static fn(mixed $value): string => is_scalar($value) ? (string) $value : '';
  $dataYaml = $old['data_yaml'] ?? 'no';
  $configuration = [
    'method' => $string($old['method'] ?? 'get'),
    'url' => $string($old['url'] ?? ''),
    'query' => $string($old['query_parameters'] ?? ''),
    'headers' => $string($old['headers'] ?? ''),
    'body' => $string($old['data'] ?? ''),
    // The same check that ECA Helper applies at runtime.
    'use_yaml' => $dataYaml === 'yes' || (is_numeric($dataYaml) && (int) $dataYaml === 1),
    'validate_yaml' => FALSE,
    'body_format' => 'raw',
    'auth_type' => 'none',
    'auth_username' => '',
    'auth_password' => '',
    'timeout' => 30,
    'retries' => 0,
    'max_response_size' => 1048576,
    'token_name' => $string($old['token_name'] ?? ''),
  ];

  $formats = [
    'raw' => 'raw',
    'json' => 'json',
    'url_encode' => 'form',
  ];
  $serialization = $old['data_serialization'] ?? 'raw';
  if (is_string($serialization) && isset($formats[$serialization])) {
    $configuration['body_format'] = $formats[$serialization];
  }
  else {
    // A multipart upload has no equivalent, and ECA Helper sent no body at all
    // for a serialization it did not know. Sending the data as a raw body
    // instead would produce a different request.
    $configuration['body'] = '';
    $configuration['use_yaml'] = FALSE;
    $dropped[] = 'data (data_serialization: ' . $string($serialization) . ')';
  }

  if (!$configuration['use_yaml'] && trim($configuration['body']) !== '') {
    if ($configuration['body_format'] === 'json' && !is_array(Json::decode($configuration['body']))) {
      // E.g. unquoted tokens like {"n": [node:nid]}, which only became valid
      // JSON after ECA Helper had replaced them.
      $review[] = 'the JSON body is no valid JSON before token replacement';
    }
    elseif ($configuration['body_format'] === 'form') {
      $review[] = 'a form body needs YAML enabled and a YAML mapping';
    }
  }

  if (trim($string($old['cookies'] ?? '')) !== '') {
    $dropped[] = 'cookies';
  }

  $requestOptions = $string($old['request_options'] ?? '');
  if (trim($requestOptions) === '') {
    return $configuration;
  }
  try {
    $options = Yaml::decode($requestOptions);
  }
  catch (InvalidDataTypeException) {
    $dropped[] = 'request_options (invalid YAML)';
    return $configuration;
  }
  if ($options === NULL) {
    return $configuration;
  }
  if (!is_array($options)) {
    $dropped[] = 'request_options (not a YAML mapping)';
    return $configuration;
  }
  foreach ($options as $key => $value) {
    if ($key === 'timeout') {
      if ((is_int($value) || is_float($value)) && $value > 0) {
        // Guzzle accepts fractions of a second, the new action whole seconds.
        // Rounding up never cuts a request short that used to succeed.
        $configuration['timeout'] = (int) ceil($value);
      }
      else {
        // A token, or 0, which means no limit in Guzzle and has no
        // equivalent in the new action.
        $dropped[] = 'request_options.timeout (not a positive number)';
      }
      continue;
    }
    if ($key === 'auth') {
      if ($value === NULL) {
        // Guzzle sends no authentication for NULL either.
        continue;
      }
      $valid = is_array($value)
        && array_is_list($value)
        && in_array(count($value), [2, 3], TRUE)
        && (is_scalar($value[0]) || $value[0] === NULL)
        && (is_scalar($value[1]) || $value[1] === NULL);
      // Guzzle defaults to basic authentication and ignores the case.
      // @see \GuzzleHttp\Client::applyOptions()
      $type = $valid && isset($value[2]) ? strtolower($string($value[2])) : 'basic';
      if ($valid && in_array($type, ['basic', 'digest'], TRUE)) {
        // Token strings carry over unchanged, the new fields accept tokens.
        $configuration['auth_type'] = $type;
        $configuration['auth_username'] = $string($value[0]);
        $configuration['auth_password'] = $string($value[1]);
      }
      elseif ($valid && $type === 'ntlm') {
        $dropped[] = 'request_options.auth (ntlm)';
      }
      else {
        $dropped[] = 'request_options.auth';
      }
      continue;
    }
    $dropped[] = 'request_options.' . $key;
  }
  return $configuration;
}

/**
 * Replaces ECA Helper "Http Request" tasks in a BPMN diagram.
 *
 * The diagram carries its own copy of the plugin ID and of the configuration
 * of every action. Each task whose pluginid property identifies the ECA Helper
 * action gets the plugin ID and element template of the new action, and its
 * fields are replaced with the configuration of the executable model, encoded
 * the way the BPMN.iO modeler writes them.
 *
 * @param string $xml
 *   The raw BPMN diagram.
 * @param array $actions
 *   The new configuration of the converted actions, keyed by action ID.
 *
 * @return string|null
 *   The rewritten diagram, or NULL when it could not be parsed or does not
 *   contain exactly the converted actions.
 *
 * @see \Drupal\bpmn_io\Service\PrepareComponents::properties()
 * @see \Drupal\bpmn_io\Service\PrepareComponents::sanitizeConfigValue()
 */
function _eca_post_update_rewrite_http_request_bpmn(string $xml, array $actions): ?string {
  $document = new \DOMDocument();
  $previous = libxml_use_internal_errors(TRUE);
  $loaded = $document->loadXML($xml);
  libxml_clear_errors();
  libxml_use_internal_errors($previous);
  if (!$loaded) {
    return NULL;
  }
  $xpath = new \DOMXPath($document);
  $rewritten = [];
  $properties = $xpath->query('//*[local-name() = "extensionElements"]/*[local-name() = "properties"]/*[local-name() = "property" and @name = "pluginid" and @value = "eca_helper_http_request"]');
  foreach ($properties as $property) {
    $extension = $property->parentNode?->parentNode;
    $element = $extension?->parentNode;
    if (!$property instanceof \DOMElement || !$extension instanceof \DOMElement || !$element instanceof \DOMElement) {
      return NULL;
    }
    $id = $element->getAttribute('id');
    // A task the executable model does not know about, or knows under another
    // plugin, means that diagram and model already disagree.
    if (!isset($actions[$id]) || isset($rewritten[$id])) {
      return NULL;
    }
    $rewritten[$id] = TRUE;

    $property->setAttribute('value', 'eca_http_request');
    foreach ($element->attributes as $attribute) {
      if ($attribute->localName === 'modelerTemplate' && $attribute->value === 'org.drupal.action.eca_helper_http_request') {
        $attribute->value = 'org.drupal.action.eca_http_request';
      }
    }

    $fields = [];
    foreach ($extension->childNodes as $child) {
      if ($child instanceof \DOMElement && $child->localName === 'field') {
        $fields[] = $child;
      }
    }
    foreach ($fields as $field) {
      $extension->removeChild($field);
    }
    $prefix = $property->prefix === '' ? '' : $property->prefix . ':';
    foreach ($actions[$id] as $key => $value) {
      $field = $document->createElementNS($property->namespaceURI, $prefix . 'field');
      $field->setAttribute('name', $key);
      $string = $document->createElementNS($property->namespaceURI, $prefix . 'string');
      $string->appendChild($document->createTextNode(is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value));
      $field->appendChild($string);
      $extension->appendChild($field);
    }
  }
  if (count($rewritten) !== count($actions)) {
    return NULL;
  }
  return $document->saveXML();
}

/**
 * Replaces ECA Helper "Http Request" nodes in a Workflow Modeler diagram.
 *
 * The Workflow Modeler stores its diagram as JSON, with a copy of the plugin
 * ID and of the configuration of every action in its nodes. That copy is read
 * back into the executable model, e.g. when a model gets enabled or disabled,
 * so each node of the ECA Helper action gets the plugin ID of the new action
 * and the configuration of the executable model, in the same native value
 * types the modeler writes there itself. The JSON is encoded the same way the
 * modeler re-encodes it.
 *
 * @param string $json
 *   The raw Workflow Modeler diagram.
 * @param array $actions
 *   The new configuration of the converted actions, keyed by action ID.
 *
 * @return string|null
 *   The rewritten diagram, or NULL when it could not be parsed or does not
 *   contain exactly the converted actions.
 *
 * @see \Drupal\modeler\Plugin\ModelerApiModeler\WorkflowModeler::buildGraph()
 * @see \Drupal\modeler\Plugin\ModelerApiModeler\WorkflowModeler::parseData()
 * @see \Drupal\modeler\Plugin\ModelerApiModeler\WorkflowModeler::rebuildRawData()
 */
function _eca_post_update_rewrite_http_request_workflow(string $json, array $actions): ?string {
  try {
    $decoded = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
  }
  catch (\JsonException) {
    return NULL;
  }
  if (!is_array($decoded) || !is_array($decoded['nodes'] ?? NULL)) {
    return NULL;
  }
  $rewritten = [];
  foreach ($decoded['nodes'] as $index => $node) {
    if (!is_array($node) || ($node['plugin'] ?? NULL) !== 'eca_helper_http_request') {
      continue;
    }
    $id = (string) ($node['id'] ?? '');
    // A node the executable model does not know about, or knows under another
    // plugin, means that diagram and model already disagree.
    if (!isset($actions[$id]) || isset($rewritten[$id])) {
      return NULL;
    }
    $rewritten[$id] = TRUE;
    $decoded['nodes'][$index]['plugin'] = 'eca_http_request';
    $decoded['nodes'][$index]['configuration'] = $actions[$id];
  }
  if (count($rewritten) !== count($actions)) {
    return NULL;
  }
  return json_encode($decoded, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
}

/**
 * Replaces the ECA Helper "Http Request" action with "HTTP: send request".
 *
 * The "eca_helper_http_request" action of ECA Helper is unsupported and lets
 * token data change the structure of a request. ECA Base provides the
 * "eca_http_request" action as its secure replacement. Every model using the
 * old action is rewritten to the new one, in the executable model as well as
 * in its diagram, and ECA Base is enabled when needed.
 *
 * This lives in ECA rather than in ECA Helper, because it has to run on every
 * site, and it works on raw configuration, so that it runs whether ECA Helper
 * is still installed or not. Settings without a safe equivalent are dropped
 * and reported per model.
 *
 * Post-update functions run in alphabetical order. The name of this function
 * sorts after eca_post_update_migrate_to_v3(), which hands the diagrams of
 * ECA 2 models over to the Modeler API first, so that they are found here.
 *
 * Diagrams of the BPMN.iO modeler and of the Workflow Modeler are rewritten,
 * whether stored with the model or separately. A model whose diagram cannot
 * be rewritten keeps the old action, so that its executable model and its
 * diagram never diverge. It is reported instead, to be replaced manually.
 *
 * @return string
 *   The message about which models have been converted, which settings have
 *   been dropped, which request bodies need a review, and which models need
 *   to be converted manually.
 *
 * @see https://git.drupalcode.org/project/eca/-/work_items/3590473
 */
function eca_post_update_replace_eca_helper_http_request(): string {
  $configFactory = \Drupal::configFactory();
  $converted = [];
  $diagrams = [];
  $dropped = [];
  $manual = [];
  $review = [];

  foreach ($configFactory->listAll('eca.eca.') as $name) {
    // Read the configuration without overrides.
    $data = $configFactory->getEditable($name)->getRawData();
    $id = (string) ($data['id'] ?? substr($name, strlen('eca.eca.')));
    $actions = [];
    $modelDropped = [];
    $modelReview = [];
    foreach ($data['actions'] ?? [] as $actionId => $action) {
      if (($action['plugin'] ?? NULL) !== 'eca_helper_http_request') {
        continue;
      }
      $actionDropped = [];
      $actionReview = [];
      $actions[$actionId] = _eca_post_update_http_request_configuration(is_array($action['configuration'] ?? NULL) ? $action['configuration'] : [], $actionDropped, $actionReview);
      $data['actions'][$actionId]['plugin'] = 'eca_http_request';
      $data['actions'][$actionId]['configuration'] = $actions[$actionId];
      if ($actionDropped !== []) {
        $modelDropped[] = $actionId . ': ' . implode(', ', $actionDropped);
      }
      if ($actionReview !== []) {
        $modelReview[] = $actionId . ': ' . implode(', ', $actionReview);
      }
    }
    if ($actions === []) {
      continue;
    }

    // The diagram is kept by the Modeler API, either in its third-party
    // settings or, referenced by its hash, in a separate configuration object.
    // @see \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase::getModelData()
    // @see \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase::storageId()
    $diagram = $data['third_party_settings']['modeler_api']['data'] ?? '';
    if ($diagram !== '') {
      $modelerId = $data['third_party_settings']['modeler_api']['modeler_id'] ?? 'fallback';
      $rewrite = match ($modelerId) {
        'bpmn_io' => _eca_post_update_rewrite_http_request_bpmn(...),
        'workflow_modeler' => _eca_post_update_rewrite_http_request_workflow(...),
        default => NULL,
      };
      if ($rewrite === NULL || !is_string($diagram)) {
        $manual[$id] = sprintf('its diagram of the "%s" modeler can not be updated automatically', $modelerId);
        continue;
      }
      $dataModelName = NULL;
      $raw = $diagram;
      if (strlen($diagram) === 37 && str_starts_with($diagram, 'hash:')) {
        $dataModelName = 'modeler_api.data_model.' . implode('_', ['eca', $modelerId, $id]);
        $raw = $configFactory->getEditable($dataModelName)->get('data');
        if (!is_string($raw) || !hash_equals(substr($diagram, 5), hash('md5', $raw))) {
          $manual[$id] = 'its separately stored diagram is missing or does not match';
          continue;
        }
      }
      $raw = $rewrite($raw, $actions);
      if ($raw === NULL) {
        $manual[$id] = 'its diagram could not be read or does not match the executable model';
        continue;
      }
      if ($dataModelName === NULL) {
        $data['third_party_settings']['modeler_api']['data'] = $raw;
      }
      else {
        $data['third_party_settings']['modeler_api']['data'] = 'hash:' . hash('md5', $raw);
        $diagrams[$dataModelName] = $raw;
      }
    }

    // Keep the module dependencies in line with what the model would calculate
    // from the providers of its plugins. ECA Helper is only dropped once none
    // of its plugins remain. Its plugin IDs all start with its module name,
    // also the derived event plugins.
    // @see \Drupal\eca\Entity\Eca::getPluginCollections()
    $modules = $data['dependencies']['module'] ?? [];
    $modules[] = 'eca_base';
    $helperRemains = FALSE;
    foreach (['events', 'conditions', 'actions'] as $componentType) {
      foreach ($data[$componentType] ?? [] as $component) {
        $base = explode(':', (string) ($component['plugin'] ?? ''))[0];
        if ($base === 'eca_helper' || str_starts_with($base, 'eca_helper_')) {
          $helperRemains = TRUE;
        }
      }
    }
    if (!$helperRemains) {
      $modules = array_diff($modules, ['eca_helper']);
    }
    $modules = array_values(array_unique($modules));
    sort($modules);
    $data['dependencies']['module'] = $modules;
    ksort($data['dependencies']);

    $converted[$name] = $data;
    if ($modelDropped !== []) {
      $dropped[$id] = $modelDropped;
    }
    if ($modelReview !== []) {
      $review[$id] = $modelReview;
    }
  }

  if ($converted === [] && $manual === []) {
    return 'No ECA model used the ECA Helper "Http Request" action.';
  }

  if ($converted !== [] && !\Drupal::moduleHandler()->moduleExists('eca_base')) {
    \Drupal::service('module_installer')->install(['eca_base']);
    // Installing a module rebuilds the container.
    $configFactory = \Drupal::configFactory();
  }
  foreach ($diagrams as $name => $xml) {
    $configFactory->getEditable($name)->set('data', $xml)->save();
  }
  $convertedIds = [];
  foreach ($converted as $name => $data) {
    $configFactory->getEditable($name)->setData($data)->save();
    $convertedIds[] = $data['id'] ?? substr($name, strlen('eca.eca.'));
  }

  $messages = [];
  if ($convertedIds !== []) {
    $messages[] = sprintf('Replaced the ECA Helper "Http Request" action with "HTTP: send request" of ECA Base in %d ECA model(s): %s.', count($convertedIds), implode(', ', $convertedIds));
  }
  if ($dropped !== []) {
    $models = [];
    foreach ($dropped as $id => $entries) {
      $models[] = $id . ' (' . implode('; ', $entries) . ')';
    }
    $messages[] = sprintf('Settings without a safe equivalent have been dropped, review these models: %s.', implode(', ', $models));
  }
  if ($review !== []) {
    $models = [];
    foreach ($review as $id => $entries) {
      $models[] = $id . ' (' . implode('; ', $entries) . ')';
    }
    $messages[] = sprintf('The request body of the following ECA models needs manual review, because tokens are no longer replaced before it is decoded: %s.', implode(', ', $models));
  }
  if ($manual !== []) {
    $models = [];
    foreach ($manual as $id => $reason) {
      $models[] = $id . ' (' . $reason . ')';
    }
    $messages[] = sprintf('The following ECA models still use the ECA Helper "Http Request" action and need to be converted manually: %s.', implode(', ', $models));
  }
  return implode(' ', $messages);
}
