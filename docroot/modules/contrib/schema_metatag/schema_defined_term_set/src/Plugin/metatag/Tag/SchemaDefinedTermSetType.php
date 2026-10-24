<?php

namespace Drupal\schema_defined_term_set\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_defined_term_set_type",
 *   label = @Translation("@type"),
 *   description = @Translation("REQUIRED. The set type."),
 *   name = "@type",
 *   group = "schema_defined_term_set",
 *   weight = -10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "type",
 *   tree_parent = {
 *     "DefinedTermSet",
 *   },
 *   tree_depth = -1,
 * )
 */
class SchemaDefinedTermSetType extends SchemaNameBase {

}
