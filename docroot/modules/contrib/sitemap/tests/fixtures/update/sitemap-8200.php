<?php

/**
 * @file
 * Contains database additions for Sitemap schema version 8200.
 */

// cspell:disable
use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
$extensions = \unserialize($extensions, ['allowed_classes' => FALSE]);
$extensions['module']['sitemap'] = 0;
$connection->update('config')
  ->fields(['data' => serialize($extensions)])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();


$rawBlockData = [
  'uuid' => '8d72a2fe-ee02-4f1e-b987-25c367ad5670',
  'langcode' => 'en',
  'status' => TRUE,
  'dependencies' => [
    'module' => [
      'sitemap',
    ],
    'theme' => [
      'stark',
    ],
  ],
  'id' => 'sitemap_syndicate_stark',
  'theme' => 'stark',
  'region' => 'content',
  'weight' => 0,
  'provider' => NULL,
  'plugin' => 'sitemap_syndicate',
  'settings' => [
    'id' => 'sitemap_syndicate',
    'label' => 'Syndicate (sitemap)',
    'label_display' => 'visible',
    'provider' => 'sitemap',
    'cache' => [
      'max_age' => 0,
    ],
  ],
  'visibility' => [],
];
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'block.block.sitemap_syndicate_stark',
    'data' => \serialize($rawBlockData),
  ])
  ->execute();

$rawSitemapData = [
  '_core' => [
    'default_config_hash' => 'GUKBlXIBQ2d0H8Q7M_Rqn1Q93tRxLPfs9cG-VeQC9NI',
  ],
  'page_title' => 'Sitemap',
  'message' => [
    'value' => '',
    'format' => 'plain_text',
  ],
  'plugins' => [
    'vocabulary:tags' => [
      'enabled' => TRUE,
      'weight' => 0,
      'settings' => [
        'title' => 'Tags',
        'show_description' => FALSE,
        'show_count' => FALSE,
        'display_unpublished' => FALSE,
        'term_depth' => 9,
        'term_count_threshold' => 0,
        'customize_link' => FALSE,
        'term_link' => 'entity.taxonomy_term.canonical|taxonomy_term',
        'always_link' => FALSE,
        'enable_rss' => FALSE,
        'rss_link' => 'view.taxonomy_term.feed_1|arg_0',
        'rss_depth' => 9,
      ],
      'id' => 'vocabulary:tags',
      'provider' => 'sitemap',
    ],
    'frontpage' => [
      'enabled' => TRUE,
      'weight' => 0,
      'settings' => [
        'title' => 'Front page',
        'rss' => '/dolor.sit',
      ],
      'id' => 'frontpage',
      'provider' => 'sitemap',
    ],
    'menu:main' => [
      'enabled' => TRUE,
      'weight' => 0,
      'settings' => [
        'title' => 'Main navigation',
        'show_disabled' => FALSE,
      ],
      'id' => 'menu:main',
      'provider' => 'sitemap',
    ],
  ],
  'include_css' => TRUE,
  'rss_front' => '/lorem.ipsum',
];
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'sitemap.settings',
    'data' => \serialize($rawSitemapData),
  ])
  ->execute();

$connection->insert('key_value')
  ->fields([
    'collection',
    'name',
    'value',
  ])
  ->values([
    'collection' => 'system.schema',
    'name' => 'sitemap',
    'value' => 'i:8200;',
  ])
  ->execute();

$connection->insert('router')
  ->fields([
    'name',
    'path',
    'pattern_outline',
    'fit',
    'route',
    'number_parts',
  ])
  ->values([
    'name' => 'sitemap.page',
    'path' => '/sitemap',
    'pattern_outline' => '/sitemap',
    'fit' => '1',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:8:"/sitemap";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:11:"_controller";s:58:"\Drupal\sitemap\Controller\SitemapController::buildSitemap";s:15:"_title_callback";s:54:"\Drupal\sitemap\Controller\SitemapController::getTitle";}s:12:"requirements";a:1:{s:11:"_permission";s:14:"access sitemap";}s:7:"options";a:3:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:15:"{^/sitemap$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:8:"/sitemap";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:1;s:14:"patternOutline";s:8:"/sitemap";s:8:"numParts";i:1;}}',
    'number_parts' => '1',
  ])
  ->values([
    'name' => 'sitemap.settings',
    'path' => '/admin/config/search/sitemap',
    'pattern_outline' => '/admin/config/search/sitemap',
    'fit' => '15',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:28:"/admin/config/search/sitemap";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:5:"_form";s:40:"\Drupal\sitemap\Form\SitemapSettingsForm";s:6:"_title";s:7:"Sitemap";}s:12:"requirements";a:1:{s:11:"_permission";s:18:"administer sitemap";}s:7:"options";a:4:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:4:"utf8";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:35:"{^/admin/config/search/sitemap$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:28:"/admin/config/search/sitemap";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:15;s:14:"patternOutline";s:28:"/admin/config/search/sitemap";s:8:"numParts";i:4;}}',
    'number_parts' => '4',
  ])
  ->execute();
