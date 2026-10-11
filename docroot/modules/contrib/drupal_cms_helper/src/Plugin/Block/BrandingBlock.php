<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\system\Plugin\Block\SystemBrandingBlock;

/**
 * Overrides the system branding block to fix the logo settings link.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 *
 * @todo Remove this when https://www.drupal.org/node/2852838 is released.
 */
final class BrandingBlock extends SystemBrandingBlock {

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<mixed>
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    $logo_description = $form['block_branding']['use_site_logo']['#description'];
    if ($logo_description instanceof TranslatableMarkup) {
      $arguments = $logo_description->getArguments();

      if (isset($arguments['@appearance'])) {
        $arguments['@appearance'] = Url::fromRoute('system.theme_settings_theme')
          ->setRouteParameter('theme', $this->configFactory->get('system.theme')->get('default'))
          ->setOption('fragment', 'edit-logo')
          ->toString();

        // phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
        $form['block_branding']['use_site_logo']['#description'] = $this->t(
          $logo_description->getUntranslatedString(),
          $arguments,
          $logo_description->getOptions(),
        );
        // phpcs:enable Drupal.Semantics.FunctionT.NotLiteralString
      }
    }
    return $form;
  }

}
