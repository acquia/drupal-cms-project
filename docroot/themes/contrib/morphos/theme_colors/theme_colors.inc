<?php

/**
 * @file
 * Theme colors settings.
 */

use Drupal\Core\Form\FormStateInterface;

require_once 'theme_colors_helper.inc';

/**
 * Builds the theme colors settings form.
 *
 * @param array &$form
 *   The form render array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form.
 */
function _theme_colors(&$form, FormStateInterface $form_state): void {
  // Schemes.
  $schemes = [
    'light' => [
      'label' => 'Light',
      'description' => 'Light color scheme',
    ],
    'dark' => [
      'label' => 'Dark',
      'description' => 'Dark color scheme',
    ],
  ];

  // Colors.
  $colors = [
    'standard' => [
      'label' => 'Standard',
      'description' => 'The main palette used for most UI elements.',
    ],
    'alternate' => [
      'label' => 'Alternate',
      'description' => 'An alternate palette for secondary UI elements.',
    ],
    'primary' => [
      'label' => 'Primary',
      'description' => 'The primary action palette used for main buttons and links.',
    ],
    'secondary' => [
      'label' => 'Secondary',
      'description' => 'The secondary action palette used for less prominent buttons and links.',
    ],
    'accent' => [
      'label' => 'Accent',
      'description' => 'An accent palette for special highlights and notifications.',
    ],
    'neutral' => [
      'label' => 'Neutral',
      'description' => 'A neutral palette for backgrounds and subtle elements.',
    ],
    'success' => [
      'label' => 'Success',
      'description' => 'A palette for success messages and indicators.',
    ],
    'warning' => [
      'label' => 'Warning',
      'description' => 'A palette for warning messages and indicators.',
    ],
    'error' => [
      'label' => 'Error',
      'description' => 'A palette for error messages and indicators.',
    ],
    'info' => [
      'label' => 'Info',
      'description' => 'A palette for informational messages and indicators.',
    ],
  ];

  // Define colors variables.
  $colors_variables = [
    'base' => [
      'background_base' => [
        'label' => 'Background base',
      ],
      'foreground_base' => [
        'label' => 'Foreground base',
      ],
      'link_base' => [
        'label' => 'Link base',
      ],
      'highlight_base' => [
        'label' => 'Highlight base',
      ],
    ],
    'derivatives' => [
      'background' => [
        'label' => 'Background',
        'base' => 'background_base',
      ],
      'background_strong' => [
        'label' => 'Background strong',
        'base' => 'background_base',
        'processor' => 'strengthen-20',
      ],
      'background_stronger' => [
        'label' => 'Background stronger',
        'base' => 'background_base',
        'processor' => 'strengthen-30',
      ],
      'background_weak' => [
        'label' => 'Background weak',
        'base' => 'background_base',
        'processor' => 'weaken-05',
      ],
      'background_weaker' => [
        'label' => 'Background weaker',
        'base' => 'background_base',
        'processor' => 'weaken-50',
      ],
      'foreground' => [
        'label' => 'Foreground',
        'base' => 'foreground_base',
        'contrast' => 'background',
      ],
      'foreground_weak' => [
        'label' => 'Foreground weak',
        'base' => 'foreground_base',
        'contrast' => 'background',
        'processor' => 'strengthen-20',
      ],
      'foreground_focus' => [
        'label' => 'Foreground focus',
        'base' => 'foreground_base',
        'contrast' => 'background',
      ],
      'link' => [
        'label' => 'Link',
        'base' => 'link_base',
        'contrast' => 'background',
      ],
      'link_hover' => [
        'label' => 'Link hover',
        'base' => 'link_base',
        'contrast' => 'background',
        'processor' => 'weaken-40',
      ],
      'link_active' => [
        'label' => 'Link active',
        'base' => 'link_base',
        'contrast' => 'background',
        'processor' => 'weaken-20',
      ],
      'link_contrast' => [
        'label' => 'Link contrast',
        'base' => 'link_base',
        'contrast' => 'link',
        'processor' => 'strengthen-90',
      ],
      'highlight' => [
        'label' => 'Highlight',
        'base' => 'highlight_base',
      ],
      'highlight_contrast' => [
        'label' => 'Highlight contrast',
        'base' => 'highlight_base',
        'contrast' => 'highlight',
        'processor' => 'strengthen-90',
      ],
    ],
  ];

  // Definition section.
  $form['definition'] = [
    '#type' => 'details',
    '#title' => t('Definition'),
    '#group' => 'morphos_settings',
    '#tree' => TRUE,
    '#weight' => -1,
  ];

  // Sub-theme acknowledgement checkbox.
  $form['definition']['subtheme_acknowledgement'] = [
    '#type' => 'checkbox',
    '#title' => t('I understand that Morphos should not be sub-themed'),
    '#description' => t('Learn how to customize the Morphos theme in the <a href="@url" target="_blank">documentation</a>.', [
      '@url' => 'https://docs.morpht.com/convivial-gov',
    ]),
    '#default_value' => _get("definition.subtheme_acknowledgement") ?? FALSE,
    '#weight' => -10,
  ];

  // Enable colors feature toggle.
  $form['definition']['enable'] = [
    '#type' => 'checkbox',
    '#title' => t('Enable theme color features'),
    '#default_value' => _get("definition.enable") ?? FALSE,
  ];
  $form['definition']['skins'] = [
    '#type' => 'textarea',
    '#title' => t('Skins'),
    '#description' => t('Skin is a collection of color schemes and colors. Use this pattern "key|Label" to define a skin. 1 line per skin.'),
    '#default_value' => _get("definition.skins") ?? '',
    '#rows' => 3,
  ];

  // Parse definitions.
  $skins = _get_skins(_get("definition.skins") ?? '');

  if (empty($skins)) {
    $messenger = \Drupal::messenger();
    $messenger->addMessage(t('Please define at least one skin in the definition.'), 'warning');
    return;
  }

  // Default skin and scheme options.
  $skin_options = array_map(function ($item) {
    return $item['label'];
  }, $skins);
  $scheme_options = array_map(function ($item) {
    return $item['label'];
  }, $schemes);

  $form['definition']['default']['skin'] = [
    '#type' => 'select',
    '#title' => t('Default skin'),
    '#default_value' => _get("definition.default.skin") ?? '',
    '#options' => $skin_options,
    '#empty_option' => t('- Select -'),
  ];
  $form['definition']['default']['scheme'] = [
    '#type' => 'select',
    '#title' => t('Default scheme'),
    '#default_value' => _get("definition.default.scheme") ?? '',
    '#options' => $scheme_options,
    '#empty_option' => t('- Select -'),
  ];

  // Structures for misc settings categories.
  $radius = [
    'boxes' => [
      'label' => 'Boxes',
      'description' => 'card, modal, alert',
      'default_value' => 0.5,
    ],
    'fields' => [
      'label' => 'Fields',
      'description' => 'button, input, select, tab',
      'default_value' => 0.25,
    ],
    'selectors' => [
      'label' => 'Selectors',
      'description' => 'checkbox, toggle, badge',
      'default_value' => 0.5,
    ],
  ];
  $effects = [
    'depth' => [
      'label' => 'Depth effect',
      'description' => '3D depth on fields & selectors',
      'default_value' => 1,
    ],
    'noise' => [
      'label' => 'Noise effect',
      'description' => 'Noise pattern on fields & selectors',
      'default_value' => 0,
    ],
  ];
  $sizes = [
    'fields' => [
      'label' => 'Fields',
      'description' => 'button, input, select, tab',
      'default_value' => 4.0,
    ],
    'selectors' => [
      'label' => 'Selectors',
      'description' => 'checkbox, toggle, badge',
      'default_value' => 4.0,
    ],
    'border-width' => [
      'label' => 'Border width',
      'description' => 'All components',
      'default_value' => 1.0,
    ],
  ];

  // Build skins settings.
  $form['skins'] = [
    '#tree' => TRUE,
  ];

  // Loop through skins.
  foreach ($skins as $skin_key => $skin_meta) {
    $skin_tabs = $skin_key . '_skin_tabs';
    $schemes_tabs = $skin_key . '_schemes_tabs';

    $form['skins'][$skin_key] = [
      '#type' => 'details',
      '#title' => $skin_meta['label'],
      '#group' => 'morphos_settings',
    ];
    $form['skins'][$skin_key][$skin_tabs] = [
      '#type' => 'horizontal_tabs',
    ];

    // Schemes.
    $form['skins'][$skin_key][$skin_tabs]['schemes'] = [
      '#type' => 'details',
      '#title' => t('Schemes'),
      '#weight' => 0,
      '#group' => $skin_tabs,
    ];

    $form_schemes =& $form['skins'][$skin_key][$skin_tabs]['schemes'][$schemes_tabs];
    $form_schemes = [
      '#type' => 'horizontal_tabs',
    ];

    foreach ($schemes as $scheme_key => $scheme_meta) {
      $form_schemes[$scheme_key] = [
        '#type' => 'details',
        '#title' => $scheme_meta['label'],
        '#group' => $schemes_tabs,
      ];

      // Colors.
      $colors_tabs = $skin_key . '_' . $scheme_key . '_scheme_colors_tabs';
      $form_colors =& $form['skins'][$skin_key][$skin_tabs]['schemes'][$schemes_tabs][$scheme_key]['colors'][$colors_tabs];
      $form_colors = [
        '#type' => 'horizontal_tabs',
      ];

      _theme_colors_colors($form_colors, $skin_key, $scheme_key, $colors, $colors_variables, $colors_tabs);
    }

    // Misc: radius, effects, sizes (per skin)
    $form_radius =& $form['skins'][$skin_key][$skin_tabs]['radius'];
    $form_effects =& $form['skins'][$skin_key][$skin_tabs]['effects'];
    $form_sizes =& $form['skins'][$skin_key][$skin_tabs]['sizes'];
    $form_settings =& $form['skins'][$skin_key][$skin_tabs]['settings'];

    $form_radius = [
      '#type' => 'details',
      '#title' => t('Radius'),
      '#group' => $skin_tabs,
      '#weight' => 1,
    ];
    $form_effects = [
      '#type' => 'details',
      '#title' => t('Effects'),
      '#group' => $skin_tabs,
      '#weight' => 2,
    ];
    $form_sizes = [
      '#type' => 'details',
      '#title' => t('Sizes'),
      '#group' => $skin_tabs,
      '#weight' => 3,
    ];
    $form_typography =& $form['skins'][$skin_key][$skin_tabs]['typography'];
    $form_typography = [
      '#type' => 'details',
      '#title' => t('Typography'),
      '#group' => $skin_tabs,
      '#weight' => 4,
    ];
    $form_settings = [
      '#type' => 'details',
      '#title' => t('Settings'),
      '#group' => $skin_tabs,
      '#weight' => 5,
    ];

    _theme_colors_radius($form_radius, $skin_key, $radius);
    _theme_colors_effects($form_effects, $skin_key, $effects);
    _theme_colors_sizes($form_sizes, $skin_key, $sizes);
    _theme_colors_typography($form_typography, $skin_key);
    _theme_colors_settings($form_settings, $skin_key);
  }

  // Form submit handler.
  $form['#validate'][] = '_theme_colors_form_validation';
  $form['#submit'] = $form['#submit'] ?? [];
  array_unshift($form['#submit'], '_theme_colors_form_submit');

  // Attach assets.
  $form['#attached']['library'][] = 'morphos/theme_colors';
}

