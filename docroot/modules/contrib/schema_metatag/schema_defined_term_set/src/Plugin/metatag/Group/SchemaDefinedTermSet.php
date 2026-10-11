<?php

namespace Drupal\schema_defined_term_set\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'DefinedTermSet' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_defined_term_set",
 *   label = @Translation("Schema.org: DefinedTermSet"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = {
 *     ":url" = "https://schema.org/DefinedTermSet",
 *   }),
 *   weight = 10,
 * )
 */
class SchemaDefinedTermSet extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
