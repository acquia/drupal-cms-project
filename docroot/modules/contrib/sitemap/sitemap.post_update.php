<?php

/**
 * @file
 * Post-update hooks for the sitemap module.
 */

declare(strict_types=1);

use Drupal\block\BlockInterface;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Restore the ability to modify the rss feed path in sitemap_syndicate block.
 */
function sitemap_post_update_sitemap_syndicate_block(&$sandbox): void {
  // If the Block module is enabled, then use a ConfigEntityUpdater to update
  // the block's configuration.
  if (\Drupal::moduleHandler()->moduleExists('block')) {
    \Drupal::classResolver(ConfigEntityUpdater::class)
      ->update($sandbox, 'block', function (BlockInterface $block): bool {
        // If this isn't a sitemap_syndicate block, skip it.
        if ($block->getPluginId() !== 'sitemap_syndicate') {
          // FALSE tells ConfigEntityUpdater not to save the block settings.
          return FALSE;
        }

        // If we get here, then we are looking at a sitemap_syndicate block.
        //
        // Figure out the new rss_feed_path...
        // 1. If there the frontpage plugin's rss setting is set, use that;
        // 2. Otherwise, use '/rss.xml'.
        $newRssFeedPath = \Drupal::configFactory()->get('sitemap.settings')->get('plugins.frontpage.settings.rss')
          ?? '/rss.xml';

        // Retrieve the block settings, set the rss_feed_path.
        $settings = $block->get('settings');
        $settings['rss_feed_path'] = $newRssFeedPath;
        $block->set('settings', $settings);

        // Return TRUE so ConfigEntityUpdater saves the Block.
        return TRUE;
      });
  }

  // Delete the global rss_front setting if it still exists.
  $globalConfig = \Drupal::configFactory()->getEditable('sitemap.settings');
  $globalConfig->clear('rss_front');
  $globalConfig->save();
}

/**
 * Removes the `cache` setting from all sitemap_syndicate blocks.
 */
function sitemap_post_update_remove_cache_setting_from_syndicate_blocks(&$sandbox): void {
  if (\Drupal::moduleHandler()->moduleExists('block')) {
    \Drupal::classResolver(ConfigEntityUpdater::class)
      ->update($sandbox, 'block', function (BlockInterface $block): bool {
        if ($block->getPluginId() === 'sitemap_syndicate') {
          $settings = $block->get('settings');
          unset($settings['cache']);
          $block->set('settings', $settings);
          return TRUE;
        }
        return FALSE;
      });
  }
}
