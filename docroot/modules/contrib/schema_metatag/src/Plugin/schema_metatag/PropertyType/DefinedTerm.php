<?php

namespace Drupal\schema_metatag\Plugin\schema_metatag\PropertyType;

use Drupal\schema_metatag\Plugin\schema_metatag\PropertyTypeBase;

/**
 * Provides a plugin for the 'DefinedTerm' Schema.org property type.
 *
 * @SchemaPropertyType(
 *   id = "defined_term",
 *   label = @Translation("DefinedTerm"),
 *   tree_parent = {
 *     "DefinedTerm",
 *   },
 *   tree_depth = 0,
 *   property_type = "DefinedTerm",
 *   sub_properties = {
 *     "@type" = {
 *       "id" = "type",
 *       "label" = @Translation("@type"),
 *       "description" = "",
 *       "tree_parent" = {
 *          "DefinedTerm",
 *       },
 *       "tree_depth" = 0,
 *     },
 *     "name" = {
 *       "id" = "text",
 *       "label" = @Translation("name"),
 *       "description" = @Translation(""),
 *     },
 *     "url" = {
 *       "id" = "url",
 *       "label" = @Translation("url"),
 *       "description" = @Translation("Absolute URL of the canonical Web page for the term."),
 *     },
 *     "inDefinedTermSet" = {
 *       "id" = "text",
 *       "label" = @Translation("inDefinedTermSet"),
 *       "description" = @Translation("The DefinedTermSet containing this term."),
 *     },
 *   }
 * )
 */
class DefinedTerm extends PropertyTypeBase {

}
