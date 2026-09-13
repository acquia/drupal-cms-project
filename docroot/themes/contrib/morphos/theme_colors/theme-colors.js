/*!
 * Project Name: Morphos (morpht/morphos)
 * File: theme_colors/theme-colors.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

(function ($, Drupal) {
  Drupal.behaviors.morphosThemeSettingColors = {
    attach: function (context, settings) {
      let lines = new Map();

      // Function to process color based on the strategy
      function processColor(color, processor, isDark) {
        const [strategy, valueString] = processor.split('-');
        let value = (parseInt(valueString, 10) / 100) * (isDark ? 1 : -1);

        if (strategy === 'strengthen') {
          return pSBC(-value, color);
        }

        return pSBC(value, color);
      }

      // Function to handle color change
      function handleColorChange(event) {
        const parent = event.target;
        const parentColor = parent.value;
        const parentName = parent.getAttribute('name');
        parent.setAttribute('value', parentColor);

        // Find is dark element
        const colorSkin = parent.dataset.colorSkin;
        const colorScheme = parent.dataset.colorScheme;
        const colorKey = parent.dataset.colorKey;
        const isDarkId = `#${colorSkin}--${colorScheme}--${colorKey}--is-dark`;
        const isDarkElement = document.querySelector(isDarkId);
        const isDark = isDarkElement.checked ?? false;

        // Find all dependent elements
        const dependents = document.querySelectorAll(
          `[data-color-dep="${parentName}"]`,
        );
        dependents.forEach((dependent) => {
          const processor = dependent.getAttribute('data-color-processor');
          const processedColor = processor
            ? processColor(parentColor, processor, isDark)
            : parentColor;
          dependent.value = processedColor;
          dependent.setAttribute('value', processedColor);

          // Trigger the input event.
          const inputEvent = new Event('input', { bubbles: true });
          dependent.dispatchEvent(inputEvent);
        });

        // Refresh all color examples.
        calculateAllColorExamples();
      }

      // Function to handle color change for derivative colors.
      function handleDerivativeColorChange(event) {
        // Refresh all color examples.
        calculateAllColorExamples();
        // Refresh all color contrasts.
        calculateAllColorContrasts();
      }

      // Function to draw lines to dependents from base on focus.
      function handleFocus(event) {
        const parent = event.target;
        const parentName = parent.getAttribute('name');
        const dependents = document.querySelectorAll(
          `[data-color-dep="${parentName}"]`,
        );

        dependents.forEach((dependent) => {
          if (!lines.has(dependent)) {
            const line = new LeaderLine(parent, dependent, {
              color: '#000',
              path: 'fluid',
              startPlug: 'disc',
              endPlug: 'arrow',
              dash: true,
            });
            lines.set(dependent, line);
          }
        });
      }

      // Function to clean lines to dependents from base on blur.
      function handleBlur(event) {
        const parent = event.target;
        const parentName = parent.getAttribute('name');
        const dependents = document.querySelectorAll(
          `[data-color-dep="${parentName}"]`,
        );

        dependents.forEach((dependent) => {
          if (lines.has(dependent)) {
            lines.get(dependent).remove();
            lines.delete(dependent);
          }
        });
      }

      // Function to convert hex color code to RGB
      function hexToRgb(hex) {
        const bigint = parseInt(hex.slice(1), 16);
        const r = (bigint >> 16) & 255;
        const g = (bigint >> 8) & 255;
        const b = bigint & 255;
        return [r, g, b];
      }

      // Function to handle color contrast change.
      function handleColorContrastChange(element) {
        const contrastElementName = element.getAttribute('data-color-contrast');
        const contrastElement = document.querySelector(
          `input[name="${contrastElementName}"]`,
        );
        // Ensure contrast element exists.
        if (!contrastElement) {
          return;
        }

        let contrastValue = contrast(
          hexToRgb(contrastElement.value),
          hexToRgb(element.value),
        );
        contrastValue = contrastValue.toFixed(1);
        const rating = getContrastRating(contrastValue);
        const elementSuffix =
          element.nextElementSibling.closest('.form-item__suffix');

        if (elementSuffix) {
          elementSuffix.innerHTML = `Contrast: ${contrastValue}`;
          elementSuffix.innerHTML = `Contrast: ${contrastValue}, ${rating}`;
        }
      }

      // Function to get the contrast rating
      function getContrastRating(contrastValue) {
        if (contrastValue < 4.5) {
          return 'Fail';
        } else if (contrastValue > 7) {
          return 'AAA';
        } else {
          return 'AA';
        }
      }

      // Function to calculate contrast for all color-contrast elements
      function calculateAllColorContrasts() {
        const colorContrastElements = document.querySelectorAll(
          'input.color-contrast',
        );
        colorContrastElements.forEach((element) => {
          handleColorContrastChange(element);
        });
      }

      // Function to calculate examples for all color-example elements
      function calculateAllColorExamples() {
        const colorExamples = document.querySelectorAll('.color-example');

        colorExamples.forEach((colorExample) => {
          const bgColorElementName = colorExample.getAttribute(
            'data-color-background-color',
          );
          const bgStrongColorElementName = colorExample.getAttribute(
            'data-color-background-strong',
          );

          if (bgColorElementName) {
            const bgColorElement = document.querySelector(
              `[name="${bgColorElementName}"]`,
            );
            const bgStrongColorElement = document.querySelector(
              `[name="${bgStrongColorElementName}"]`,
            );

            if (bgStrongColorElement) {
              colorExample.style.background = `linear-gradient(45deg, ${bgColorElement.value}, ${bgStrongColorElement.value})`;
            } else if (bgColorElement) {
              colorExample.style.backgroundColor = bgColorElement.value;
            }
          }

          colorExample
            .querySelectorAll('[data-color-color]')
            .forEach((element) => {
              const colorElementName = element.getAttribute('data-color-color');
              if (colorElementName) {
                const colorElement = document.querySelector(
                  `[name="${colorElementName}"]`,
                );
                if (colorElement) {
                  element.style.color = colorElement.value;
                }
              }
            });
        });
      }

      // Add event listeners to all color-base elements
      const baseColorElements = document.querySelectorAll(
        '.color-base',
        context,
      );

      baseColorElements.forEach((element) => {
        element.addEventListener('change', handleColorChange);
        element.addEventListener('focus', handleFocus);
        element.addEventListener('blur', handleBlur);
      });

      // Add event listeners to all color-derivative elements
      const derivativeColorElements = document.querySelectorAll(
        '.color-derivative',
        context,
      );

      derivativeColorElements.forEach((element) => {
        element.addEventListener('input', handleDerivativeColorChange);
      });

      // Calculate contrast for all color-contrast elements on page load
      calculateAllColorContrasts();

      // Calculate examples for all color-example elements
      calculateAllColorExamples();
    },
  };

  const RED = 0.2126;
  const GREEN = 0.7152;
  const BLUE = 0.0722;
  const GAMMA = 2.4;

  function luminance(r, g, b) {
    var a = [r, g, b].map((v) => {
      v /= 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, GAMMA);
    });
    return a[0] * RED + a[1] * GREEN + a[2] * BLUE;
  }

  function contrast(rgb1, rgb2) {
    var lum1 = luminance(...rgb1);
    var lum2 = luminance(...rgb2);
    var brightest = Math.max(lum1, lum2);
    var darkest = Math.min(lum1, lum2);
    return (brightest + 0.05) / (darkest + 0.05);
  }

  const pSBC = (p, c0, c1, l) => {
    let r,
      g,
      b,
      P,
      f,
      t,
      h,
      i = parseInt,
      m = Math.round,
      a = typeof c1 == 'string';
    if (
      typeof p != 'number' ||
      p < -1 ||
      p > 1 ||
      typeof c0 != 'string' ||
      (c0[0] != 'r' && c0[0] != '#') ||
      (c1 && !a)
    ) {
      return null;
    }
    if (!this.pSBCr) {
      this.pSBCr = (d) => {
        let n = d.length,
          x = {};
        if (n > 9) {
          (([r, g, b, a] = d = d.split(',')), (n = d.length));
          if (n < 3 || n > 4) {
            return null;
          }
          ((x.r = i(r[3] == 'a' ? r.slice(5) : r.slice(4))),
            (x.g = i(g)),
            (x.b = i(b)),
            (x.a = a ? parseFloat(a) : -1));
        } else {
          if (n == 8 || n == 6 || n < 4) {
            return null;
          }
          if (n < 6) {
            d =
              '#' +
              d[1] +
              d[1] +
              d[2] +
              d[2] +
              d[3] +
              d[3] +
              (n > 4 ? d[4] + d[4] : '');
          }
          d = i(d.slice(1), 16);
          if (n == 9 || n == 5) {
            ((x.r = (d >> 24) & 255),
              (x.g = (d >> 16) & 255),
              (x.b = (d >> 8) & 255),
              (x.a = m((d & 255) / 0.255) / 1000));
          } else {
            ((x.r = d >> 16),
              (x.g = (d >> 8) & 255),
              (x.b = d & 255),
              (x.a = -1));
          }
        }
        return x;
      };
    }
    ((h = c0.length > 9),
      (h = a ? (c1.length > 9 ? true : c1 == 'c' ? !h : false) : h),
      (f = this.pSBCr(c0)),
      (P = p < 0),
      (t =
        c1 && c1 != 'c'
          ? this.pSBCr(c1)
          : P
            ? { r: 0, g: 0, b: 0, a: -1 }
            : { r: 255, g: 255, b: 255, a: -1 }),
      (p = P ? p * -1 : p),
      (P = 1 - p));
    if (!f || !t) {
      return null;
    }
    if (l) {
      ((r = m(P * f.r + p * t.r)),
        (g = m(P * f.g + p * t.g)),
        (b = m(P * f.b + p * t.b)));
    } else {
      ((r = m((P * f.r ** 2 + p * t.r ** 2) ** 0.5)),
        (g = m((P * f.g ** 2 + p * t.g ** 2) ** 0.5)),
        (b = m((P * f.b ** 2 + p * t.b ** 2) ** 0.5)));
    }
    ((a = f.a),
      (t = t.a),
      (f = a >= 0 || t >= 0),
      (a = f ? (a < 0 ? t : t < 0 ? a : a * P + t * p) : 0));
    if (h) {
      return (
        'rgb' +
        (f ? 'a(' : '(') +
        r +
        ',' +
        g +
        ',' +
        b +
        (f ? ',' + m(a * 1000) / 1000 : '') +
        ')'
      );
    } else {
      return (
        '#' +
        (4294967296 + r * 16777216 + g * 65536 + b * 256 + (f ? m(a * 255) : 0))
          .toString(16)
          .slice(1, f ? undefined : -2)
      );
    }
  };
})(jQuery, Drupal);

/**
 * DaisyUI color mapping to Morphos palettes.
 * Supports v4 (--p) and v5 (--color-primary) formats.
 */
