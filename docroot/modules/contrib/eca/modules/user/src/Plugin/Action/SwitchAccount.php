<?php

namespace Drupal\eca_user\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\CleanupInterface;
use Drupal\eca_user\AccountSwitcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Switch current account.
 *
 * This action runs with the authority of the model, not with that of the user
 * who triggered it, and ECA deliberately grants access to it unconditionally.
 * Switching the account is the very purpose of the action, and gating it on a
 * permission held by the account before the switch would defeat the service
 * account pattern it exists for, where a low privileged trigger elevates to a
 * configured account on purpose.
 *
 * Restricting who may cause a switch is therefore a model restriction
 * requirement rather than a permission check. It is stated for site builders in
 * the plugin description above and in the notice on the configuration form
 * below, both of which are inherited by SwitchServiceAccount.
 *
 * @see \Drupal\eca_user\Plugin\Action\SwitchServiceAccount
 * @see https://git.drupalcode.org/project/eca/-/work_items/3590400
 */
#[Action(
  id: 'eca_switch_account',
  label: new TranslatableMarkup('User: switch current account'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Switch to given user account. Everything after this action runs with the permissions of that account, not with those of the user who triggered the model. ECA does not check any permission before the switch, so restricting it is part of the model: make sure the triggering event and the conditions in front of this action cannot be reached by an account that should not be able to cause the switch.'),
  version_introduced: '1.0.0',
)]
class SwitchAccount extends ConfigurableActionBase implements CleanupInterface {

  /**
   * The ECA account switcher service.
   *
   * @var \Drupal\eca_user\AccountSwitcher
   */
  protected AccountSwitcher $accountSwitcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->accountSwitcher = $container->get('eca_user.account_switcher');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'user_id' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['user_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User ID (UID)'),
      '#default_value' => $this->configuration['user_id'] ?? '',
      '#description' => $this->t('The numeric ID of the user account to switch to.'),
      '#weight' => -10,
      '#eca_token_replacement' => TRUE,
    ];
    // ECA does not gate account switching on a permission, so the model has to
    // do the restricting. Say so where the site builder configures the action.
    // @see https://git.drupalcode.org/project/eca/-/work_items/3590400
    $form['eca_account_switch_notice'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<em>Restricting this action is part of the model.</em> Everything after it runs with the permissions of the account being switched to, not with those of the user who triggered the model. ECA does not check any permission before the switch, because switching is the very purpose of this action. Make sure that the triggering event and the conditions in front of this action cannot be reached by an account that should not be able to cause the switch, for example by adding a role or permission condition before it, and grant the account being switched to only the permissions that the model actually needs.'),
      '#weight' => -9,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['user_id'] = $form_state->getValue('user_id');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!isset($this->configuration['user_id']) || $this->configuration['user_id'] === '') {
      return;
    }
    $uid = (string) $this->tokenService->replaceClear($this->configuration['user_id']);
    if ($uid !== '' && ctype_digit($uid)) {
      $uid = (int) $uid;
      /**
       * @var \Drupal\user\UserInterface|null $user
       */
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      $this->accountSwitcher->switchTo($this, $user);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cleanupAfterSuccessors(): void {
    $this->accountSwitcher->cleanup($this);
  }

}
