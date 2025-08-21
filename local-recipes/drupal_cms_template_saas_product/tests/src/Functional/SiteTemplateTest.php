<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_template_saas_product\Functional;

use Composer\InstalledVersions;
use Drupal\experience_builder\JsonSchemaDefinitionsStreamwrapper;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

#[Group('drupal_cms_template_saas_product')]
#[IgnoreDeprecations]
final class SiteTemplateTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testSiteTemplate(): void {
    $dir = InstalledVersions::getInstallPath('drupal/drupal_cms_template_saas_product');
    // This is a site template, and therefore meant to be applied only once.
    $this->applyRecipe($dir);
  }

  /**
   * {@inheritdoc}
   */
  protected function rebuildAll(): void {
    // The rebuild won't succeed without the `json-schema-definitions` stream
    // wrapper. This would normally happen automatically whenever a module is
    // installed, but in this case, all of that has taken place in a separate
    // process, so we need to refresh this process manually.
    // @see experience_builder_module_preinstall()
    $this->container->get('stream_wrapper_manager')
      ->registerWrapper(
        'json-schema-definitions',
        JsonSchemaDefinitionsStreamwrapper::class,
        JsonSchemaDefinitionsStreamwrapper::getType(),
      );

    parent::rebuildAll();
  }

}
