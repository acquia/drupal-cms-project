<?php

namespace Drupal\Tests\eca_development\Unit;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\eca_development\Drush\Commands\DocsCommands;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\ArrayLoader as TwigLoader;

/**
 * Tests the ECA documentation commands.
 */
#[Group('eca')]
#[Group('eca_development')]
class DocsCommandsTest extends UnitTestCase {

  /**
   * Relative path of the library page template from this test.
   */
  private const string LIBRARY_TEMPLATE = __DIR__ . '/../../../templates/docs/library.md.twig';

  /**
   * Relative path of the provider index page template from this test.
   */
  private const string PROVIDER_TEMPLATE = __DIR__ . '/../../../templates/docs/provider.md.twig';

  /**
   * Tests conversion of current and legacy navigation structures.
   *
   * @param array $navigation
   *   The decoded navigation structure.
   * @param array $expected
   *   The expected internal TOC structure.
   */
  #[DataProvider('navigationProvider')]
  public function testNavToToc(array $navigation, array $expected): void {
    $reflection = new \ReflectionClass(DocsCommands::class);
    $command = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('navToToc');

    $this->assertSame($expected, $method->invoke($command, $navigation, TRUE));
  }

  /**
   * Tests append-only merging and the established library TOC format.
   */
  public function testLibraryTocSerialization(): void {
    $reflection = new \ReflectionClass(DocsCommands::class);
    $command = $reflection->newInstanceWithoutConstructor();
    $navigation = [
      0 => 'library/index.md',
      '0-ECA' => NULL,
      'forms' => [
        'Existing model' => 'library/forms/existing_model.md',
      ],
      'simple' => [
        'Updated model' => 'library/simple/old_path.md',
      ],
    ];
    $toc = [
      'forms' => [
        'New model' => 'library/forms/new_model.md',
      ],
      'simple' => [
        'Updated model' => 'library/simple/updated_model.md',
      ],
    ];

    $existing = $reflection->getMethod('navToToc')->invoke($command, $navigation, TRUE);
    $reflection->getMethod('mergeTocAppendOnly')->invokeArgs($command, [&$toc, $existing]);
    $reflection->getMethod('sortNestedArrayAssoc')->invokeArgs($command, [&$toc]);
    $reflection->getProperty('toc')->setValue($command, $toc);
    $serialized = $reflection->getMethod('serializeToc')->invoke($command, 'library');

    $this->assertSame(<<<'YAML'
0: library/index.md
forms:
  'Existing model': library/forms/existing_model.md
  'New model': library/forms/new_model.md
simple:
  'Updated model': library/simple/updated_model.md

YAML, $serialized);
  }

  /**
   * Tests that each viewer binding follows the artifact that was written.
   *
   * The standalone viewer shows a placeholder until it has loaded the file it
   * was bound to, so a page advertising an artifact that ::modelDoc() did not
   * write never finishes loading. Both bindings are therefore emitted only
   * for a file that is actually there, which is what "has_json" and
   * "has_bpmn" report.
   *
   * @param bool $hasJson
   *   Whether a Workflow Modeler graph was published beside the page.
   * @param bool $hasBpmn
   *   Whether a stored BPMN diagram was published beside the page.
   */
  #[DataProvider('viewerBindingProvider')]
  public function testLibraryViewerBindings(bool $hasJson, bool $hasBpmn): void {
    $reflection = new \ReflectionClass(DocsCommands::class);
    $command = $reflection->newInstanceWithoutConstructor();
    $loader = new TwigLoader();
    $reflection->getProperty('twigLoader')->setValue($command, $loader);
    $reflection->getProperty('twigEnvironment')->setValue($command, new TwigEnvironment($loader));

    $rendered = $reflection->getMethod('render')->invoke($command, self::LIBRARY_TEMPLATE, [
      'rawid' => 'eca_lib_0001',
      'id' => 'unpublish_expired_content',
      'label' => 'Unpublish expired content',
      'version' => '1.0.0',
      'changelog' => '',
      'main_tag' => 'content',
      'tags' => ['eca-library', 'content'],
      'documentation' => 'What this model does.',
      'events' => [],
      'conditions' => [],
      'actions' => [],
      'dependencies' => [],
      'model_filename' => 'workflow_modeler-eca_lib_0001',
      'library_path' => 'library/content',
      'namespace' => DocsCommands::NAMESPACE,
      'has_bpmn' => $hasBpmn,
      'has_json' => $hasJson,
    ]);

    // A failed render returns an empty string, which would satisfy every
    // "does not contain" assertion below without proving anything.
    $this->assertStringContainsString('# Unpublish expired content', $rendered);

    $jsonBinding = 'jsonurl="eca_lib_0001.json"';
    $bpmnBinding = "url='workflow_modeler-eca_lib_0001.xml'";
    if ($hasJson) {
      $this->assertStringContainsString($jsonBinding, $rendered);
    }
    else {
      $this->assertStringNotContainsString($jsonBinding, $rendered);
    }
    if ($hasBpmn) {
      $this->assertStringContainsString($bpmnBinding, $rendered);
    }
    else {
      $this->assertStringNotContainsString($bpmnBinding, $rendered);
    }
  }