/**
 * Builds color palette form elements per scheme.
 *
 * @param array &$form_colors
 *   The form colors render array.
 * @param string $skin_key
 *   The skin key.
 * @param string $scheme_key
 *   The color scheme key.
 * @param array $colors
 *   The color definitions.
 * @param array $colors_variables
 *   The color variable definitions including base and derivatives.
 * @param string $colors_tabs
 *   The tab group identifier for color tabs.
 */
function _theme_colors_colors(&$form_colors, $skin_key, $scheme_key, $colors, $colors_variables, $colors_tabs): void {
  foreach ($colors as $color_key => $color_meta) {
    $id = "{$skin_key}--{$scheme_key}--{$color_key}";
    $child_id_prefix = "{$id}--";
    $label = $color_meta['label'];

    $form_colors[$color_key] = [
      '#type' => 'details',
      '#title' => $label,
      '#id' => $id,
      '#attributes' => [
        'class' => ['palette'],
      ],
      '#group' => $colors_tabs,
    ];

    // Base colors wrapper.
    $form_colors[$color_key]['base'] = [
      '#title' => t('Base'),
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['palette__item']],
    ];

    // Derivatives colors wrapper.
    $form_colors[$color_key]['derivatives'] = [
      '#title' => t('Derivatives'),
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['palette__item']],
    ];

    // Is dark.
    $form_colors[$color_key]['base']['is_dark'] = [
      '#type' => 'checkbox',
      '#title' => t('Is dark'),
      '#id' => $child_id_prefix . 'is-dark',
      '#default_value' => _get("skins.$skin_key.schemes.$scheme_key.colors.$color_key.base.is_dark") ?? FALSE,
      '#description' => t('Identify whether this color variant is dark, required save before changing the colors'),
    ];

    // Build base colors.
    foreach ($colors_variables['base'] as $base_key => $base_meta) {
      $form_colors[$color_key]['base'][$base_key] = [
        '#type' => 'color',
        '#title' => $base_meta['label'],
        '#id' => $child_id_prefix . $base_key,
        '#default_value' => _get("skins.$skin_key.schemes.$scheme_key.colors.$color_key.base.$base_key") ?? '',
        '#description' => t('Setting this color will automatically cascade color down into the dependent color.'),
        '#attributes' => [
          'class' => ['color-base'],
          'data-color-skin' => $skin_key,
          'data-color-scheme' => $scheme_key,
          'data-color-key' => $color_key,
        ],
      ];
    }

    // Build derivative colors.
    $field_dep_name = "skins[{$skin_key}][{$skin_key}_skin_tabs]"
      . "[schemes][{$skin_key}_schemes_tabs]"
      . "[{$scheme_key}][colors][{$skin_key}_{$scheme_key}_scheme_colors_tabs]";

    foreach ($colors_variables['derivatives'] as $d_key => $d_meta) {
      $form_colors[$color_key]['derivatives'][$d_key] = [
        '#type' => 'color',
        '#title' => $d_meta['label'],
        '#id' => $child_id_prefix . $d_key,
        '#default_value' => _get("skins.$skin_key.schemes.$scheme_key.colors.$color_key.derivatives.$d_key") ?? '',
        '#attributes' => [
          'class' => ['color', 'color-derivative'],
          'data-color-dep' => $field_dep_name . "[{$color_key}][base][{$d_meta['base']}]",
          'data-color-processor' => $d_meta['processor'] ?? NULL,
        ],
        // Empty space for contrast rating.
        '#field_suffix' => ' ',
      ];

      if (!empty($d_meta['contrast'])) {
        $form_colors[$color_key]['derivatives'][$d_key]['#attributes']['class'][] = 'color-contrast';
        $form_colors[$color_key]['derivatives'][$d_key]['#attributes']['data-color-contrast'] = "{$field_dep_name}[{$color_key}][derivatives][{$d_meta['contrast']}]";
      }
    }

    // Template for example section.
    $example_template = "
      <div class='form-item'>
        <span class='form-item__label'>Example colors</span>
        <div
          class='color-example'
          data-color-background-color='{$field_dep_name}[COLOR_KEY][derivatives][background]'
          data-color-background-strong='{$field_dep_name}[COLOR_KEY][derivatives][background_strong]'
          data-color-background-weak='{$field_dep_name}[COLOR_KEY][derivatives][background_weak]'
        >
          <p>
            <span data-color-color='{$field_dep_name}[COLOR_KEY][derivatives][foreground]'>Text</span>
            <span data-color-color='{$field_dep_name}[COLOR_KEY][derivatives][foreground_weak]'>Alt Text</span>
            <span data-color-color='{$field_dep_name}[COLOR_KEY][derivatives][foreground_focus]'>Focus</span>
          </p>
          <p>
            <span data-color-color='{$field_dep_name}[COLOR_KEY][derivatives][link]'>Link</span>
            <span data-color-color='{$field_dep_name}[COLOR_KEY][derivatives][link_hover]'>Hover</span>
            <span data-color-color='{$field_dep_name}[COLOR_KEY][derivatives][link_active]'>Active</span>
          </p>
          <p>
            <span data-color-color='{$field_dep_name}[COLOR_KEY][derivatives][highlight]'>Highlight</span>
          </p>
        </div>
      </div>
    ";

    // Example markup.
    $form_colors[$color_key]['base']['example'] = [
      '#type' => 'markup',
      '#title' => t('Example'),
      '#markup' => str_replace([
        'SKIN_KEY',
        'SCHEME_KEY',
        'COLOR_KEY',
      ], [
        $skin_key,
        $scheme_key,
        $color_key,
      ], $example_template),
      '#weight' => 100,
    ];
  }

  // Import.
  $form_colors['import'] = [
    '#type' => 'details',
    '#title' => t('Import'),
    '#group' => $colors_tabs,
  ];

  $form_colors['import']['css_variables'] = [
    '#type' => 'textarea',
    '#title' => t('CSS variables'),
    '#id' => "daisyui-import-css-variables--{$skin_key}--{$scheme_key}",
    '#description' => t('Paste the DaisyUI theme CSS variables here to import the colors. Copy the CSS from theme generator here <a href=":url" target="_blank">https://daisyui.com/theme-generator</a>.', [
      ':url' => 'https://daisyui.com/theme-generator',
    ]),
    '#attributes' => [
      'data-import-skin' => $skin_key,
      'data-import-scheme' => $scheme_key,
    ],
  ];

  $form_colors['import']['no_submit_button'] = [
    '#type' => 'button',
    '#value' => t('Import'),
    '#submit_button' => FALSE,
    '#attributes' => [
      'onclick' => 'importDaisyUIColors(event); return false;',
      'type' => 'button',
      'data-import-skin' => $skin_key,
      'data-import-scheme' => $scheme_key,
    ],
  ];
}

