<?php

namespace Drupal\Tests\eca\Kernel\Update;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\eca\Entity\Eca;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the replacement of the ECA Helper "Http Request" action.
 *
 * ECA Helper is not a development dependency of ECA. The post-update works on
 * raw configuration, so the models are written the same way, without the
 * plugins of ECA Helper being available.
 *
 * @see eca_post_update_replace_eca_helper_http_request()
 */
#[Group('eca')]
#[Group('eca_core')]
#[Group('eca_update')]
#[RunTestsInSeparateProcesses]
class MigrateEcaHelperHttpRequestTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * The namespace of the BPMN extension elements.
   */
  private const string CAMUNDA = 'http://camunda.org/schema/1.0/bpmn';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_ui',
    'modeler',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   *
   * The fixtures reference ECA Helper plugins, which have no schema here, and
   * a plugin of ECA Base before that module is enabled. The converted models
   * are checked against the schema explicitly instead.
   */
  protected function getConfigSchemaExclusions(): array {
    return array_merge(parent::getConfigSchemaExclusions(), [
      'eca.eca.helper_embedded',
      'eca.eca.helper_plain',
      'eca.eca.helper_separate',
      'eca.eca.helper_unknown',
      'eca.eca.helper_workflow',
      'eca.eca.unrelated',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    Eca::setTesting();
    parent::setUp();
    $this->installConfig(['system', 'user', 'eca']);
    $this->installEntitySchema('user');
    // Update files are not autoloaded, so pull it in the same way core does
    // before it invokes a post-update function.
    // @see update_invoke_post_update()
    \Drupal::moduleHandler()->loadInclude('eca', 'php', 'eca.post_update');
  }

  /**
   * Tests that models using the ECA Helper action are converted.
   */
  public function testHttpRequestActionsAreReplaced(): void {
    $setValue = [
      'plugin' => 'eca_token_set_value',
      'configuration' => [
        'token_name' => 'unchanged',
        'token_value' => 'value',
        'use_yaml' => FALSE,
        'validate_yaml' => FALSE,
      ],
    ];

    // Basic and digest authentication and the timeout move from the request
    // options to their own fields. The diagram is embedded.
    $basic = [
      'plugin' => 'eca_helper_http_request',
      'configuration' => [
        'method' => 'post',
        'url' => 'https://example.com/api/[node:nid]',
        'data' => "title: '[node:title]'",
        'data_yaml' => 'yes',
        'data_serialization' => 'json',
        'query_parameters' => 'page: 1',
        'headers' => 'Accept: application/json',
        'cookies' => '',
        'request_options' => "timeout: 10\nauth: ['[user:name]', '[site:secret]']",
        'token_name' => 'response',
      ],
    ];
    $digest = [
      'plugin' => 'eca_helper_http_request',
      'configuration' => [
        'method' => 'get',
        'url' => 'https://example.com/digest',
        'data' => '',
        'data_yaml' => 'no',
        'data_serialization' => 'url_encode',
        'query_parameters' => '',
        'headers' => '',
        'cookies' => '',
        'request_options' => "auth:\n  - jane\n  - '[pass]'\n  - Digest\ntimeout: 2.5",
        'token_name' => 'digest_response',
      ],
    ];
    $this->createModel('helper_embedded', ['eca_helper', 'modeler_api'], [
      'http_basic' => $basic,
      'http_digest' => $digest,
      'set_value' => $setValue,
    ], 'embedded');

    // Settings without a safe equivalent are dropped, a JSON body that is only
    // valid after token replacement is reported for review, and another ECA
    // Helper plugin keeps the dependency on ECA Helper. The diagram is stored
    // separately.
    $dropped = [
      'plugin' => 'eca_helper_http_request',
      'configuration' => [
        'method' => 'post',
        'url' => 'https://example.com/upload',
        'data' => "- name: file\n  contents: /tmp/upload.txt",
        'data_yaml' => 'yes',
        'data_serialization' => 'multipart',
        'query_parameters' => '',
        'headers' => '',
        'cookies' => 'session: abc',
        'request_options' => "auth: [user, password, ntlm]\nverify: false\nsink: /tmp/response\ntimeout: '[timeout]'",
        'token_name' => '',
      ],
    ];
    $json = [
      'plugin' => 'eca_helper_http_request',
      'configuration' => [
        'method' => 'put',
        'url' => 'https://example.com/node',
        'data' => '{"nid": [node:nid]}',
        'data_yaml' => 'no',
        'data_serialization' => 'json',
        'query_parameters' => '',
        'headers' => '',
        'cookies' => '',
        'request_options' => '',
        'token_name' => '',
      ],
    ];
    $dumper = [
      'plugin' => 'eca_helper_dumper',
      'configuration' => [],
    ];
    $this->createModel('helper_separate', ['eca_helper', 'modeler_api'], [
      'http_dropped' => $dropped,
      'http_json' => $json,
      'dumper' => $dumper,
    ], 'separate');

    // Without a diagram, only the executable model is converted.
    $invalid = $digest;
    $invalid['configuration']['request_options'] = '{ invalid';
    $this->createModel('helper_plain', ['eca_helper', 'modeler_api'], [
      'http_invalid' => $invalid,
    ], 'none');

    // A Workflow Modeler diagram is rewritten as well.
    $this->createModel('helper_workflow', ['eca_helper', 'modeler_api'], [
      'http_workflow' => $basic,
      'set_value' => $setValue,
    ], 'workflow');

    // A diagram of an unknown modeler can not be rewritten, so the model is
    // left alone rather than having its diagram diverge from the executable
    // model.
    $this->createModel('helper_unknown', ['eca_helper', 'modeler_api'], [
      'http_unknown' => $basic,
    ], 'unknown');

    // A model without the ECA Helper action is not touched at all.
    $this->createModel('unrelated', ['eca_base', 'modeler_api'], [
      'set_value' => $setValue,
    ], 'embedded');

    $storage = \Drupal::service('config.storage');
    $untouched = [
      'eca.eca.helper_unknown' => $storage->read('eca.eca.helper_unknown'),
      'eca.eca.unrelated' => $storage->read('eca.eca.unrelated'),
    ];
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('eca_base'));

    $this->assertSame(
      'Replaced the ECA Helper "Http Request" action with "HTTP: send request" of ECA Base in 4 ECA model(s): helper_embedded, helper_plain, helper_separate, helper_workflow.'
      . ' Settings without a safe equivalent have been dropped, review these models: helper_plain (http_invalid: request_options (invalid YAML)), helper_separate (http_dropped: data (data_serialization: multipart), cookies, request_options.auth (ntlm), request_options.verify, request_options.sink, request_options.timeout (not a positive number)).'
      . ' The request body of the following ECA models needs manual review, because tokens are no longer replaced before it is decoded: helper_separate (http_json: the JSON body is no valid JSON before token replacement).'
      . ' The following ECA models still use the ECA Helper "Http Request" action and need to be converted manually: helper_unknown (its diagram of the "unknown_modeler" modeler can not be updated automatically).',
      eca_post_update_replace_eca_helper_http_request()
    );

    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('eca_base'));
    foreach ($untouched as $name => $data) {
      $this->assertSame($data, $storage->read($name), $name . ' has been changed.');
    }

    // Basic authentication, timeout and the remaining fields.
    $embedded = \Drupal::config('eca.eca.helper_embedded')->getRawData();
    $this->assertSame(['eca_base', 'modeler_api'], $embedded['dependencies']['module']);
    $this->assertSame('eca_http_request', $embedded['actions']['http_basic']['plugin']);
    $this->assertSame([
      'method' => 'post',
      'url' => 'https://example.com/api/[node:nid]',
      'query' => 'page: 1',
      'headers' => 'Accept: application/json',
      'body' => "title: '[node:title]'",
      'use_yaml' => TRUE,
      'validate_yaml' => FALSE,
      'body_format' => 'json',
      'auth_type' => 'basic',
      'auth_username' => '[user:name]',
      'auth_password' => '[site:secret]',
      'timeout' => 10,
      'retries' => 0,
      'max_response_size' => 1048576,
      'token_name' => 'response',
    ], $embedded['actions']['http_basic']['configuration']);
    // Digest authentication, with the case of its name ignored like Guzzle
    // does, and a fractional timeout rounded up to whole seconds.
    $this->assertSame([
      'method' => 'get',
      'url' => 'https://example.com/digest',
      'query' => '',
      'headers' => '',
      'body' => '',
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
      'body_format' => 'form',
      'auth_type' => 'digest',
      'auth_username' => 'jane',
      'auth_password' => '[pass]',
      'timeout' => 3,
      'retries' => 0,
      'max_response_size' => 1048576,
      'token_name' => 'digest_response',
    ], $embedded['actions']['http_digest']['configuration']);
    // Labels, successors and other actions stay as they were. Saving sorts the
    // keys of a rewritten model by its schema, so only the content counts.
    $this->assertSame('Label of http_basic', $embedded['actions']['http_basic']['label']);
    $expected = [
      'configuration' => $setValue['configuration'],
      'label' => 'Label of set_value',
      'plugin' => 'eca_token_set_value',
      'successors' => [],
    ];
    $actual = $embedded['actions']['set_value'];
    ksort($actual);
    $this->assertSame($expected, $actual);

    $xml = $embedded['third_party_settings']['modeler_api']['data'];
    $this->assertDiagramMatches($xml, 'http_basic', 'eca_http_request', $embedded['actions']['http_basic']['configuration']);
    $this->assertDiagramMatches($xml, 'http_digest', 'eca_http_request', $embedded['actions']['http_digest']['configuration']);
    $this->assertDiagramMatches($xml, 'set_value', 'eca_token_set_value', $setValue['configuration']);

    // Dropped settings fall back to the defaults, and the separately stored
    // diagram is updated together with its hash.
    $separate = \Drupal::config('eca.eca.helper_separate')->getRawData();
    $this->assertSame(['eca_base', 'eca_helper', 'modeler_api'], $separate['dependencies']['module']);
    $this->assertSame([
      'method' => 'post',
      'url' => 'https://example.com/upload',
      'query' => '',
      'headers' => '',
      'body' => '',
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
      'body_format' => 'raw',
      'auth_type' => 'none',
      'auth_username' => '',
      'auth_password' => '',
      'timeout' => 30,
      'retries' => 0,
      'max_response_size' => 1048576,
      'token_name' => '',
    ], $separate['actions']['http_dropped']['configuration']);
    // The JSON body that needs a review is carried over unchanged.
    $this->assertSame('{"nid": [node:nid]}', $separate['actions']['http_json']['configuration']['body']);
    $this->assertSame('json', $separate['actions']['http_json']['configuration']['body_format']);
    $this->assertSame('eca_helper_dumper', $separate['actions']['dumper']['plugin']);

    $xml = \Drupal::config('modeler_api.data_model.eca_bpmn_io_helper_separate')->get('data');
    $this->assertSame('hash:' . hash('md5', $xml), $separate['third_party_settings']['modeler_api']['data']);
    $this->assertDiagramMatches($xml, 'http_dropped', 'eca_http_request', $separate['actions']['http_dropped']['configuration']);
    $this->assertDiagramMatches($xml, 'http_json', 'eca_http_request', $separate['actions']['http_json']['configuration']);

    $plain = \Drupal::config('eca.eca.helper_plain')->getRawData();
    $this->assertSame(['eca_base', 'modeler_api'], $plain['dependencies']['module']);
    $this->assertSame('eca_http_request', $plain['actions']['http_invalid']['plugin']);
    $this->assertSame('none', $plain['actions']['http_invalid']['configuration']['auth_type']);
    $this->assertSame(30, $plain['actions']['http_invalid']['configuration']['timeout']);

    // The Workflow Modeler diagram carries the converted configuration, in the
    // same native value types as the executable model.
    $workflow = \Drupal::config('eca.eca.helper_workflow')->getRawData();
    $this->assertSame(['eca_base', 'modeler_api'], $workflow['dependencies']['module']);
    $this->assertSame('eca_http_request', $workflow['actions']['http_workflow']['plugin']);
    $this->assertSame($embedded['actions']['http_basic']['configuration'], $workflow['actions']['http_workflow']['configuration']);
    $json = $workflow['third_party_settings']['modeler_api']['data'];
    $nodes = array_column(json_decode($json, TRUE)['nodes'], NULL, 'id');
    $this->assertSame('eca_http_request', $nodes['http_workflow']['plugin']);
    $this->assertSame($workflow['actions']['http_workflow']['configuration'], $nodes['http_workflow']['configuration']);
    $this->assertSame('Label of http_workflow', $nodes['http_workflow']['label']);
    $this->assertSame(['x' => 100, 'y' => 100], $nodes['http_workflow']['position']);
    $this->assertSame('eca_token_set_value', $nodes['set_value']['plugin']);
    $this->assertSame($setValue['configuration'], $nodes['set_value']['configuration']);

    // The modeler itself reads the new plugin and configuration back out of
    // the rewritten diagram. It leaves out FALSE values, as it always does.
    /** @var \Drupal\modeler_api\Plugin\ModelerPluginManager $modelerPluginManager */
    $modelerPluginManager = \Drupal::service('plugin.manager.modeler_api.modeler');
    $modeler = $modelerPluginManager->createInstance('workflow_modeler');
    $this->assertInstanceOf(ModelerInterface::class, $modeler);
    $modeler->parseData($this->modelOwner(), $json);
    $components = [];
    foreach ($modeler->readComponents() as $component) {
      $components[$component->getId()] = $component;
    }
    $this->assertSame('eca_http_request', $components['http_workflow']->getPluginId());
    $this->assertSame(
      array_filter($workflow['actions']['http_workflow']['configuration'], static fn(mixed $value): bool => $value !== FALSE),
      $components['http_workflow']->getConfiguration()
    );

    $typedConfig = \Drupal::service('config.typed');
    foreach (['eca.eca.helper_embedded', 'eca.eca.helper_plain', 'eca.eca.helper_workflow'] as $name) {
      $this->assertConfigSchema($typedConfig, $name, \Drupal::config($name)->getRawData());
      $this->assertConvertedConfigIsValid($name);
    }
  }

  /**
   * Tests that nothing happens when no model uses the ECA Helper action.
   */
  public function testNothingToReplace(): void {
    $this->createModel('unrelated', ['eca_base', 'modeler_api'], [
      'set_value' => [
        'plugin' => 'eca_token_set_value',
        'configuration' => [
          'token_name' => 'unchanged',
          'token_value' => 'value',
          'use_yaml' => FALSE,
          'validate_yaml' => FALSE,
        ],
      ],
    ], 'embedded');
    $storage = \Drupal::service('config.storage');
    $before = $storage->read('eca.eca.unrelated');

    $this->assertSame(
      'No ECA model used the ECA Helper "Http Request" action.',
      eca_post_update_replace_eca_helper_http_request()
    );
    $this->assertSame($before, $storage->read('eca.eca.unrelated'));
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('eca_base'));
  }

  /**
   * Writes a model straight into the configuration.
   *
   * @param string $id
   *   The ID of the model.
   * @param string[] $modules
   *   The module dependencies of the model.
   * @param array $actions
   *   The actions, each with its plugin and configuration, keyed by ID.
   * @param string $diagram
   *   How the diagram is stored: "embedded" or "separate" for a BPMN diagram,
   *   "workflow" for a diagram of the Workflow Modeler, "unknown" for a
   *   diagram of an unknown modeler, "none" for none.
   */
  private function createModel(string $id, array $modules, array $actions, string $diagram): void {
    $components = [];
    $tasks = '';
    foreach ($actions as $actionId => $action) {
      $components[$actionId] = [
        'plugin' => $action['plugin'],
        'label' => 'Label of ' . $actionId,
        'configuration' => $action['configuration'],
        'successors' => [],
      ];
      $tasks .= $this->diagramTask($actionId, $action['plugin'], $action['configuration']);
    }
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" xmlns:camunda="http://camunda.org/schema/1.0/bpmn" id="Definitions_1">
  <bpmn:process id="$id" isExecutable="true">$tasks
  </bpmn:process>
</bpmn:definitions>
XML;
    $settings = [
      'modeler_id' => 'bpmn_io',
      'label' => 'Model ' . $id,
    ];
    switch ($diagram) {
      case 'embedded':
        $settings['data'] = $xml;
        break;

      case 'separate':
        $settings['data'] = 'hash:' . hash('md5', $xml);
        \Drupal::configFactory()->getEditable('modeler_api.data_model.eca_bpmn_io_' . $id)->setData([
          'uuid' => \Drupal::service('uuid')->generate(),
          'langcode' => 'en',
          'status' => TRUE,
          'dependencies' => [],
          'id' => 'eca_bpmn_io_' . $id,
          'data' => $xml,
        ])->save();
        break;

      case 'workflow':
        // The way the Workflow Modeler builds its graph from a model.
        // @see \Drupal\modeler\Plugin\ModelerApiModeler\WorkflowModeler::buildGraph()
        $nodes = [];
        foreach ($components as $componentId => $component) {
          $nodes[] = [
            'id' => $componentId,
            'plugin' => $component['plugin'],
            'label' => $component['label'],
            'configuration' => $component['configuration'],
            'componentType' => Api::COMPONENT_TYPE_ELEMENT,
            'configured' => TRUE,
            'position' => ['x' => 100, 'y' => 100],
          ];
        }
        $settings['modeler_id'] = 'workflow_modeler';
        $settings['data'] = json_encode([
          'id' => $id,
          'metadata' => [
            'version' => '1.0.0',
            'label' => 'Model ' . $id,
            'executable' => TRUE,
          ],
          'nodes' => $nodes,
          'edges' => [],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        break;

      case 'unknown':
        $settings['modeler_id'] = 'unknown_modeler';
        $settings['data'] = '{"nodes":[{"id":"http_unknown","plugin":"eca_helper_http_request"}]}';
        break;

      default:
        $settings['modeler_id'] = 'fallback';
    }

    \Drupal::configFactory()->getEditable('eca.eca.' . $id)->setData([
      'uuid' => \Drupal::service('uuid')->generate(),
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [
        'module' => $modules,
      ],
      'third_party_settings' => [
        'modeler_api' => $settings,
      ],
      'id' => $id,
      'weight' => 0,
      'template' => FALSE,
      'events' => [],
      'conditions' => [],
      'gateways' => [],
      'actions' => $components,
    ])->save();
  }

  /**
   * Returns a BPMN task the way the BPMN.iO modeler writes it.
   *
   * @param string $id
   *   The ID of the task.
   * @param string $pluginId
   *   The ID of the action plugin.
   * @param array $configuration
   *   The configuration of the action.
   *
   * @return string
   *   The task as XML.
   */
  private function diagramTask(string $id, string $pluginId, array $configuration): string {
    $fields = '';
    foreach ($this->encodeFields($configuration) as $name => $value) {
      $value = htmlspecialchars($value, ENT_XML1);
      $fields .= "\n        <camunda:field name=\"$name\">\n          <camunda:string>$value</camunda:string>\n        </camunda:field>";
    }
    return <<<XML

    <bpmn:task id="$id" name="Label of $id" camunda:modelerTemplate="org.drupal.action.$pluginId">
      <bpmn:extensionElements>
        <camunda:properties>
          <camunda:property name="pluginid" value="$pluginId" />
        </camunda:properties>$fields
      </bpmn:extensionElements>
    </bpmn:task>
XML;
  }

  /**
   * Encodes a configuration the way the BPMN.iO modeler stores its fields.
   *
   * @param array $configuration
   *   The configuration.
   *
   * @return string[]
   *   The field values, keyed by field name.
   */
  private function encodeFields(array $configuration): array {
    $fields = [];
    foreach ($configuration as $key => $value) {
      $fields[$key] = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
    }
    return $fields;
  }

  /**
   * Asserts that a task in a diagram matches its executable action.
   *
   * @param string $xml
   *   The BPMN diagram.
   * @param string $id
   *   The ID of the task.
   * @param string $pluginId
   *   The expected plugin ID.
   * @param array $configuration
   *   The configuration of the action in the executable model.
   */
  private function assertDiagramMatches(string $xml, string $id, string $pluginId, array $configuration): void {
    $document = new \DOMDocument();
    $this->assertTrue($document->loadXML($xml));
    $xpath = new \DOMXPath($document);
    $xpath->registerNamespace('camunda', self::CAMUNDA);
    $task = $xpath->query('//*[@id="' . $id . '"]')->item(0);
    $this->assertInstanceOf(\DOMElement::class, $task);
    $this->assertSame('org.drupal.action.' . $pluginId, $task->getAttributeNS(self::CAMUNDA, 'modelerTemplate'));
    $properties = $xpath->query('*[local-name() = "extensionElements"]/camunda:properties/camunda:property[@name = "pluginid"]', $task);
    $this->assertSame(1, $properties->length);
    $this->assertSame($pluginId, $properties->item(0)->getAttribute('value'));
    $fields = [];
    foreach ($xpath->query('*[local-name() = "extensionElements"]/camunda:field', $task) as $field) {
      $fields[$field->getAttribute('name')] = $xpath->evaluate('string(camunda:string)', $field);
    }
    $this->assertSame($this->encodeFields($configuration), $fields, 'The fields of ' . $id . ' do not match its configuration.');
  }

  /**
   * Asserts that a converted model passes configuration validation.
   *
   * @param string $name
   *   The name of the configuration object.
   */
  private function assertConvertedConfigIsValid(string $name): void {
    $violations = \Drupal::service('config.typed')->createFromNameAndData(
      $name,
      \Drupal::config($name)->getRawData()
    )->validate();
    $messages = [];
    foreach ($violations as $violation) {
      $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
    }
    $this->assertSame([], $messages, 'Converted configuration ' . $name . ' violates the schema.');
  }

  /**
   * Returns the Modeler API model owner plugin for ECA.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface
   *   The model owner plugin.
   */
  private function modelOwner(): ModelOwnerInterface {
    /** @var \Drupal\modeler_api\Plugin\ModelOwnerPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.modeler_api.model_owner');
    $owner = $manager->createInstance('eca');
    $this->assertInstanceOf(ModelOwnerInterface::class, $owner);
    return $owner;
  }

}
