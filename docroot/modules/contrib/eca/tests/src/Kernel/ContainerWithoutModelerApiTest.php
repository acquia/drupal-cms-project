<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Processor;
use Drupal\eca_form\Hook\TemplateFormHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that ECA's container compiles without the Modeler API being installed.
 *
 * A site that upgrades directly from ECA 2.1 to ECA 3.1 runs ECA 3.1 code while
 * "modeler_api" is still absent from core.extension. It only gets installed by
 * eca_update_8012(), and that update hook cannot run before the container has
 * been compiled and Drupal has booted. Every ECA service definition that
 * depends on a Modeler API service must therefore tolerate its absence, or the
 * site white-screens with no way out other than editing the database by hand.
 *
 * @see eca_update_8012()
 * @see \Drupal\eca\EcaServiceProvider::alter()
 */
#[Group('eca')]
#[Group('eca_core')]
#[Group('eca_update')]
#[RunTestsInSeparateProcesses]
class ContainerWithoutModelerApiTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Deliberately omits "modeler_api" to reproduce the upgrade window. Kernel
   * tests do not resolve info.yml dependencies, so the module stays absent even
   * though ECA declares it as a dependency.
   *
   * Every sub-module that a site can have enabled without pulling in anything
   * beyond ECA itself is listed, because the container is compiled as a whole:
   * a single sub-module referencing a Modeler API service brings the site down
   * just as effectively as ECA core doing it. That includes eca_development,
   * which is the one sub-module that does declare the Modeler API as its own
   * dependency, but which a site upgrading from ECA 2 can still have enabled
   * because back then it did not.
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_access',
    'eca_base',
    'eca_cache',
    'eca_config',
    'eca_content',
    'eca_development',
    'eca_endpoint',
    'eca_form',
    'eca_log',
    'eca_menu',
    'eca_misc',
    'eca_node_access',
    'eca_queue',
    'eca_render',
    'eca_ui',
    'eca_user',
  ];

  /**
   * Tests that the container builds and its ECA services are instantiable.
   */
  public function testContainerCompilesWithoutModelerApi(): void {
    // Guard the premise of this test: reaching this line already proves that
    // the container compiled, so make sure it compiled without modeler_api.
    $this->assertFalse($this->container->get('module_handler')->moduleExists('modeler_api'));
    $this->assertFalse($this->container->has('modeler_api.template_token_resolver'));

    // The ECA processor references the Modeler API template token resolver as a
    // constructor argument, and must still be instantiable without it.
    $this->assertTrue($this->container->has('eca.processor'));
    $this->assertInstanceOf(Processor::class, $this->container->get('eca.processor'));

    // The template form hooks of eca_form receive the same resolver, and get
    // instantiated for every form that is built, including the ones of the
    // database update UI at /update.php.
    $this->assertInstanceOf(TemplateFormHooks::class, $this->container->get(TemplateFormHooks::class));
  }

}
