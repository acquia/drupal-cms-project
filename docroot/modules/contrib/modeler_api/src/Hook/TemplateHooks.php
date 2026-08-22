<?php

namespace Drupal\modeler_api\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Url;
use Drupal\modeler_api\TemplateTokenResolver;

/**
 * Implements library hooks for the Modeler API module.
 */
class TemplateHooks {

  /**
   * Constructs a new TemplateHooks object.
   */
  public function __construct(
    protected TemplateTokenResolver $templateTokenResolver,
  ) {}

  /**
   * Implements hook_library_info_alter().
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension === 'modeler_api' && isset($libraries['template_token_selector'])) {
      $libraries['template_token_selector']['drupalSettings']['modeler_api']['token_url'] = Url::fromRoute('system.csrftoken')->toString();
      $libraries['template_token_selector']['drupalSettings']['modeler_api']['template_apply_url'] = Url::fromRoute('modeler_api.apply_template')->toString();
    }
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments', order: Order::Last)]
  public function pageAttachmentsAlter(array &$attachments): void {
    $this->templateTokenResolver->getAttachments($attachments);
  }

}