const DAISYUI_COLOR_MAP = {
  primary: {
    bg: ['--color-primary', '--p'],
    fg: ['--color-primary-content', '--pc'],
  },
  secondary: {
    bg: ['--color-secondary', '--s'],
    fg: ['--color-secondary-content', '--sc'],
  },
  accent: {
    bg: ['--color-accent', '--a'],
    fg: ['--color-accent-content', '--ac'],
  },
  neutral: {
    bg: ['--color-neutral', '--n'],
    fg: ['--color-neutral-content', '--nc'],
  },
  standard: {
    bg: ['--color-base-100', '--b1'],
    fg: ['--color-base-content', '--bc'],
  },
  alternate: {
    bg: ['--color-base-200', '--b2'],
    fg: ['--color-base-content', '--bc'],
  },
  light: {
    bg: ['--color-base-100', '--b1'],
    fg: ['--color-base-content', '--bc'],
  },
  dark: {
    bg: ['--color-neutral', '--n'],
    fg: ['--color-neutral-content', '--nc'],
  },
  success: {
    bg: ['--color-success', '--su'],
    fg: ['--color-success-content', '--suc'],
  },
  warning: {
    bg: ['--color-warning', '--wa'],
    fg: ['--color-warning-content', '--wac'],
  },
  error: {
    bg: ['--color-error', '--er'],
    fg: ['--color-error-content', '--erc'],
  },
  info: { bg: ['--color-info', '--in'], fg: ['--color-info-content', '--inc'] },
};

