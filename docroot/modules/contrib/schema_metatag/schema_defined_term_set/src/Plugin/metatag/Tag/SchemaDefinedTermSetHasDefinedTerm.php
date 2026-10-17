<?php

namespace Drupal\schema_defined_term_set\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'hasDefinedTerm' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_defined_term_set_has_defined_term",
 *   label = @Translation("Has defined term"),
 *   description = @Translation("A Defined Term contained in this term set."),
 *   name = "hasDefinedTerm",
 *   group = "schema_defined_term_set",
 *   weight = 2,
 *   multiple = TRUE,
 *   type = "string",
 *   property_type = "defined_term",
 *   secure = FALSE,
 *   tree_parent = {
 *     "DefinedTerm",
 *   },
 * )
 */
class SchemaDefinedTermSetHasDefinedTerm extends SchemaNameBase {

}