  /**
   * Tests that a plugin page's front matter round-trips its own metadata.
   *
   * The generated pages are the only place the machine-readable plugin
   * metadata exists, so a consumer that cannot parse a page loses that plugin
   * silently. Everything that makes the block survive is asserted here:
   * a label containing a double quote, significant leading and trailing
   * whitespace, a ": " sequence, a NULL default staying NULL rather than an
   * empty string, an integer option value staying an integer, an empty-string
   * option value, and an empty option list staying a sequence.
   */
  public function testFrontMatterRoundTrip(): void {
    $reflection = new \ReflectionClass(DocsCommands::class);
    $command = $reflection->newInstanceWithoutConstructor();
    // This double is a stub because the test never verifies interactions with
    // it. A mock without expectations makes PHPUnit 12.5 emit a notice that
    // failOnPhpunitNotice escalates to a failure in core's next major.
    $plugin = $this->createStub(PluginInspectionInterface::class);
    $plugin->method('getPluginId')->willReturn('eca_test_hostile');

    // A label with a double quote in it is the case the previous hand-written
    // "title" line survived only because no plugin happens to ship one.
    $label = 'A "quoted" label';
    $formFields = [
      [
        'name' => 'token_name',
        'label' => 'Token name',
        // The leading space is significant and a plain YAML scalar drops it.
        'description' => ' Please provide the token name only, without brackets.',
        'form_type' => 'textfield',
        'required' => FALSE,
        'default' => NULL,
        'multiple' => FALSE,
        'options' => [],
        'options_site_dependent' => FALSE,
        'options_truncated' => FALSE,
        'value_format' => NULL,
        'source' => 'form',
      ],
      [
        'name' => 'access_result',
        'label' => 'Access result  ',
        'description' => 'Please note: this uses the "Defined by token" option: <em>x</em>',
        'form_type' => 'select',
        'required' => TRUE,
        'default' => 'forbidden',
        'multiple' => FALSE,
        'options' => [
          ['value' => '', 'label' => 'undefined'],
          ['value' => 3, 'label' => 'Three'],
          ['value' => '_eca_token', 'label' => 'Defined by token'],
          ['value' => 'yes', 'label' => 'Looks like a boolean'],
        ],
        'options_site_dependent' => TRUE,
        'options_truncated' => FALSE,
        'value_format' => 'A "quoted" hint: with a colon.',
        'source' => 'form',
      ],
    ];
    $extraFields = [
      [
        'name' => 'block_machine_name',
        'label' => '',
        'description' => '',
        'form_type' => NULL,
        'required' => FALSE,
        'default' => [],
        'multiple' => FALSE,
        'options' => [],
        'options_site_dependent' => FALSE,
        'options_truncated' => FALSE,
        'value_format' => NULL,
        'source' => 'default_configuration',
      ],
    ];
    $values = [
      'label' => $label,
      'description' => 'Only works when reacting upon <em>ECA File</em> events.',
      'provider' => 'eca_test',
      'provider_name' => 'ECA Test',
      'version_introduced' => '3.1.3',
      'type' => 'action',
      'id_fs' => 'eca_test_hostile',
      'key_sources' => ['form', 'default_configuration'],
      'fields_may_be_incomplete' => FALSE,
      // A submodule, so the tag has to name the parent module to install.
      'extension_info' => ['standalone' => FALSE, 'module' => 'eca'],
      'fields' => $formFields,
      'extra_fields' => $extraFields,
    ];
    $docPath = 'plugins/eca/test/actions/eca_test_hostile.md';

    $block = $reflection->getMethod('frontMatter')->invoke($command, $plugin, $values, $docPath);

    $this->assertStringStartsWith("---\n", $block);
    $this->assertStringEndsWith("\n---", $block);
    // PHP cannot tell an empty list from an empty map once decoded, so the
    // only place to assert it is the emitted text. "{  }" here would hand a
    // consumer in any other language an object where the contract promises an
    // array, see DocsCommands::YAML_DUMP_FLAGS.
    $this->assertStringNotContainsString('{  }', $block);
    $this->assertStringContainsString('options: []', $block);
    $this->assertStringContainsString('default: []', $block);

    $decoded = Yaml::decode($this->frontMatterBody($block));
    $this->assertSame(['title', 'tags', 'eca_plugin'], array_keys($decoded));
    $this->assertSame($label, $decoded['title']);
    $this->assertSame(['action', 'eca_test', 'eca action 3.1.3'], $decoded['tags']);

    // The assembler validates the contract version before it trusts anything
    // else in the block, so it has to be there and it has to come first.
    $entry = $decoded['eca_plugin'];
    $this->assertSame('contract_version', array_key_first($entry));
    $this->assertSame(2, $entry['contract_version']);
    unset($entry['contract_version']);

    $this->assertSame([
      'id' => 'eca_test_hostile',
      'id_fs' => 'eca_test_hostile',
      'type' => 'action',
      'provider' => 'eca_test',
      'provider_name' => 'ECA Test',
      'label' => $label,
      'description' => 'Only works when reacting upon <em>ECA File</em> events.',
      'version_introduced' => '3.1.3',
      'doc_path' => $docPath,
      'is_derivative' => FALSE,
      'base_id' => 'eca_test_hostile',
      'fields_may_be_incomplete' => FALSE,
      'key_sources' => ['form', 'default_configuration'],
      'fields' => [
        [
          'key' => 'token_name',
          'label' => 'Token name',
          'description' => ' Please provide the token name only, without brackets.',
          'form_type' => 'textfield',
          'required' => FALSE,
          'default' => NULL,
          'multiple' => FALSE,
          'options' => [],
          'options_site_dependent' => FALSE,
          'options_truncated' => FALSE,
          'value_format' => NULL,
          'source' => 'form',
        ],
        [
          'key' => 'access_result',
          'label' => 'Access result  ',
          'description' => 'Please note: this uses the "Defined by token" option: <em>x</em>',
          'form_type' => 'select',
          'required' => TRUE,
          'default' => 'forbidden',
          'multiple' => FALSE,
          'options' => [
            ['value' => '', 'label' => 'undefined'],
            ['value' => 3, 'label' => 'Three'],
            ['value' => '_eca_token', 'label' => 'Defined by token'],
            ['value' => 'yes', 'label' => 'Looks like a boolean'],
          ],
          'options_site_dependent' => TRUE,
          'options_truncated' => FALSE,
          'value_format' => 'A "quoted" hint: with a colon.',
          'source' => 'form',
        ],
        [
          'key' => 'block_machine_name',
          'label' => '',
          'description' => '',
          'form_type' => NULL,
          'required' => FALSE,
          'default' => [],
          'multiple' => FALSE,
          'options' => [],
          'options_site_dependent' => FALSE,
          'options_truncated' => FALSE,
          'value_format' => NULL,
          'source' => 'default_configuration',
        ],
      ],
    ], $entry);

    // Called out individually because a regression on any of these would be
    // reported as one unreadable array diff above.
    $this->assertNull($entry['fields'][0]['default'], 'A NULL default must not decode as an empty string.');
    $this->assertSame(3, $entry['fields'][1]['options'][1]['value'], 'An integer option value must not decode as a string.');
    $this->assertSame('', $entry['fields'][1]['options'][0]['value'], 'An empty-string option value must survive.');
    $this->assertSame('yes', $entry['fields'][1]['options'][3]['value'], 'A boolean-looking option value must stay a string.');
  }

