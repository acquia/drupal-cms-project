<?php

namespace Drupal\eca_language\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca_language\Event\LanguageNegotiateEvent;

/**
 * Resets language negotiation.
 */
#[Action(
  id: 'eca_reset_language_negotiation',
  label: new TranslatableMarkup('Language: reset negotiation'),
)]
#[EcaAction(
  description: new TranslatableMarkup('This may be useful when switching between multiple users with different preferred languages.'),
  version_introduced: '2.0.0',
)]
class ResetLanguageNegotiation extends LanguageActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (isset($this->event) && ($this->event instanceof LanguageNegotiateEvent)) {
      $this->event->langcode = NULL;
      return;
    }
    $this->languageManager->getNegotiator()->setCurrentUser($this->currentUser);
    $this->languageManager->reset();
  }

}
