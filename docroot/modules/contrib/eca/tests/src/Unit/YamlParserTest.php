<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\eca\Service\YamlParser;
use Drupal\eca\Token\TokenInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the YamlParser service.
 */
#[Group('eca')]
#[Group('eca_core')]
class YamlParserTest extends UnitTestCase {

  /**
   * The mocked token service.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $token;

  /**
   * The YAML parser under test.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->token = $this->createStub(TokenInterface::class);
    $this->yamlParser = new YamlParser($this->token);
  }

  /**
   * Tests parsing a scalar string with token replacement.
   */
  public function testParseScalarString(): void {
    $this->token->method('replaceClear')
      ->willReturnCallback(fn($text) => str_replace('[site:name]', 'My Site', $text));

    $result = $this->yamlParser->parse('Hello [site:name]');
    $this->assertSame('Hello My Site', $result);
  }

  /**
   * Tests parsing a scalar string without token replacement.
   */
  public function testParseScalarStringNoReplace(): void {
    $token = $this->createMock(TokenInterface::class);
    $token->expects($this->never())->method('replaceClear');
    $yamlParser = new YamlParser($token);

    $result = $yamlParser->parse('Hello [site:name]', FALSE);
    $this->assertSame('Hello [site:name]', $result);
  }

  /**
   * Tests that tokens in array values are replaced.
   */
  public function testParseArrayValues(): void {
    $this->token->method('replaceClear')
      ->willReturnCallback(fn($text) => str_replace('[node:title]', 'My Node', $text));

    $yaml = "key: '[node:title]'";
    $result = $this->yamlParser->parse($yaml);
    $this->assertSame(['key' => 'My Node'], $result);
  }

  /**
   * Tests that tokens in array keys are replaced.
   */
  public function testParseArrayKeys(): void {
    $this->token->method('replaceClear')
      ->willReturnCallback(fn($text) => str_replace('[token:key]', 'replaced_key', $text));

    $yaml = "'[token:key]': some_value";
    $result = $this->yamlParser->parse($yaml);
    $this->assertSame(['replaced_key' => 'some_value'], $result);
  }

  /**
   * Tests that tokens in both keys and values are replaced.
   */
  public function testParseArrayKeysAndValues(): void {
    $this->token->method('replaceClear')
      ->willReturnCallback(fn($text) => strtr($text, [
        '[token:key]' => 'replaced_key',
        '[token:value]' => 'replaced_value',
      ]));

    $yaml = "'[token:key]': '[token:value]'";
    $result = $this->yamlParser->parse($yaml);
    $this->assertSame(['replaced_key' => 'replaced_value'], $result);
  }

  /**
   * Tests recursive token replacement in nested arrays.
   */
  public function testParseNestedArrays(): void {
    $this->token->method('replaceClear')
      ->willReturnCallback(fn($text) => strtr($text, [
        '[token:outer_key]' => 'outer',
        '[token:inner_key]' => 'inner',
        '[token:value]' => 'the_value',
      ]));

    $yaml = <<<YAML
'[token:outer_key]':
  '[token:inner_key]': '[token:value]'
YAML;

    $result = $this->yamlParser->parse($yaml);
    $this->assertSame(['outer' => ['inner' => 'the_value']], $result);
  }

  /**
   * Tests that integer keys are preserved and their values are still processed.
   */
  public function testParseIntegerKeysRecurse(): void {
    $this->token->method('replaceClear')
      ->willReturnCallback(fn($text) => strtr($text, [
        '[token:key]' => 'resolved_key',
        '[token:value]' => 'resolved_value',
      ]));

    $yaml = <<<YAML
- '[token:key]': '[token:value]'
- plain_value
YAML;

    $result = $this->yamlParser->parse($yaml);
    $expected = [
      ['resolved_key' => 'resolved_value'],
      'plain_value',
    ];
    $this->assertSame($expected, $result);
  }

  /**
   * Tests deeply nested arrays under integer keys.
   */
  public function testParseDeeplyNestedUnderIntegerKeys(): void {
    $this->token->method('replaceClear')
      ->willReturnCallback(fn($text) => strtr($text, [
        '[token:a]' => 'key_a',
        '[token:b]' => 'key_b',
        '[token:val]' => 'deep_value',
      ]));

    $yaml = <<<YAML
-
  '[token:a]':
    '[token:b]': '[token:val]'
YAML;

    $result = $this->yamlParser->parse($yaml);
    $expected = [
      ['key_a' => ['key_b' => 'deep_value']],
    ];
    $this->assertSame($expected, $result);
  }

  /**
   * Tests that empty values are not passed to token replacement.
   */
  public function testParseEmptyValuesSkipped(): void {
    $this->token->method('replaceClear')
      ->willReturnCallback(function ($text) {
        // Should never be called with an empty string.
        $this->assertNotEmpty($text);
        return $text;
      });

    $yaml = <<<YAML
key1: ''
key2: some_value
YAML;

    $result = $this->yamlParser->parse($yaml);
    $this->assertSame(['key1' => '', 'key2' => 'some_value'], $result);
  }

  /**
   * Tests that non-string values (int, bool, null) are preserved.
   */
  public function testParseNonStringValuesPreserved(): void {
    $this->token->method('replaceClear')
      ->willReturnCallback(fn($text) => $text);

    $yaml = <<<YAML
count: 42
enabled: true
nothing: null
label: some_text
YAML;

    $result = $this->yamlParser->parse($yaml);
    $this->assertSame(42, $result['count']);
    $this->assertTrue($result['enabled']);
    $this->assertNull($result['nothing']);
    $this->assertSame('some_text', $result['label']);
  }

  /**
   * Tests that token replacement is disabled when flag is FALSE.
   */
  public function testParseArrayNoReplace(): void {
    $token = $this->createMock(TokenInterface::class);
    $token->expects($this->never())->method('replaceClear');
    $yamlParser = new YamlParser($token);

    $yaml = "'[token:key]': '[token:value]'";
    $result = $yamlParser->parse($yaml, FALSE);
    $this->assertSame(['[token:key]' => '[token:value]'], $result);
  }

}
