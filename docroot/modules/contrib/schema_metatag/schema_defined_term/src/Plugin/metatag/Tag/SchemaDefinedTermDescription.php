<?php

namespace Drupal\schema_defined_term\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'description' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_defined_term_description",
 *   label = @Translation("Description"),
 *   description = @Translation("Definition of the term."),
 *   name = "description",
 *   group = "schema_defined_term",
 *   property_type = "text",
 *   tree_parent = {
 *     "DefinedTerm",
 *   },
 *   weight = 3,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaDefinedTermDescription extends SchemaNameBase {

}
