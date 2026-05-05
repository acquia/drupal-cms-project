<?php

/**
 * @file
 * Theme UI variables.
 *
 * Export CSS variables based on Theme colors settings.
 */

use Drupal\Core\Render\Markup;

require_once 'theme_colors_helper.inc';

/**
 * Attaches CSS variable style tags to the page based on theme color settings.
 *
 * @param array &$attachments
 *   The page attachments render array.
 */
function _theme_colors_variables(&$attachments): void {
  $skins = _get("skins");

  if (empty($skins)) {
    return;
  }

  // Initialize variables.
  $default_skin = _get("definition.default.skin") ?? array_key_first($skins);
  $default_scheme = _get("definition.default.scheme") ?? 'light';

  // Get active skin base on skin settings and strategies.
  $active_skin = _get_apply_skin($skins) ?? $default_skin;

  // Build CSS variables for the active skin.
  $skin = $skins[$active_skin];
  $schemes = $skin['schemes'];
  $css_variables = '';

  // Set props variables.
  $props_variables = _build_props_variables($skin);
  $css_variables .= ":root{{$props_variables}}";

  // Set typography variables.
  $typography_variables = _build_typography_variables($skin);
  $css_variables .= "{$typography_variables}";

  // Set DaisyUI variables.
  $daisyui_variables = _build_daisyui_variables($active_skin, $schemes, $default_scheme);
  $css_variables .= $daisyui_variables;

  // Set colors variables.
  $schemes_variables = _build_colors_variables($schemes, $default_scheme);
  $css_variables .= $schemes_variables;

  // Attach font imports as a separate style tag (@import must precede rules).
  $font_imports = _build_typography_imports($skin);
  if (!empty($font_imports)) {
    $attachments['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => Markup::create($font_imports),
      ],
      'theme-typography-imports',
      ['weight' => 99],
    ];
  }

  // Attach CSS variables.
  if ($css_variables !== '') {
    $attachments['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#attributes' => ['type' => 'text/css'],
        '#value' => $css_variables,
      ],
      'theme-colors',
      ['weight' => 100],
    ];
  }
}

/**
 * Builds CSS variables for radius, effects, and sizes.
 *
 * @param array $skin
 *   The skin configuration array containing radius, effects, and sizes.
 *
 * @return string
 *   A string of CSS variable declarations.
 */
function _build_props_variables($skin): string {
  // Initialize variables.
  $radius = $skin['radius'];
  $effects = $skin['effects'];
  $sizes = $skin['sizes'];
  $variables = '';

  // Set radius.
  foreach ($radius as $rk => $rv) {
    $radius_variable = match ($rk) {
      'selectors' => '--radius-selector',
      'fields' => '--radius-field',
      'boxes' => '--radius-box',
      default => '--radius-' . str_replace('_', '-', $rk),
    };
    // phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found
    $variables .= "{$radius_variable}:{$rv}" . "rem;";
  }

  // Set effects.
  foreach ($effects as $ek => $ev) {
    $effect_variable = '--' . str_replace('_', '-', $ek);
    $effect_value = $ev ? '1' : '0';
    $variables .= "{$effect_variable}:{$effect_value};";
  }

  // Set sizes.
  foreach ($sizes as $sk => $sv) {
    $size_variable = match ($sk) {
      'selectors' => '--size-selector',
      'fields' => '--size-field',
      'border-width' => '--border',
      default => '--size-' . str_replace('_', '-', $sk),
    };
    // phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found
    $variables .= "{$size_variable}:{$sv}" . "px;";
  }

  // Megamenu columns.
  $megamenu_cols = $skin['settings']['header_menu_megamenu_cols'] ?? '4';
  $variables .= "--megamenu-cols:{$megamenu_cols};";

  return $variables;
}

/**
 * Builds CSS variables for color schemes.
 *
 * @param array $schemes
 *   An associative array of color schemes.
 * @param string $default_scheme
 *   The default scheme key.
 *
 * @return string
 *   A string of CSS variable declarations scoped to data-scheme selectors.
 */
