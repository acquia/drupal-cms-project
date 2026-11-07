<?php

namespace Drupal\eca_misc\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Event\RenderEventInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_misc\PageTitleStore;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets the title of the current page.
 */
#[Action(
  id: 'eca_misc_set_page_title',
  label: new TranslatableMarkup('Set page title'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Sets the title of the current page. Works on forms (e.g. the "Build form" event) and on regular pages such as entity view pages, views pages and controller results.'),
  version_introduced: '3.1.3',
)]
class SetPageTitle extends ConfigurableActionBase {

  /**
   * The page title store.
   *
   * @var \Drupal\eca_misc\PageTitleStore
   */
  protected PageTitleStore $pageTitleStore;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->pageTitleStore = $container->get(PageTitleStore::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowed();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $title = trim((string) $this->tokenService->replaceClear($this->configuration['title']));
    if ($title === '') {
      return;
    }

    // Store the title so the late preprocess hooks can apply it to both the
    // HTML <title> element and the on-page heading. This is the only reliable
    // override point for entity view pages, views pages and controller
    // results, because core freezes the title as a scalar in HtmlRenderer.
    $this->pageTitleStore->setTitle($title);

    // When this action runs in the context of a render event (e.g. the form
    // "Build form" event), also set "#title" directly on the render array.
    // For forms this is the reliable mechanism and takes immediate effect.
    $event = $this->event ?? NULL;
    if ($event instanceof RenderEventInterface) {
      $build = &$event->getRenderArray();
      $build['#title'] = $title;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'title' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('The title for the current page. This field supports tokens.'),
      '#default_value' => $this->configuration['title'],
      '#required' => TRUE,
      '#weight' => -10,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['title'] = $form_state->getValue('title');
    parent::submitConfigurationForm($form, $form_state);
  }

}