  /**
   * Tests that hostile scalars survive the front matter round trip.
   *
   * Each value is placed in every position a plugin string can reach: the
   * page title, the plugin label and description, and an option value and
   * label. A plain YAML scalar breaks on most of them.
   *
   * @param string $value
   *   The scalar to round-trip.
   */
  #[DataProvider('hostileScalarProvider')]
  public function testFrontMatterScalarRoundTrip(string $value): void {
    $reflection = new \ReflectionClass(DocsCommands::class);
    $command = $reflection->newInstanceWithoutConstructor();

    $data = [
      'title' => $value,
      'tags' => ['action', 'eca_test', 'eca action 1.0.0'],
      'eca_plugin' => [
        'contract_version' => 2,
        'label' => $value,
        'description' => $value,
        'fields' => [
          [
            'key' => 'k',
            'label' => $value,
            'description' => $value,
            'form_type' => 'select',
            'required' => FALSE,
            'default' => NULL,
            'multiple' => FALSE,
            'options' => [
              ['value' => $value, 'label' => $value],
              ['value' => 3, 'label' => 'Three'],
              ['value' => '', 'label' => 'undefined'],
            ],
            'options_site_dependent' => FALSE,
            'options_truncated' => FALSE,
            'value_format' => NULL,
            'source' => 'form',
          ],
        ],
      ],
    ];

    $block = $reflection->getMethod('serializeFrontMatter')->invoke($command, $data);
    $this->assertSame($data, Yaml::decode($this->frontMatterBody($block)));
  }