function _build_colors_variables($schemes, $default_scheme): string {
  $variables = '';

  foreach ($schemes as $scheme_key => $scheme) {
    // Set colors.
    $colors = $scheme['colors'];
    $color_variables = '';

    foreach ($colors as $color_key => $color) {
      // Color class and wrapper.
      $pattern = ".color--" . str_replace('_', '-', $color_key);
      $color_class = "[data-scheme=\"{$scheme_key}\"] {$pattern}";

      // Set standard color on :root for default scheme to allow
      // usage without data-scheme attribute, and also generate
      // standard color variables for all schemes for consistency
      // and fallback.
      if ($color_key === 'standard') {
        if ($scheme_key === $default_scheme) {
          $color_class = ":root,[data-scheme=\"{$scheme_key}\"],[data-scheme=\"{$scheme_key}\"] {$pattern}";
        }
        else {
          $color_class = "[data-scheme=\"{$scheme_key}\"],[data-scheme=\"{$scheme_key}\"] {$pattern}";
        }
      }

      // Loop though derivative colors.
      $derivatives = $color['derivatives'] ?? [];
      $derivatives_variables = '';

      foreach ($derivatives as $derivative_key => $derivative_value) {
        // Set derivative variable.
        $derivative_variable = '--' . str_replace('_', '-', $derivative_key);
        $derivatives_variables .= "$derivative_variable:$derivative_value;";

        // Generate addition variables for background RGBA variants.
        if ($derivative_key === 'background') {
          $rgb = _hex_to_rgb($derivative_value);

          // Create RGBA variants with different alpha levels.
          if (!empty($rgb)) {
            for ($i = 1; $i <= 10; $i++) {
              $alpha = $i / 10;
              // Format alpha string.
              $alpha_str = str_pad(number_format($alpha * 100, 0, ''), 3, '0', STR_PAD_LEFT);
              $derivatives_variables .= "--background-{$alpha_str}:rgba({$rgb['r']},{$rgb['g']},{$rgb['b']},{$alpha});";
            }
          }
        }
      }

      $color_variables .= "{$color_class}{{$derivatives_variables}}";
    }

    // Append color variables to scheme.
    $variables .= $color_variables;
  }

  return $variables;
}

/**
 * Builds DaisyUI color CSS variables from theme color schemes.
 *
 * @param string $active_skin
 *   The active skin key.
 * @param array $schemes
 *   An associative array of color schemes.
 * @param string $default_scheme
 *   The default scheme key.
 *
 * @return string
 *   A string of DaisyUI CSS variable declarations.
 */
function _build_daisyui_variables($active_skin, $schemes, $default_scheme): string {
  // Mapping of daisyUI color names to theme color derivatives.
  $mapping = [
    'base-100' => 'standard.derivatives.background',
    'base-200' => 'standard.derivatives.background_weak',
    'base-300' => 'standard.derivatives.background_weaker',
    'base-content' => 'standard.derivatives.foreground',
    'primary' => 'primary.derivatives.background',
    'primary-content' => 'primary.derivatives.foreground',
    'secondary' => 'secondary.derivatives.background',
    'secondary-content' => 'secondary.derivatives.foreground',
    'accent' => 'accent.derivatives.background',
    'accent-content' => 'accent.derivatives.foreground',
    'neutral' => 'neutral.derivatives.background',
    'neutral-content' => 'neutral.derivatives.foreground',
    'info' => 'info.derivatives.background',
    'info-content' => 'info.derivatives.foreground',
    'success' => 'success.derivatives.background',
    'success-content' => 'success.derivatives.foreground',
    'warning' => 'warning.derivatives.background',
    'warning-content' => 'warning.derivatives.foreground',
    'error' => 'error.derivatives.background',
    'error-content' => 'error.derivatives.foreground',
  ];

  // Initialize variables.
  $variables = '';

  foreach ($schemes as $scheme_key => $scheme) {
    $daisyui_variables = '';

    foreach ($mapping as $key => $map) {
      $value = _get("skins.$active_skin.schemes.$scheme_key.colors.$map") ?? NULL;
      if ($value) {
        $daisyui_variables .= "--color-{$key}:{$value};";
      }
    }

    if ($scheme_key === $default_scheme) {
      $variables .= ":root,[data-scheme=\"{$scheme_key}\"]{{$daisyui_variables}}";
    }
    else {
      $variables .= "[data-scheme=\"{$scheme_key}\"]{{$daisyui_variables}}";
    }
  }

  return $variables;
}

/**
 * Builds @import statements for web fonts.
 *
 * @param array $skin
 *   The skin configuration array containing typography settings.
 *
 * @return string
 *   A string of CSS @import statements for font URLs.
 */
function _build_typography_imports($skin): string {
  $typography = $skin['typography'] ?? [];
  $imports_raw = $typography['imports'] ?? '';

  if (empty(trim($imports_raw))) {
    return '';
  }

  $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $imports_raw)));
  $imports = '';

  // Validate URL.
  foreach ($lines as $line) {
    $url = filter_var($line, FILTER_VALIDATE_URL);
    if ($url) {
      $imports .= "@import url('{$url}');";
    }
  }

  return $imports;
}

/**
 * Builds CSS variables for typography settings.
 *
 * @param array $skin
 *   The skin configuration array containing typography settings.
 *
 * @return string
 *   A string of CSS variable declarations for fonts and headings.
 */