/**
 * Builds radius settings form elements.
 *
 * @param array &$form_radius
 *   The form radius render array.
 * @param string $skin_key
 *   The skin key.
 * @param array $radius
 *   The radius definitions.
 */
function _theme_colors_radius(&$form_radius, $skin_key, $radius): void {
  foreach ($radius as $radius_key => $radius_meta) {
    $form_radius[$radius_key] = [
      '#type' => 'number',
      '#title' => $radius_meta['label'] ?? ucfirst($radius_key),
      '#description' => $radius_meta['description'] ?? '',
      '#default_value' => _get("skins.$skin_key.radius.$radius_key") ?? $radius_meta['default_value'],
      '#field_suffix' => 'rem',
      '#step' => 0.25,
      '#attributes' => ['min' => 0, 'max' => 100],
    ];
  }
}

/**
 * Builds effects settings form elements.
 *
 * @param array &$form_effects
 *   The form effects render array.
 * @param string $skin_key
 *   The skin key.
 * @param array $effects
 *   The effects definitions.
 */
function _theme_colors_effects(&$form_effects, $skin_key, $effects): void {
  foreach ($effects as $effect_key => $effect_meta) {
    $form_effects[$effect_key] = [
      '#type' => 'checkbox',
      '#title' => $effect_meta['label'] ?? ucfirst($effect_key),
      '#description' => $effect_meta['description'] ?? '',
      '#default_value' => _get("skins.$skin_key.effects.$effect_key") ?? $effect_meta['default_value'],
    ];
  }
}

