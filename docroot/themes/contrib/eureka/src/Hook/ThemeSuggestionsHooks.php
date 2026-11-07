<?php

namespace Drupal\eureka\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for theme suggestions.
 */
class ThemeSuggestionsHooks {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new ThemeSuggestionsHooks object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    RequestStack $requestStack,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->requestStack = $requestStack;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Implements hook_theme_suggestions_page_alter().
   */
  #[Hook('theme_suggestions_page_alter')]
  public function suggestionsPageAlter(array &$suggestions): void {
    $request = $this->requestStack->getCurrentRequest();
    if ($node = $request->attributes->get('node')) {
      if (!is_object($node)) {
        $node = $request->attributes->get('node_revision');
        $node = $this->entityTypeManager
          ->getStorage('node')
          ->loadRevision($node);
      }
      if (is_object($node)) {
        array_splice($suggestions, 1, 0, 'page__node__' . $node->getType());
      }
    }
  }

}
