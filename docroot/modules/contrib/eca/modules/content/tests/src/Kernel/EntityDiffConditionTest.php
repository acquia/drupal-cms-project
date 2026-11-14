<?php

namespace Drupal\Tests\eca_content\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the entity diff condition plugin.
 */
#[Group('eca')]
#[Group('eca_content')]
#[RunTestsInSeparateProcesses]
class EntityDiffConditionTest extends EntityDiffTestBase {

  /**
   * Tests entity compare with non-equal entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTwoEntitiesNotEqualIncludeAll(): void {
    $node = $this->createAndGetNode('First', 'The first node.');
    $nodeToCompare = $this->createAndGetNode('Second', 'The second node.');

    $this->addNodeAsToken($nodeToCompare);

    $config = $this->getConfig();

    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');
    $condition = $condition_manager->createInstance('eca_entity_diff', $config);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate());
  }

  /**
   * Tests entity compare with equal entities by specific fields.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTwoEntitiesEqualIncludeFields(): void {
    $node = $this->createAndGetNode('First', 'The first node.');
    $nodeToCompare = $this->createAndGetNode('First', 'The first node.');

    $this->addNodeAsToken($nodeToCompare);

    $config = $this->getConfig(['title', 'body']);

    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');
    $condition = $condition_manager->createInstance('eca_entity_diff', $config);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate());
  }

  /**
   * Tests entity compare with equal entities by specific fields.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTwoEntitiesEqualExcludeFields(): void {
    $node = $this->createAndGetNode('A test', 'The test node.');
    $nodeToCompare = $this->createAndGetNode('A test', 'The test node.');

    $this->addNodeAsToken($nodeToCompare);
    $config = $this->getConfig([], ['nid', 'uuid', 'vid']);

    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');
    $condition = $condition_manager->createInstance('eca_entity_diff', $config);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate());
  }

}
