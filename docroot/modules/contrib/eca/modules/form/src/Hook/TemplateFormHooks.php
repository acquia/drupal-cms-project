<?php

namespace Drupal\eca_form\Hook;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eca\Entity\Eca;
use Drupal\eca\PluginManager\Event;
use Drupal\eca_form\Event\FormBuild;
use Drupal\eca_form\Event\FormValidate;
use Drupal\modeler_api\TemplateTokenResolver;

/**
 * Implements form hooks for templates.
 */
class TemplateFormHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new TemplateFormHooks object.
   */
  public function __construct(
    protected TemplateTokenResolver $templateTokenResolver,
    protected Event $eventPluginManager,
    protected AccountProxyInterface $currentUser,
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter', order: Order::Last)]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['#form_id']) || !$this->currentUser->hasPermission('modeler api edit eca')) {
      return;
    }
    $templates = $this->state->get('eca.templates', []);
    $events = [
      'eca.form.build' => new FormBuild($form, $form_state),
      'eca.form.validate' => new FormValidate($form, $form_state),
    ];
    foreach ($events as $eventName => $event) {
      $eventPluginId = $this->eventPluginManager->getPluginIdForSystemEvent($eventName);
      try {
        $eventPluginClass = $this->eventPluginManager->getDefinition($eventPluginId)['class'];
      }
      catch (PluginNotFoundException) {
        continue;
      }
      foreach ($templates[$eventName] ?? [] as $ecaId => $events) {
        $eca = Eca::load($ecaId);
        foreach ($events as $eventId => $wildcard) {
          if (call_user_func($eventPluginClass . '::appliesForWildcard', $event, $eventName, $wildcard)) {
            $this->templateTokenResolver->addLabel($eca->get('events')[$eventId]['label'], 'eca', $ecaId, $eventId);
            if ($wildcard === '*:*:*:*') {
              $this->templateTokenResolver->addConfig('form_ids', $form['#form_id'], 'eca', $ecaId, $eventId);
              $newEcaId = 'form_' . $form['#form_id'];
              $formIds = $form['#form_id'];
            }
            else {
              foreach ($eca->get('events')[$eventId]['configuration'] as $key => $value) {
                if (!empty($value) && is_scalar($value)) {
                  $this->templateTokenResolver->addConfig($key, $value, 'eca', $ecaId, $eventId);
                }
              }
              $newEcaId = 'form_' . str_replace(['*', ':'], ['any', '_'], $wildcard);
              $formIds = '';
            }
            $this->templateTokenResolver->addConfig('newModelId', $newEcaId, 'eca', $ecaId, $eventId);
            $this->templateTokenResolver->addDropdownItem('eca-template:select:form:field:all', $this->t('Edit in modeler'), 'eca', $newEcaId, 'eca_form', [
              'form_ids' => $formIds,
              'field_name' => '{{ target }}',
            ]);
            foreach ($eca->getAllEventElements($eventId) as $id => $type) {
              foreach ($eca->get($type . 's')[$id]['configuration'] ?? [] as $value) {
                $this->templateTokenResolver->addToken($value ?? '', 'eca', $ecaId, $eventId);
              }
            }
          }
        }
      }
    }
    $this->templateTokenResolver->getAttachments($form);
  }

}
