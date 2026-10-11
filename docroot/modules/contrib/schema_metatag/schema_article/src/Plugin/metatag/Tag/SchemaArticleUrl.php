<?php

namespace Drupal\schema_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_article_url' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_article_url",
 *   label = @Translation("url"),
 *   description = @Translation("The url of the article."),
 *   name = "url",
 *   group = "schema_article",
 *   weight = -1,
 *   type = "string",
 *   property_type = "url",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaArticleUrl extends SchemaNameBase {

}
