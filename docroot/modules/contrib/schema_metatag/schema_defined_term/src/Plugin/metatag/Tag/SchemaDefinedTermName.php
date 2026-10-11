<?php

namespace Drupal\schema_defined_term\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'name' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_defined_term_name",
 *   label = @Translation("Name"),
 *   description = @Translation("The name of the term."),
 *   name = "name",
 *   group = "schema_defined_term",
 *   weight = 1,
 *   type = "string",
 *   property_type = "text",
 *   secure = FALSE,
 *   multiple = FALSE,
 * )
 */
class SchemaDefinedTermName extends SchemaNameBase {

}