/**
 * Builds sizes settings form elements.
 *
 * @param array &$form_sizes
 *   The form sizes render array.
 * @param string $skin_key
 *   The skin key.
 * @param array $sizes
 *   The sizes definitions.
 */
function _theme_colors_sizes(&$form_sizes, $skin_key, $sizes): void {
  foreach ($sizes as $size_key => $size_meta) {
    $form_sizes[$size_key] = [
      '#type' => 'number',
      '#title' => $size_meta['label'] ?? ucfirst($size_key),
      '#description' => $size_meta['description'] ?? '',
      '#default_value' => _get("skins.$skin_key.size.$size_key") ?? $size_meta['default_value'],
      '#field_suffix' => 'px',
      '#step' => 1,
      '#attributes' => ['min' => 0, 'max' => 100],
    ];
  }
}

/**
 * Builds typography settings form elements.
 *
 * @param array &$form_typography
 *   The form typography render array.
 * @param string $skin_key
 *   The skin key.
 */
function _theme_colors_typography(&$form_typography, $skin_key): void {
  // Imports.
  $form_typography['imports'] = [
    '#type' => 'textarea',
    '#title' => t('Imports'),
    '#default_value' => _get("skins.$skin_key.typography.imports") ?? '',
    '#description' => t('Line separated list of URLs of fonts to import. e.g. https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap'),
    '#rows' => 4,
  ];

  // Default font.
  $form_typography['default_font'] = [
    '#type' => 'select',
    '#title' => t('Default font'),
    '#default_value' => _get("skins.$skin_key.typography.default_font") ?? 'font-sans',
    '#description' => t('The default font to use for the site.'),
    '#options' => [
      '' => t('- Select -'),
      'font-sans' => t('Sans serif'),
      'font-serif' => t('Serif'),
      'font-mono' => t('Mono'),
    ],
  ];

  // Font families fieldset.
  $form_typography['fonts'] = [
    '#type' => 'fieldset',
    '#title' => t('Font families'),
  ];
  $form_typography['fonts']['font_sans'] = [
    '#type' => 'textfield',
    '#title' => t('Sans serif font'),
    '#default_value' => _get("skins.$skin_key.typography.fonts.font_sans") ?? '',
    '#description' => t('Font definition for sans serif fonts. e.g. "Inter", ui-sans-serif, system-ui, sans-serif'),
  ];
  $form_typography['fonts']['font_serif'] = [
    '#type' => 'textfield',
    '#title' => t('Serif font'),
    '#default_value' => _get("skins.$skin_key.typography.fonts.font_serif") ?? '',
    '#description' => t('Font definition for serif fonts. e.g. "Merriweather", ui-serif, Georgia, serif'),
  ];
  $form_typography['fonts']['font_mono'] = [
    '#type' => 'textfield',
    '#title' => t('Mono font'),
    '#default_value' => _get("skins.$skin_key.typography.fonts.font_mono") ?? '',
    '#description' => t('Font definition for mono fonts. e.g. "Fira Code", ui-monospace, SFMono-Regular, monospace'),
  ];

  // Headings h1-h6.
  $headings = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
  foreach ($headings as $heading) {
    $heading_upper = strtoupper($heading);
    $form_typography[$heading] = [
      '#type' => 'fieldset',
      '#title' => t('@heading', ['@heading' => $heading_upper]),
    ];
    $form_typography[$heading]["{$heading}_font"] = [
      '#type' => 'textfield',
      '#title' => t('Font'),
      '#default_value' => _get("skins.$skin_key.typography.$heading.{$heading}_font") ?? '',
      '#description' => t('e.g. "Inter", ui-sans-serif, sans-serif'),
    ];
    $form_typography[$heading]["{$heading}_size"] = [
      '#type' => 'number',
      '#title' => t('Size'),
      '#default_value' => _get("skins.$skin_key.typography.$heading.{$heading}_size") ?? '',
      '#description' => t('@heading font size in rem, e.g. 3', ['@heading' => $heading_upper]),
      '#field_suffix' => 'rem',
      '#step' => 'any',
      '#attributes' => ['min' => 0, 'max' => 10],
    ];
    $form_typography[$heading]["{$heading}_tracking"] = [
      '#type' => 'number',
      '#title' => t('Tracking'),
      '#default_value' => _get("skins.$skin_key.typography.$heading.{$heading}_tracking") ?? '',
      '#description' => t('@heading tracking (letter spacing) e.g. -0.05', ['@heading' => $heading_upper]),
      '#step' => 'any',
      '#field_suffix' => 'em',
    ];
    $form_typography[$heading]["{$heading}_leading"] = [
      '#type' => 'number',
      '#title' => t('Leading'),
      '#default_value' => _get("skins.$skin_key.typography.$heading.{$heading}_leading") ?? '',
      '#description' => t('@heading leading (line spacing) e.g. 1.2', ['@heading' => $heading_upper]),
      '#step' => 'any',
      '#attributes' => ['min' => 0, 'max' => 10],
    ];
    $form_typography[$heading]["{$heading}_weight"] = [
      '#type' => 'number',
      '#title' => t('Weight'),
      '#default_value' => _get("skins.$skin_key.typography.$heading.{$heading}_weight") ?? '',
      '#description' => t('@heading weight (300, 400, 600, 700)', ['@heading' => $heading_upper]),
      '#step' => 100,
      '#attributes' => ['min' => 100, 'max' => 900],
    ];
  }

  // Display fields.
  $form_typography['display'] = [
    '#type' => 'fieldset',
    '#title' => t('Display'),
  ];
  $form_typography['display']['display_font'] = [
    '#type' => 'textfield',
    '#title' => t('Font'),
    '#default_value' => _get("skins.$skin_key.typography.display.display_font") ?? '',
    '#description' => t('e.g. "Inter", ui-sans-serif, sans-serif'),
  ];
  $form_typography['display']['display_size'] = [
    '#type' => 'number',
    '#title' => t('Size'),
    '#default_value' => _get("skins.$skin_key.typography.display.display_size") ?? '',
    '#description' => t('Display font size in rem, e.g. 3'),
    '#step' => 'any',
    '#field_suffix' => 'rem',
    '#attributes' => ['min' => 0, 'max' => 10],
  ];
  $form_typography['display']['display_tracking'] = [
    '#type' => 'number',
    '#title' => t('Tracking'),
    '#default_value' => _get("skins.$skin_key.typography.display.display_tracking") ?? '',
    '#description' => t('Display tracking (letter spacing) e.g. -0.05'),
    '#step' => 'any',
    '#field_suffix' => 'em',
  ];
  $form_typography['display']['display_leading'] = [
    '#type' => 'number',
    '#title' => t('Leading'),
    '#default_value' => _get("skins.$skin_key.typography.display.display_leading") ?? '',
    '#description' => t('Display leading (line spacing) e.g. 1.2'),
    '#step' => 'any',
    '#attributes' => ['min' => 0, 'max' => 10],
  ];
  $form_typography['display']['display_weight'] = [
    '#type' => 'number',
    '#title' => t('Weight'),
    '#default_value' => _get("skins.$skin_key.typography.display.display_weight") ?? '',
    '#description' => t('Display weight (300, 400, 600, 700)'),
    '#step' => 100,
    '#attributes' => ['min' => 100, 'max' => 900],
  ];
}

