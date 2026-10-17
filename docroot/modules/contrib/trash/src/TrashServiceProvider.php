<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\trash\EntityQuery\Sql\PgsqlQueryFactory as CorePgsqlQueryFactory;
use Drupal\trash\EntityQuery\Sql\QueryFactory as CoreQueryFactory;
use Drupal\trash\EntityQuery\Workspaces\PgsqlQueryFactory as WorkspacesPgsqlQueryFactory;
use Drupal\trash\EntityQuery\Workspaces\QueryFactory as WorkspacesQueryFactory;
use Drupal\trash\Handler\TrashHandlerPass;
use Drupal\trash\LayoutBuilder\TrashInlineBlockUsage;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Alters container services.
 */
class TrashServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->addCompilerPass(new TrashHandlerPass());
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // @todo Revert to decorating the entity type manager when we can require
    //   Drupal 10.
    if ($container->hasDefinition('entity_type.manager')) {
      $container->getDefinition('entity_type.manager')
        ->setClass(TrashEntityTypeManager::class);
    }

    // Decorate entity query factories.
    if ($container->hasDefinition('workspaces.entity.query.sql')) {
      $priority = 10;
      $factory = [
        'service' => 'workspaces.entity.query.sql',
        'class' => WorkspacesQueryFactory::class,
      ];
      $pgsql_factory = [
        'service' => 'pgsql.workspaces.entity.query.sql',
        'class' => WorkspacesPgsqlQueryFactory::class,
      ];
    }
    else {
      $priority = 100;
      $factory = [
        'service' => 'entity.query.sql',
        'class' => CoreQueryFactory::class,
      ];
      $pgsql_factory = [
        'service' => 'pgsql.entity.query.sql',
        'class' => CorePgsqlQueryFactory::class,
      ];
    }
    if ($container->hasDefinition($factory['service'])) {
      $definition = (new ChildDefinition($factory['service']))
        ->setClass($factory['class'])
        ->setDecoratedService('entity.query.sql', NULL, $priority);
      $container->setDefinition('trash.entity.query.sql', $definition);
    }
    if ($container->hasDefinition($pgsql_factory['service'])) {
      $definition = (new ChildDefinition($pgsql_factory['service']))
        ->setClass($pgsql_factory['class'])
        ->setDecoratedService('pgsql.entity.query.sql', NULL, $priority);
      $container->setDefinition('trash.pgsql.entity.query.sql', $definition);
    }

    if ($container->hasDefinition('workspaces.information')) {
      $container->register('trash.workspaces.information', TrashWorkspaceInformation::class)
        ->setPublic(FALSE)
        ->setDecoratedService('workspaces.information', NULL, 100)
        ->addArgument(new Reference('trash.workspaces.information.inner'))
        ->addArgument(new Reference('trash.manager'));
    }

    if ($container->hasDefinition('workspaces.manager')) {
      $container->register('trash.workspaces.manager', TrashWorkspaceManager::class)
        ->setPublic(FALSE)
        ->setDecoratedService('workspaces.manager', NULL, 100)
        ->addArgument(new Reference('trash.workspaces.manager.inner'))
        ->addArgument(new Reference('trash.manager'));
    }

    if ($container->hasDefinition('inline_block.usage')) {
      $container->register('trash.inline_block.usage', TrashInlineBlockUsage::class)
        ->setPublic(FALSE)
        ->setDecoratedService('inline_block.usage')
        ->addArgument(new Reference('trash.inline_block.usage.inner'))
        ->addArgument(new Reference('trash.manager'));
    }

    if ($container->hasDefinition('wse_menu.tree_storage')) {
      $container->getDefinition('wse_menu.tree_storage')
        ->setClass(TrashWseMenuTreeStorage::class)
        ->addMethodCall('setTrashManager', [new Reference('trash.manager')]);
    }

    // Ensure the trash ignore subscriber is one of the first definitions used
    // after authentication. This is necessary because in the event that two
    // subscriber event listeners have the same priority, then the one which was
    // registered first takes precedence. We must ensure that the ignore
    // subscriber is one of the first subscribers with a priority of 299.
    $trash_ignore_subscriber = 'trash.ignore_subscriber';

    // This is one of the earliest 'kernel.request' event listeners with a
    // priority of 299, so we must ensure the ignore subscriber is added
    // before it.
    $target = 'system.timezone_resolver';

    if ($container->hasDefinition($trash_ignore_subscriber) && $container->hasDefinition($target)) {
      $definitions = $container->getDefinitions();
      // Move 'trash.ignore_subscriber' before 'system.timezone_resolver' so it
      // runs first among priority-299 'kernel.request' listeners.
      $trash_definition = [$trash_ignore_subscriber => $definitions[$trash_ignore_subscriber]];
      unset($definitions[$trash_ignore_subscriber]);
      $pos = array_search($target, array_keys($definitions), TRUE);
      assert(is_int($pos));
      $container->setDefinitions(
        array_slice($definitions, 0, $pos, TRUE) +
        $trash_definition +
        array_slice($definitions, $pos, NULL, TRUE)
      );
    }
  }

}
