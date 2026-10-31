<?php

/**
 * @file
 * Hooks and documentation related to BPMN IO module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the list of supported themes for the BPMN IO module.
 *
 * This hook allows modules to modify the list of themes that are supported
 * by the BPMN IO module. Modules can add, remove, or modify theme entries.
 *
 * @param string[] $supported_themes
 *   An array of supported theme names.
 */
function hook_bpmn_io_supported_themes_alter(array &$supported_themes): void {
  // Add support for a custom theme.
  $supported_themes[] = 'my_custom_theme';
}

/**
 * @} End of "addtogroup hooks"
 */
