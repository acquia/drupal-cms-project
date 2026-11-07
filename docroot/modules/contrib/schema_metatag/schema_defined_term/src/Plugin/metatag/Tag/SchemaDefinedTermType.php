<?php

namespace Drupal\schema_defined_term\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_defined_term_type",
 *   label = @Translation("@type"),
 *   description = @Translation("REQUIRED. The type."),
 *   name = "@type",
 *   group = "schema_defined_term",
 *   weight = -10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "type",
 *   tree_parent = {
 *     "DefinedTerm",
 *   },
 *   tree_depth = 0,
 * )
 */
class SchemaDefinedTermType extends SchemaNameBase {

}