function _build_typography_variables($skin): string {
  $typography = $skin['typography'] ?? [];
  $variables = '';

  // Default font.
  $default_variables = '';
  $default_font = $typography['default_font'] ?? '';
  if (!empty($default_font)) {
    $default_variables .= "--default-font-family:var(--{$default_font});";
  }

  // Font families (may be nested under 'fonts' fieldset).
  $fonts = $typography['fonts'] ?? $typography;
  $font_fields = ['font_sans', 'font_serif', 'font_mono'];
  foreach ($font_fields as $field) {
    $value = $fonts[$field] ?? $typography[$field] ?? '';
    if (!empty(trim($value))) {
      $css_var = '--' . str_replace('_', '-', $field);
      $default_variables .= "{$css_var}:{$value};";
    }
  }

  $variables .= ":root{{$default_variables}}";

  // Headings h1-h6.
  $headings = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
  foreach ($headings as $heading) {
    $heading_variables = '';

    // May be nested under fieldset or flat.
    $heading_data = $typography[$heading] ?? $typography;

    // Font.
    $font = $heading_data["{$heading}_font"] ?? $typography["{$heading}_font"] ?? '';
    if (!empty(trim($font))) {
      $heading_variables .= "--font-family:{$font};";
    }

    // Size.
    $size = $heading_data["{$heading}_size"] ?? $typography["{$heading}_size"] ?? '';
    if (!empty($size) && $size !== '') {
      $heading_variables .= "--font-size:{$size}rem;";
    }

    // Tracking.
    $tracking = $heading_data["{$heading}_tracking"] ?? $typography["{$heading}_tracking"] ?? '';
    if ($tracking !== '' && $tracking !== NULL) {
      $heading_variables .= "--font-tracking:{$tracking}em;";
    }

    // Leading.
    $leading = $heading_data["{$heading}_leading"] ?? $typography["{$heading}_leading"] ?? '';
    if (!empty($leading) && $leading !== '') {
      $heading_variables .= "--font-leading:{$leading}em;";
    }

    // Weight.
    $weight = $heading_data["{$heading}_weight"] ?? $typography["{$heading}_weight"] ?? '';
    if (!empty($weight) && $weight !== '') {
      $heading_variables .= "--font-weight:{$weight};";
    }

    $variables .= "{$heading}{{$heading_variables}}";
  }

  // Display font.
  $display_variables = '';

  // Display (may be nested under 'display' fieldset).
  $display_data = $typography['display'] ?? $typography;

  // Display font.
  $display_font = $display_data['display_font'] ?? $typography['display_font'] ?? '';
  if (!empty(trim($display_font))) {
    $display_variables .= "--font-family:{$display_font};";
  }

  // Display size.
  $display_size = $display_data['display_size'] ?? $typography['display_size'] ?? '';
  if (!empty($display_size) && $display_size !== '') {
    $display_variables .= "--font-size:{$display_size}rem;";
  }

  // Display tracking.
  $display_tracking = $display_data['display_tracking'] ?? $typography['display_tracking'] ?? '';
  if ($display_tracking !== '' && $display_tracking !== NULL) {
    $display_variables .= "--font-tracking:{$display_tracking}em;";
  }

  // Display leading.
  $display_leading = $display_data['display_leading'] ?? $typography['display_leading'] ?? '';
  if (!empty($display_leading) && $display_leading !== '') {
    $display_variables .= "--font-leading:{$display_leading};";
  }

  // Display weight.
  $display_weight = $display_data['display_weight'] ?? $typography['display_weight'] ?? '';
  if (!empty($display_weight) && $display_weight !== '') {
    $display_variables .= "--font-weight:{$display_weight};";
  }

  $variables .= ".font-display{{$display_variables}}";

  return $variables;
}

/**
 * Determines which skin to apply based on strategy rules.
 *
 * @param array $skins
 *   An associative array of skin configurations.
 *
 * @return string|null
 *   The skin key to apply, or NULL if no strategy matches.
 */
function _get_apply_skin($skins): string|null {
  foreach ($skins as $skin_key => $skin) {
    $apply_settings = $skin['settings']['apply'];
    $lines = array_filter(preg_split('/\r\n|\r|\n/', $apply_settings));

    foreach ($lines as $line) {
      $line = trim($line);
      $parts = explode('|', $line);
      $strategy = trim($parts[0]);
      $params = array_map('trim', explode(",", $parts[1]));

      if (_apply_strategy($strategy, $params)) {
        return $skin_key;
      }
    }
  }

  return NULL;
}

/**
 * Dispatches a skin-apply strategy by name.
 *
 * @param string $strategy
 *   The strategy name (e.g., "path").
 * @param array $params
 *   The strategy parameters.
 *
 * @return bool
 *   TRUE if the strategy matches, FALSE otherwise.
 */
function _apply_strategy($strategy, $params): bool {
  return match ($strategy) {
    "path" => _apply_strategy_path($params),
    default => FALSE,
  };
}

/**
 * Evaluates the path-based skin-apply strategy.
 *
 * @param array $params
 *   The strategy parameters. First element is the path pattern,
 *   which may end with '*' for wildcard matching.
 *
 * @return bool
 *   TRUE if the current request path matches the pattern.
 */
function _apply_strategy_path($params): bool {
  $current_path = \Drupal::request()->getPathInfo();

  // Expect only 1 parameter.
  $pattern = $params[0] ?? '';

  // Remove trailing '*' for wildcard support.
  if (str_ends_with($pattern, '*')) {
    $base = rtrim($pattern, '*');
    if (str_starts_with($current_path, $base)) {
      return TRUE;
    }
  }
  // Exact match if no wildcard.
  elseif ($current_path === $pattern) {
    return TRUE;
  }

  return FALSE;
}
