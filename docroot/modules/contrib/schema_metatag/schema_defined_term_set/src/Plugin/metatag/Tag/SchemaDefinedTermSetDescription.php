<?php

namespace Drupal\schema_defined_term_set\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'description' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_defined_term_set_description",
 *   label = @Translation("description"),
 *   description = @Translation("A description of the term set."),
 *   name = "description",
 *   group = "schema_defined_term_set",
 *   weight = 2,
 *   property_type = "text",
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   tree_parent = {
 *     "DefinedTermSet",
 *   },
 * )
 */
class SchemaDefinedTermSetDescription extends SchemaNameBase {

}