/**
 * Builds general skin settings form elements.
 *
 * @param array &$form_settings
 *   The form settings render array.
 * @param string $skin_key
 *   The skin key.
 */
function _theme_colors_settings(&$form_settings, $skin_key): void {
  $form_settings['header_menu_style'] = [
    '#type' => 'select',
    '#title' => t('Header menu style'),
    '#description' => t('The style of the menu displayed in the header region.'),
    '#default_value' => _get("skins.$skin_key.settings.header_menu_style") ?? 'dropdown',
    '#options' => [
      'simple' => t('Simple'),
      'dropdown' => t('Dropdown'),
      'megamenu' => t('Megamenu'),
    ],
  ];

  $form_settings['header_menu_megamenu_cols'] = [
    '#type' => 'select',
    '#title' => t('Mega menu columns'),
    '#description' => t('The number of columns to show for the mega menu.'),
    '#default_value' => _get("skins.$skin_key.settings.header_menu_megamenu_cols") ?? '4',
    '#options' => [
      '2' => t('2 columns'),
      '3' => t('3 columns'),
      '4' => t('4 columns'),
      '5' => t('5 columns'),
      '6' => t('6 columns'),
    ],
    '#states' => [
      'visible' => [
        ':input[name="skins[' . $skin_key . '][' . $skin_key . '_skin_tabs][settings][header_menu_style]"]' => ['value' => 'megamenu'],
      ],
    ],
  ];

  $form_settings['header_sticky'] = [
    '#type' => 'checkbox',
    '#title' => t('Sticky header'),
    '#description' => t('Enable to make the header sticky on the top of the page.'),
    '#default_value' =>  _get("skins.$skin_key.settings.header_sticky") ?? FALSE,
  ];

  $form_settings['footer_sticky'] = [
    '#type' => 'checkbox',
    '#title' => t('Sticky footer'),
    '#description' => t('Enable to position the footer on the bottom of the page.'),
    '#default_value' =>  _get("skins.$skin_key.settings.footer_sticky") ?? FALSE,
  ];

  $form_settings['apply'] = [
    '#type' => 'textarea',
    '#title' => t('Apply skin'),
    '#default_value' => _get("skins.$skin_key.settings.apply"),
    '#description' => t('Apply skin to section of the site, e.g. path|/campaign*. 1 line per strategy.'),
    '#rows' => 3,
  ];
}

