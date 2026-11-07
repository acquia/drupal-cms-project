<?php

namespace Drupal\Tests\schema_defined_term_set\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag DefinedTermSet tags work correctly.
 *
 * @group schema_metatag
 * @group schema_defined_term
 */
class SchemaDefinedTermSetTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_metatag_test', 'schema_defined_term_set'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_defined_term_set';

  /**
   * {@inheritdoc}
   */
  public $groupName = 'schema_defined_term_set';

}
