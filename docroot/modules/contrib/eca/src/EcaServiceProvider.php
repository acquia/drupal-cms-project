<?php

namespace Drupal\eca;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\eca\Token\ContribToken;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Provider for dynamically provided services by ECA.
 */
class EcaServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Add our dynamic subscriber to the event dispatcher.
    $definition = $container->getDefinition('event_dispatcher');
    $definition->addMethodCall('addSubscriber', [new Reference('eca.dynamic_subscriber')]);

    $modules = $container->getParameter('container.modules');
    if (isset($modules['token'])) {
      // Replace the core decorator with the contrib decorator.
      $definition = $container->getDefinition('eca.service.token');
      $definition->setClass(ContribToken::class);
    }

    if (!isset($modules['modeler_api'])) {
      // ECA declares the Modeler API as a dependency, but a site that upgrades
      // directly from ECA 2 boots this code while "modeler_api" is still absent
      // from core.extension. It only gets installed by eca_update_8012(), and
      // that update hook cannot run before the container has been compiled and
      // Drupal has booted. So every ECA service that depends on a Modeler API
      // service has to survive its absence, otherwise the site white-screens
      // with no way left to run the update at all.
      //
      // The template token resolver is the only Modeler API service that any
      // ECA service definition requires. It exists to offer form templates
      // inside a modeler, which is inert while no modeler can be reached, so
      // dropping it for the duration of the update loses nothing. As soon as
      // eca_update_8012() has installed the Modeler API, the container is
      // rebuilt and the real service is wired up again.
      //
      // The hook class of eca_form takes the same resolver, but cannot be
      // handled here: it is registered by a compiler pass, which runs after
      // every service provider, so it declares the dependency as optional in
      // its own service definition instead.
      //
      // @see eca_update_8012()
      // @see \Drupal\eca_form\Hook\TemplateFormHooks
      // @see \Drupal\Core\Hook\HookCollectorPass::registerHookServices()
      self::dropReference($container->getDefinition('eca.processor'), 'modeler_api.template_token_resolver');
    }
  }

  /**
   * Replaces references to a given service in a definition with NULL.
   *
   * The argument is matched by the service it references rather than by its
   * position, so that reordering the arguments of the definition cannot turn
   * this into a silent no-op.
   *
   * @param \Symfony\Component\DependencyInjection\Definition $definition
   *   The definition whose arguments are being rewritten.
   * @param string $serviceId
   *   The ID of the service that must no longer be referenced.
   */
  private static function dropReference(Definition $definition, string $serviceId): void {
    foreach ($definition->getArguments() as $index => $argument) {
      if ($argument instanceof Reference && (string) $argument === $serviceId) {
        $definition->replaceArgument($index, NULL);
      }
    }
  }

}