/**
 * Submit handler that cleans skin form data before saving.
 *
 * @param array $form
 *   The form render array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form.
 */
function _theme_colors_form_submit($form, FormStateInterface $form_state): void {
  // Get skins data, stop if not exists.
  $skins = $form_state->getValue(['skins']);
  if (!$skins) {
    return;
  }

  // Clean skins data before save.
  $clean_skins = [];
  foreach ($skins as $skin_key => $skin_data) {
    $skin_tabs_key = $skin_key . '_skin_tabs';
    if (isset($skin_data[$skin_tabs_key])) {
      $skin_data = array_merge($skin_data, $skin_data[$skin_tabs_key]);
      unset($skin_data[$skin_tabs_key]);
    }

    // Remove active tab keys.
    $skin_data = array_filter($skin_data, function ($key) {
      return !str_ends_with($key, '_active_tab');
    }, ARRAY_FILTER_USE_KEY);

    // Clean schemes.
    if (isset($skin_data['schemes'])) {
      $schemes_tabs_key = $skin_key . '_schemes_tabs';
      if (isset($skin_data['schemes'][$schemes_tabs_key])) {
        $skin_data['schemes'] = array_merge($skin_data['schemes'], $skin_data['schemes'][$schemes_tabs_key]);
        unset($skin_data['schemes'][$schemes_tabs_key]);
      }

      // Remove active tab keys.
      $skin_data['schemes'] = array_filter($skin_data['schemes'], function ($key) {
        return !str_ends_with($key, '_active_tab');
      }, ARRAY_FILTER_USE_KEY);

      // Loop through schemes.
      foreach ($skin_data['schemes'] as $scheme_key => &$scheme_data) {
        // Clean colors.
        if (isset($scheme_data['colors'])) {
          $colors_tabs_key = $skin_key . '_' . $scheme_key . '_scheme_colors_tabs';
          if (isset($scheme_data['colors'][$colors_tabs_key])) {
            $scheme_data['colors'] = array_merge($scheme_data['colors'], $scheme_data['colors'][$colors_tabs_key]);
            unset($scheme_data['colors'][$colors_tabs_key]);
          }

          // Remove import section.
          unset($scheme_data['colors']['import']);

          // Remove active tab keys.
          $scheme_data['colors'] = array_filter($scheme_data['colors'], function ($key) {
            return !str_ends_with($key, '_active_tab');
          }, ARRAY_FILTER_USE_KEY);
        }
      }
    }

    // Save cleaned skin data.
    $clean_skins[$skin_key] = $skin_data;
  }

  // Set skins settings from cleaned data.
  $form_state->setValue(['skins'], $clean_skins);
}

/**
 * Validation handler for typography import URLs.
 *
 * @param array &$form
 *   The form render array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form.
 */
function _theme_colors_form_validation(&$form, FormStateInterface $form_state) {
  $skins = $form_state->getValue("skins");

  if ($skins) {
    foreach ($skins as $skin_key => $skin) {
      $skin_tab = $skin_key . '_skin_tabs';
      $imports_raw = $skins[$skin_key][$skin_tab]['typography']['imports'];
      $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $imports_raw)));

      foreach ($lines as $line) {
        $url = filter_var($line, FILTER_VALIDATE_URL);
        if (!$url) {
          $form_state->setErrorByName('Imports', t('Typography import must contain valid URLs only. Error in skin "@skin": "@line"', [
            '@skin' => $skin_key,
            '@line' => $line,
          ]));
        }
      }
    }
  }
}
