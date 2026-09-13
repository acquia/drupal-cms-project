<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for ECA condition plugins.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class ConditionTest extends KernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'eca_base',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(static::$modules);
  }

  /**
   * Tests scalar comparison.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testScalarComparison(): void {
    // Create the Article content type with a standard body field.
    $this->createContentType(['type' => 'article', 'name' => 'Article']);

    $body = $this->randomMachineName(32);
    $summary = $this->randomMachineName(16);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'title' => '123',
      'body' => [
        [
          'value' => $body,
          'summary' => $summary,
          'format' => 'plain_text',
        ],
      ],
    ]);
    $node->save();

    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    $token_services->addTokenData('node', $node);
    /** @var \Drupal\Component\Plugin\PluginManagerInterface $condition_plugin_manager */
    $condition_plugin_manager = \Drupal::service('plugin.manager.eca.condition');
    /** @var \Drupal\eca_base\Plugin\ECA\Condition\ScalarComparison $plugin */
    $plugin = $condition_plugin_manager->createInstance('eca_scalar', [
      'left' => '[node:title]',
      'right' => '123',
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'operator' => StringComparisonBase::COMPARE_EQUALS,
      'case' => TRUE,
    ]);
    $this->assertTrue($plugin->evaluate());

    /** @var \Drupal\eca_base\Plugin\ECA\Condition\ScalarComparison $plugin */
    $plugin = $condition_plugin_manager->createInstance('eca_scalar', [
      'left' => '[node:title]',
      'right' => '124',
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'operator' => StringComparisonBase::COMPARE_EQUALS,
      'case' => TRUE,
    ]);
    $this->assertFalse($plugin->evaluate());

    /** @var \Drupal\eca_base\Plugin\ECA\Condition\ScalarComparison $plugin */
    $plugin = $condition_plugin_manager->createInstance('eca_scalar', [
      'left' => '[node:title]',
      'right' => 'aaa',
      'type' => StringComparisonBase::COMPARE_TYPE_COUNT,
      'operator' => StringComparisonBase::COMPARE_EQUALS,
      'case' => TRUE,
    ]);
    $this->assertTrue($plugin->evaluate());

    /** @var \Drupal\eca_base\Plugin\ECA\Condition\ScalarComparison $plugin */
    $plugin = $condition_plugin_manager->createInstance('eca_scalar', [
      'left' => '[node:title]',
      'right' => 'aaaa',
      'type' => StringComparisonBase::COMPARE_TYPE_COUNT,
      'operator' => StringComparisonBase::COMPARE_EQUALS,
      'case' => TRUE,
    ]);
    $this->assertFalse($plugin->evaluate());

    /** @var \Drupal\eca_base\Plugin\ECA\Condition\ScalarComparison $plugin */
    $plugin = $condition_plugin_manager->createInstance('eca_scalar', [
      'left' => '100',
      'right' => '99',
      'type' => StringComparisonBase::COMPARE_TYPE_NUMERIC,
      'operator' => StringComparisonBase::COMPARE_GREATERTHAN,
      'case' => TRUE,
    ]);
    $this->assertTrue($plugin->evaluate());

    /** @var \Drupal\eca_base\Plugin\ECA\Condition\ScalarComparison $plugin */
    $plugin = $condition_plugin_manager->createInstance('eca_scalar', [
      'left' => '99',
      'right' => '100',
      'type' => StringComparisonBase::COMPARE_TYPE_NUMERIC,
      'operator' => StringComparisonBase::COMPARE_GREATERTHAN,
      'case' => TRUE,
    ]);
    $this->assertFalse($plugin->evaluate());

    /** @var \Drupal\eca_base\Plugin\ECA\Condition\ScalarComparison $plugin */
    $plugin = $condition_plugin_manager->createInstance('eca_scalar', [
      'left' => '100',
      'right' => 'DrUpAl!',
      'type' => StringComparisonBase::COMPARE_TYPE_NUMERIC,
      'operator' => StringComparisonBase::COMPARE_GREATERTHAN,
      'case' => TRUE,
    ]);
    $this->assertFalse($plugin->evaluate());
  }

}
