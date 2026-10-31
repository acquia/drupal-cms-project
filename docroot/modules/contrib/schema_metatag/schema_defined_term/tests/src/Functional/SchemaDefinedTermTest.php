<?php

namespace Drupal\Tests\schema_defined_term\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag DefinedTerm tags work correctly.
 *
 * @group schema_metatag
 * @group schema_defined_term
 */
class SchemaDefinedTermTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_metatag_test', 'schema_defined_term'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_defined_term';

  /**
   * {@inheritdoc}
   */
  public $groupName = 'schema_defined_term';

}
