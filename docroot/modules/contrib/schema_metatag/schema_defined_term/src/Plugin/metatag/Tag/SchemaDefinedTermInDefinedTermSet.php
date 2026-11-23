<?php

namespace Drupal\schema_defined_term\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'inDefinedTermSet' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_defined_term_in_defined_term_set",
 *   label = @Translation("In defined term set"),
 *   description = @Translation("The DefinedTermSet containing this term."),
 *   name = "inDefinedTermSet",
 *   group = "schema_defined_term",
 *   weight = 3,
 *   type = "string",
 *   property_type = "text",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaDefinedTermInDefinedTermSet extends SchemaNameBase {

}
