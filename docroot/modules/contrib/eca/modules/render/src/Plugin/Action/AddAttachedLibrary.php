<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Attach a library to an existing render array element.
 */
#[Action(
  id: 'eca_render_add_attached_library',
  label: new TranslatableMarkup('Render: add attached library'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Attach a JavaScript/CSS library to an existing render array element. Only works when reacting upon a rendering event, such as <em>Build form</em> or <em>Build ECA Block</em>.'),
  version_introduced: '3.0.12',
)]
class AddAttachedLibrary extends AddAttachedBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['value']['#title'] = $this->t('Library ID');
    $form['value']['#description'] = $this->t('Given in the format of <code>module_name/library_name</code>.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildAttachedArray(array &$attachments): void {
    $value = trim((string) $this->tokenService->replaceClear($this->configuration['value']));
    if (!empty($value)) {
      $attachments['library'][] = $value;
    }
  }

}
