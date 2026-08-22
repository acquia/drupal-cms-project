<?php

namespace Drupal\trash_test\EventSubscriber;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\LanguageNegotiatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles specific behavior for trash module.
 */
class TrashTestEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ?LanguageNegotiatorInterface $languageNegotiator,
    protected LanguageManagerInterface $languageManager,
    #[Autowire(service: 'keyvalue')]
    protected KeyValueFactoryInterface $keyValueFactory,
  ) {

  }

  /**
   * Handles early language negotiations before the trash module may use it.
   */
  public function handleLanguageNegotiations(): void {
    if ($this->keyValueFactory->get('trash_test')->get('early_language_negotiation')) {
      // Trigger language negotiations earlier than normal.
      $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
      $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE);
      $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Handle language negotiations immediately after the
    // authentication subscriber (priority 300), and before the trash ignore
    // subscriber (priority 299).
    $events[KernelEvents::REQUEST][] = ['handleLanguageNegotiations', 300];

    return $events;
  }

}