/**
 * Import DaisyUI theme colors into the form.
 */
function importDaisyUIColors(event) {
  var btn = event.target;
  var skin = btn.dataset.importSkin;
  var scheme = btn.dataset.importScheme;
  var textarea = btn.closest('details').querySelector('textarea');

  if (!textarea || !textarea.value.trim()) {
    showImportMessage('Paste DaisyUI CSS variables first.', 'error');
    return;
  }

  // Show loading state.
  var btnText = btn.value;
  btn.value = 'Importing...';
  btn.disabled = true;

  // Let the UI update before processing.
  setTimeout(function () {
    try {
      doImport(textarea.value, btn, btnText, skin, scheme);
    } catch (err) {
      showImportMessage('Import failed: ' + err.message, 'error');
      btn.value = btnText;
      btn.disabled = false;
    }
  }, 50);
}

/**
 * Process DaisyUI import.
 */
function doImport(cssText, btn, btnText, skin, scheme) {
  var cssVars = parseCssVariables(cssText);

  if (!Object.keys(cssVars).length) {
    showImportMessage('No CSS variables found.', 'error');
    if (btn) {
      btn.value = btnText;
      btn.disabled = false;
    }
    return;
  }
  var imported = 0;
  var warnings = [];

  // Process each palette.
  Object.keys(DAISYUI_COLOR_MAP).forEach(function (palette) {
    var map = DAISYUI_COLOR_MAP[palette];
    var bgVal = findCssVar(cssVars, map.bg);
    var fgVal = findCssVar(cssVars, map.fg);

    if (!bgVal && !fgVal) {
      return;
    }

    try {
      var bgHex = bgVal ? oklchToHex(bgVal) : null;
      var fgHex = fgVal ? oklchToHex(fgVal) : null;

      if (!bgHex && !fgHex) {
        return;
      }

      // Build selector for this palette.
      var sel =
        '.color-base[data-color-skin="' +
        skin +
        '"]' +
        '[data-color-scheme="' +
        scheme +
        '"]' +
        '[data-color-key="' +
        palette +
        '"]';

      var bgInput = document.querySelector(sel + '[name*="background_base"]');
      var fgInput = document.querySelector(sel + '[name*="foreground_base"]');
      var linkInput = document.querySelector(sel + '[name*="link_base"]');

      // Set background.
      if (bgInput && bgHex) {
        bgInput.value = bgHex;
        bgInput.setAttribute('value', bgHex);
        imported++;
      }

      // Set foreground.
      if (fgInput && fgHex) {
        fgInput.value = fgHex;
        fgInput.setAttribute('value', fgHex);
        imported++;
      }

      // Determine dark/light and generate link color.
      if (bgHex && fgHex) {
        var isDark = getLuminance(bgHex) < getLuminance(fgHex);

        var darkCheckbox = document.getElementById(
          skin + '--' + scheme + '--' + palette + '--is-dark',
        );
        if (darkCheckbox) {
          darkCheckbox.checked = isDark;
        }

        if (linkInput) {
          var linkHex = makeLinkColor(bgHex, fgHex);
          linkInput.value = linkHex;
          linkInput.setAttribute('value', linkHex);
          imported++;
        }
      }

      // Trigger updates.
      [bgInput, fgInput, linkInput].forEach(function (input) {
        if (input) {
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    } catch (e) {
      warnings.push(palette + ': ' + e.message);
    }
  });

  // Reset button.
  if (btn) {
    btn.value = btnText;
    btn.disabled = false;
  }

  // Show result.
  if (imported > 0) {
    var msg = 'Imported ' + imported + ' colors. Review and save.';
    if (warnings.length) {
      msg += ' (' + warnings.length + ' warnings)';
    }
    showImportMessage(msg, 'success');
  } else {
    showImportMessage('No colors imported. Check CSS format.', 'error');
  }
}

/**
 * Find first matching CSS variable.
 */
function findCssVar(vars, names) {
  for (var i = 0; i < names.length; i++) {
    if (vars[names[i]]) {
      return vars[names[i]];
    }
  }
  return null;
}

/**
 * Show import feedback message.
 */
function showImportMessage(message, type) {
  // Use Drupal messages if available.
  if (typeof Drupal !== 'undefined' && Drupal.Message) {
    var messenger = new Drupal.Message();
    messenger.clear();
    messenger.add(message, { type: type === 'success' ? 'status' : type });
    return;
  }

  // Fallback to custom element.
  var el = document.getElementById('daisyui-import-message');
  if (!el) {
    el = document.createElement('div');
    el.id = 'daisyui-import-message';
    el.style.cssText =
      'padding:10px 15px;margin:10px 0;border-radius:4px;font-weight:500';
    var textarea = document.getElementById('daisyui-import-css-variables');
    if (textarea && textarea.parentNode) {
      textarea.parentNode.insertBefore(el, textarea);
    }
  }

  var styles = {
    success: { bg: '#d4edda', border: '#c3e6cb', color: '#155724' },
    error: { bg: '#f8d7da', border: '#f5c6cb', color: '#721c24' },
    warning: { bg: '#fff3cd', border: '#ffeeba', color: '#856404' },
  };
  var s = styles[type] || styles.warning;

  el.style.backgroundColor = s.bg;
  el.style.border = '1px solid ' + s.border;
  el.style.color = s.color;
  el.textContent = message;
  el.style.display = 'block';

  if (type === 'success') {
    setTimeout(function () {
      el.style.display = 'none';
    }, 5000);
  }
}

/**
 * Parse CSS variables from text.
 */
function parseCssVariables(text) {
  var vars = {};
  var re = /(--[\w-]+)\s*:\s*([^;]+);/g;
  var m;
  while ((m = re.exec(text)) !== null) {
    vars[m[1].trim()] = m[2].trim();
  }
  return vars;
}

/**
 * Convert OKLCH to hex color.
 * Handles both "L C H" and "oklch(L C H)" formats.
 */
function oklchToHex(str) {
  var val = str.trim();

  // Extract from oklch() function if present.
  var match = val.match(/oklch\s*\(\s*([^)]+)\s*\)/i);
  if (match) {
    val = match[1].trim();
  }

  var parts = val.split(/\s+/);
  if (parts.length < 3) {
    throw new Error('Invalid OKLCH: ' + str);
  }

  // Parse lightness (percentage or decimal).
  var L = parseFloat(parts[0]);
  if (parts[0].indexOf('%') !== -1 || L > 1) {
    L = L / 100;
  }

  var C = parseFloat(parts[1]);
  var H = parseFloat(parts[2]);

  if (isNaN(L) || isNaN(C) || isNaN(H)) {
    throw new Error('Invalid OKLCH values: ' + str);
  }

  // OKLCH to OKLAB.
  var hRad = (H * Math.PI) / 180;
  var a = C * Math.cos(hRad);
  var b = C * Math.sin(hRad);

  // OKLAB to linear sRGB.
  var l_ = L + 0.3963377774 * a + 0.2158037573 * b;
  var m_ = L - 0.1055613458 * a - 0.0638541728 * b;
  var s_ = L - 0.0894841775 * a - 1.291485548 * b;

  var l = l_ * l_ * l_;
  var m = m_ * m_ * m_;
  var s = s_ * s_ * s_;

  var r = 4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s;
  var g = -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s;
  var bl = -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s;

  // Gamma correction.
  r = r <= 0.0031308 ? 12.92 * r : 1.055 * Math.pow(r, 1 / 2.4) - 0.055;
  g = g <= 0.0031308 ? 12.92 * g : 1.055 * Math.pow(g, 1 / 2.4) - 0.055;
  bl = bl <= 0.0031308 ? 12.92 * bl : 1.055 * Math.pow(bl, 1 / 2.4) - 0.055;

  // Clamp and convert to hex.
  r = Math.max(0, Math.min(255, Math.round(r * 255)));
  g = Math.max(0, Math.min(255, Math.round(g * 255)));
  bl = Math.max(0, Math.min(255, Math.round(bl * 255)));

  return '#' + ((1 << 24) + (r << 16) + (g << 8) + bl).toString(16).slice(1);
}

/**
 * Get relative luminance of hex color.
 */
function getLuminance(hex) {
  hex = hex.replace('#', '');
  var r = parseInt(hex.substr(0, 2), 16) / 255;
  var g = parseInt(hex.substr(2, 2), 16) / 255;
  var b = parseInt(hex.substr(4, 2), 16) / 255;

  r = r <= 0.03928 ? r / 12.92 : Math.pow((r + 0.055) / 1.055, 2.4);
  g = g <= 0.03928 ? g / 12.92 : Math.pow((g + 0.055) / 1.055, 2.4);
  b = b <= 0.03928 ? b / 12.92 : Math.pow((b + 0.055) / 1.055, 2.4);

  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * Generate link color as a 85/15 mix of foreground and background.
 */
function makeLinkColor(bgHex, fgHex) {
  var bg = hexToRgb(bgHex);
  var fg = hexToRgb(fgHex);
  var weight = 0.85;

  var r = Math.round(fg.r * weight + bg.r * (1 - weight));
  var g = Math.round(fg.g * weight + bg.g * (1 - weight));
  var b = Math.round(fg.b * weight + bg.b * (1 - weight));

  return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}

/**
 * Parse hex to RGB.
 */
function hexToRgb(hex) {
  hex = hex.replace('#', '');
  return {
    r: parseInt(hex.substr(0, 2), 16),
    g: parseInt(hex.substr(2, 2), 16),
    b: parseInt(hex.substr(4, 2), 16),
  };
}
