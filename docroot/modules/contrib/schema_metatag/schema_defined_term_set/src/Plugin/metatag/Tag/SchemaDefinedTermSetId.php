<?php

namespace Drupal\schema_defined_term_set\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'id' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_defined_term_set_id",
 *   label = @Translation("@id"),
 *   description = @Translation("Globally unique id of the defined term set, usually a url."),
 *   name = "@id",
 *   group = "schema_defined_term_set",
 *   weight = 0,
 *   type = "string",
 *   property_type = "text",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaDefinedTermSetId extends SchemaNameBase {

}
