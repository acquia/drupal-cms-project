<?php

namespace Drupal\schema_defined_term\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'DefinedTerm' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_defined_term",
 *   label = @Translation("Schema.org: DefinedTerm"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = {
 *     ":url" = "https://schema.org/DefinedTerm",
 *   }),
 *   weight = 10,
 * )
 */
class SchemaDefinedTerm extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
