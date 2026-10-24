<?php

namespace Drupal\schema_organization\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_organization_founding_date' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_organization_founding_date",
 *   label = @Translation("foundingDate"),
 *   description = @Translation("The date that this organization was founded, in ISO 8601 format, 2017-12-31."),
 *   name = "foundingDate",
 *   group = "schema_organization",
 *   weight = 1.03,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "date",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaOrganizationFoundingDate extends SchemaNameBase {

}