  /**
   * Tests that a multi-line final value cannot swallow the closing delimiter.
   *
   * A multi-line string is dumped as a literal block scalar, and the dumper
   * emits no trailing newline after one. Without the guard in
   * ::serializeFrontMatter() the closing "---" lands on that block's last
   * line, which makes the whole document unparsable.
   *
   * @param string $value
   *   The multi-line value to place as the last value of the block.
   */
  #[DataProvider('trailingLiteralBlockProvider')]
  public function testFrontMatterTrailingLiteralBlock(string $value): void {
    $reflection = new \ReflectionClass(DocsCommands::class);
    $command = $reflection->newInstanceWithoutConstructor();

    $data = [
      'title' => 'Trailing literal block',
      'tags' => ['action', 'eca_test', 'eca action 1.0.0'],
      'eca_plugin' => [
        'contract_version' => 2,
        'id' => 'eca_test_trailing',
        'description' => $value,
      ],
    ];

    $block = $reflection->getMethod('serializeFrontMatter')->invoke($command, $data);

    $lines = explode("\n", $block);
    $this->assertSame('---', array_pop($lines), 'The closing delimiter needs a line of its own.');
    $this->assertSame($data, Yaml::decode($this->frontMatterBody($block)));
  }

  /**
   * Tests that a provider index page's front matter carries its own keys only.
   *
   * A provider name is the arbitrary "name" string of a module's info.yml, so
   * it reaches the page with exactly the same hostility as a plugin label. The
   * block also has to stay minimal: ::pluginDoc() renders the provider
   * template while the plugin's own front matter is still in scope, and an
   * "eca_plugin" block leaking onto a provider index page would advertise the
   * last plugin processed as if it described the module.
   *
   * @param string $value
   *   The provider name to round-trip.
   */
  #[DataProvider('hostileScalarProvider')]
  public function testProviderFrontMatter(string $value): void {
    $reflection = new \ReflectionClass(DocsCommands::class);
    $command = $reflection->newInstanceWithoutConstructor();

    $block = $reflection->getMethod('providerFrontMatter')->invoke($command, $value);
    $decoded = Yaml::decode($this->frontMatterBody($block));

    $this->assertSame(['title', 'tags'], array_keys($decoded));
    $this->assertSame($value, $decoded['title']);
    $this->assertSame(['module'], $decoded['tags']);
  }

  /**
   * Tests that the provider template emits the serialized block.
   *
   * ::providerFrontMatter() being correct proves nothing on its own, because
   * the template used to interpolate its own YAML by hand. The wiring is what
   * this asserts. ::render() swallows Twig errors and returns an empty string,
   * and that empty string would silently satisfy the "does not contain"
   * assertion at the end, so the page body is asserted first.
   */
  public function testProviderPageFrontMatter(): void {
    $reflection = new \ReflectionClass(DocsCommands::class);
    $command = $reflection->newInstanceWithoutConstructor();
    $loader = new TwigLoader();
    $reflection->getProperty('twigLoader')->setValue($command, $loader);
    $reflection->getProperty('twigEnvironment')->setValue($command, new TwigEnvironment($loader));

    // A module name is free text in its info.yml, and a double quote in one
    // used to corrupt the hand-written "title" line and with it the whole
    // block, which MkDocs then dropped without reporting anything.
    $providerName = 'Acme "Pro" Integration';
    $rendered = $reflection->getMethod('render')->invoke($command, self::PROVIDER_TEMPLATE, [
      'front_matter' => $reflection->getMethod('providerFrontMatter')->invoke($command, $providerName),
      'provider' => 'acme_pro',
      'provider_name' => $providerName,
      'extension_info' => ['standalone' => TRUE, 'module' => 'acme_pro'],
    ]);

    // A failed render returns an empty string, so prove the body is intact
    // before asserting anything about what the page does not contain.
    $this->assertStringContainsString('{!include/modules/acme_pro.md!}', $rendered);
    $this->assertStringContainsString('composer require drupal/acme_pro', $rendered);
    $this->assertStringContainsString('drush pm:install acme_pro', $rendered);

    $parts = explode("\n---\n", $rendered, 2);
    $this->assertCount(2, $parts, 'The rendered page has to open with a front matter block.');
    $decoded = Yaml::decode($this->frontMatterBody($parts[0] . "\n---"));

    $this->assertSame(['title', 'tags'], array_keys($decoded));
    $this->assertSame($providerName, $decoded['title']);
    $this->assertSame(['module'], $decoded['tags']);
    // ::pluginDoc() renders this template from a scope that still holds the
    // plugin's front matter, so the plugin contract block must never land on
    // a provider index page.
    $this->assertStringNotContainsString('eca_plugin', $rendered);
  }

