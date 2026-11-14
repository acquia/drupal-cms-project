<?php

namespace Drupal\Tests\editoria11y\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\editoria11y\Api;
use Drupal\Tests\UnitTestCase;

/**
 * Tests construction of the editoria11y.api service.
 *
 * @coversDefaultClass \Drupal\editoria11y\Api
 *
 * @group editoria11y
 */
class ApiTest extends UnitTestCase {

  /**
   * Builds a container holding mocks of every service Api depends on.
   *
   * The entity type manager is mocked from the interface only, as a decorator
   * such as Trash's TrashEntityTypeManager would be.
   */
  protected function buildContainer(): ContainerBuilder {
    $container = new ContainerBuilder();
    $container->set('current_user', $this->createMock(AccountInterface::class));
    $container->set('database', $this->createMock(Connection::class));
    $container->set('entity_type.manager', $this->createMock(EntityTypeManagerInterface::class));
    $container->set('config.factory', $this->createMock(ConfigFactoryInterface::class));
    $container->set('path.validator', $this->createMock(PathValidatorInterface::class));
    return $container;
  }

  /**
   * Tests that any EntityTypeManagerInterface implementation is accepted.
   *
   * @covers ::__construct
   */
  public function testConstructAcceptsDecoratedEntityTypeManager(): void {
    $container = $this->buildContainer();
    $api = new Api(
      $container->get('current_user'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('path.validator'),
    );
    $this->assertInstanceOf(Api::class, $api);
  }

  /**
   * Tests that the factory method passes every dependency to the constructor.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $this->assertInstanceOf(Api::class, Api::create($this->buildContainer()));
  }

}
