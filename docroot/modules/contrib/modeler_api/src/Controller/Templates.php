<?php

namespace Drupal\modeler_api\Controller;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for templates in Modeler API.
 */
final class Templates extends ControllerBase {

  /**
   * Template controller constructor.
   */
  public function __construct(
    protected Request $request,
    protected ModelOwnerPluginManager $modelOwnerPluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Templates {
    return new Templates(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('plugin.manager.modeler_api.model_owner'),
    );
  }

  /**
   * Checks access for template apply.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(): AccessResultInterface {
    $result = AccessResult::forbidden();
    foreach ($this->modelOwnerPluginManager->getAllInstances() as $owner) {
      if ($this->currentUser()->hasPermission('modeler api edit ' . $owner->configEntityTypeId())) {
        $result = AccessResult::allowed();
        break;
      }
    }
    return $result;
  }

  /**
   * Apply templates to elements through model owners.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response that contains messages.
   */
  public function apply(): AjaxResponse {
    $frontEndData = json_decode($this->request->getContent(), TRUE);
    $response = new AjaxResponse();
    /** @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface[] $owners */
    $owners = [];
    foreach ($frontEndData as $item) {
      $ownerId = $item['model_owner_id'];
      if (!isset($owners[$ownerId])) {
        try {
          $owners[$ownerId] = $this->modelOwnerPluginManager->createInstance($ownerId);
        }
        catch (PluginException) {
          $response->addCommand(new MessageCommand($this->t('Error: Could not load model owner %label.', [
            '%label' => $ownerId,
          ]), NULL, ['type' => 'error']));
          continue;
        }
      }
      $owner = $owners[$ownerId];
      if ($this->currentUser()->hasPermission('modeler api edit ' . $owner->configEntityTypeId())) {
        $owner->applyTemplate($item['model_id'], $item['component_id'], $item['target'], $item['hidden_config'], $item['config']);
        $response->addCommand(new MessageCommand('Automation template saved and applied.'));
      }
      else {
        $response->addCommand(new MessageCommand($this->t('Error: No permission to apply templates for model owner %label.', [
          '%label' => $ownerId,
        ]), NULL, ['type' => 'error']));
      }
    }
    return $response;
  }

}
