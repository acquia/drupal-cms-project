<?php

namespace Drupal\eca\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase as CoreActionBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\eca\EcaState;
use Drupal\eca\Token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for ECA provided actions.
 */
abstract class ActionBase extends CoreActionBase implements ContainerFactoryPluginInterface, ActionInterface {

  /**
   * The ID of the containing ECA model.
   *
   * @var string
   */
  protected string $ecaModelId;

  /**
   * The ID of the action within the ECA model.
   *
   * @var string
   */
  protected string $actionId;

  /**
   * Triggered event leading to this action.
   *
   * @var \Symfony\Contracts\EventDispatcher\Event
   */
  protected Event $event;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The ECA-related token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * ECA state service.
   *
   * @var \Drupal\eca\EcaState
   */
  protected EcaState $state;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, TokenInterface $token_service, AccountProxyInterface $current_user, TimeInterface $time, EcaState $state, LoggerChannelInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->tokenService = $token_service;
    $this->currentUser = $current_user;
    $this->time = $time;
    $this->state = $state;
    $this->logger = $logger;

    if ($this instanceof ConfigurableInterface) {
      $this->setConfiguration($configuration);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('eca.token_services'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('eca.state'),
      $container->get('logger.channel.eca')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function externallyAvailable(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setEcaActionIds(string $ecaModelId, string $actionId): ActionInterface {
    $this->ecaModelId = $ecaModelId;
    $this->actionId = $actionId;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEvent(object $event): ActionInterface {
    $this->event = $event;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEvent(): Event {
    return $this->event;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function handleExceptions(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function logExceptions(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * ECA actions do not attach cache metadata to their access results. Return
   * a bare AccessResult, without ::cachePerPermissions(), ::cachePerUser(),
   * ::addCacheContexts(), ::addCacheTags() or any max-age call.
   *
   * The reason is that nothing ever reads it. Every known caller of an ECA
   * action plugin's ::access() reduces the result to a boolean, plus the
   * reason string in the two cases that report why: ECA's own executor in
   * Objects\EcaAction::execute(), PreConfiguredAction::access(), and the bulk
   * form callers in Views, Entity, Webform and DRD. None of them reads the
   * cacheability back off the result, passes it to ::addCacheableDependency(),
   * attaches it to a render array or returns it in a response. Nor could they
   * rely on it: ActionInterface::access() is typed to return
   * AccessResultInterface, which does not extend CacheableDependencyInterface,
   * so reading cacheability requires an instanceof guard that no caller has.
   * Actions also execute inside event dispatch - cron, entity CRUD, queue
   * workers, Drush - where there is no render context to bubble into.
   *
   * Metadata that nobody consumes is not free: it is unverifiable, so it drifts
   * into being wrong. ::cachePerPermissions() on a decision that actually
   * depends on token replacement asserts the result varies by permission set
   * and by nothing else, which is simply false, and the first consumer to
   * appear would cache the wrong thing.
   *
   * Access *reasons* are a separate concern and are wanted. They come from
   * AccessResultReasonInterface, which has nothing to do with cacheability,
   * and callers do log them.
   *
   * The carve-out: this rule is about action plugin access only. Access checks
   * that genuinely feed the render or page cache - controller `_custom_access`
   * callbacks, hook_entity_access() implementations and the like - are a
   * different case and are not covered by it. ECA's own eca_access hooks
   * deliberately collect cacheability from a render context and then set a
   * max-age of zero, which is correct for them.
   *
   * @see https://git.drupalcode.org/project/eca/-/work_items/3590399
   * @see https://git.drupalcode.org/project/eca/-/work_items/3590440
   * @see \Drupal\eca\Entity\Objects\EcaAction::execute()
   * @see \Drupal\eca_access\Hook\AccessHooks::entityAccess()
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowed();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
