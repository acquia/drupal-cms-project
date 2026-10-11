<?php

namespace Drupal\schema_defined_term\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'termCode' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_defined_term_term_code",
 *   label = @Translation("Term Code"),
 *   description = @Translation("Code identifying the term."),
 *   name = "termCode",
 *   group = "schema_defined_term",
 *   weight = 4,
 *   type = "string",
 *   property_type = "text",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaDefinedTermTermCode extends SchemaNameBase {

}