  /**
   * Strips the delimiters off a front matter block, as a consumer would.
   *
   * @param string $block
   *   The front matter block.
   *
   * @return string
   *   The YAML between the delimiters.
   */
  private function frontMatterBody(string $block): string {
    $this->assertStringStartsWith("---\n", $block);
    $this->assertStringEndsWith("\n---", $block);
    return substr($block, 4, -3);
  }

  /**
   * Provides scalars that a hand-written YAML scalar cannot carry.
   */
  public static function hostileScalarProvider(): array {
    return [
      'double quote' => ['A "quoted" label'],
      'colon space' => ['Label: with a colon'],
      'leading whitespace' => ['  leading'],
      'trailing whitespace' => ['trailing  '],
      'only whitespace' => ['   '],
      'tab' => ["tab\tinside"],
      'multi-line' => ["first line\nsecond line"],
      'multi-line trailing newline' => ["first line\n"],
      'multi-line blank line' => ["first\n\nthird"],
      'multi-line indented' => ["  indented\n  second"],
      'multi-line trailing space' => ["first \nsecond "],
      'carriage return' => ["first\r\nsecond"],
      'document marker' => ['--- not a document'],
      'looks like a boolean' => ['yes'],
      'looks like NULL' => ['null'],
      'looks like an integer' => ['3'],
      'looks like a float' => ['3.0'],
      'looks like a sexagesimal' => ['1:30'],
      'leading zero' => ['017'],
      'hash' => ['label # not a comment'],
      'braces' => ['{not: a map}'],
      'brackets' => ['[not, a, list]'],
      'ampersand' => ['&anchor'],
      'asterisk' => ['*alias'],
      'backtick' => ['`backtick`'],
      'percent' => ['%directive'],
      'at sign' => ['@reserved'],
      'exclamation mark' => ['!tag'],
      'single quote' => ["it's a label"],
      'backslash' => ['a\\b'],
      'empty string' => [''],
    ];
  }

  /**
   * Provides multi-line values whose chomping indicators differ.
   */
  public static function trailingLiteralBlockProvider(): array {
    return [
      'no trailing newline' => ["first\nsecond"],
      'one trailing newline' => ["first\nsecond\n"],
      'two trailing newlines' => ["first\nsecond\n\n"],
      'blank line inside' => ["first\n\nthird"],
    ];
  }

  /**
   * Provides the four combinations of published viewer artifacts.
   */
  public static function viewerBindingProvider(): array {
    return [
      'both artifacts' => [TRUE, TRUE],
      'graph only' => [TRUE, FALSE],
      'diagram only' => [FALSE, TRUE],
      'no artifact at all' => [FALSE, FALSE],
    ];
  }

  /**
   * Provides navigation structures in supported formats.
   */
  public static function navigationProvider(): array {
    $expected = [
      'forms' => [
        'Existing model' => 'library/forms/existing_model.md',
      ],
    ];
    return [
      'legacy associative library TOC' => [
        [
          0 => 'library/index.md',
          'forms' => [
            'Existing model' => 'library/forms/existing_model.md',
          ],
        ],
        $expected,
      ],
      'nested navigation list' => [
        [
          'library/index.md',
          [
            'forms' => [
              ['Existing model' => 'library/forms/existing_model.md'],
            ],
          ],
        ],
        $expected,
      ],
    ];
  }

}
