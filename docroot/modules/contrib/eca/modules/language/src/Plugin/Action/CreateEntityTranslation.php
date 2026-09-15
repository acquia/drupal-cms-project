<?php

namespace Drupal\eca_language\Plugin\Action;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a translation for a content entity, replicating core UI behavior.
 *
 * Closely follows ContentTranslationController::prepareTranslation(), the
 * logic that runs when an editor clicks "Add" on /node/{id}/translations:
 *  - copies all field values from the source translation via toArray();
 *  - resets revision translation affected status (revisionable entities);
 *  - sets translation author to the current user;
 *  - sets translation creation time to request time;
 *  - records the source language on the translation metadata;
 *  - saves the entity.
 *
 * It additionally marks the new translation as outdated (needs update), as
 * required by this action's purpose, which core's prepareTranslation() does
 * not do. It also does not replicate core's additional draft-translation
 * reconciliation loop.
 */
#[Action(
  id: 'eca_create_entity_translation',
  label: new TranslatableMarkup('Entity: create translation'),
  type: 'entity',
)]
#[EcaAction(
  description: new TranslatableMarkup('Creates a translation of a content entity, replicating the behavior of the core translation UI.'),
  version_introduced: '3.1.0',
)]
class CreateEntityTranslation extends LanguageActionBase {

  use PluginFormTrait;

  /**
   * The content translation manager.
   *
   * NULL when the core content_translation module is not installed. The
   * eca_language module does not depend on content_translation, so its service
   * may be absent; access() and execute() handle that case gracefully.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface|null
   */
  protected ?ContentTranslationManagerInterface $contentTranslationManager = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    // Parent chain: LanguageActionBase::create() → ActionBase::create() which
    // calls new static() via the final constructor, then
    // LanguageActionBase::create() adds $instance->languageManager via setter.
    // We only need to add our one additional service here.
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    // The content_translation.manager service only exists when the core
    // content_translation module is enabled. The eca_language module does not
    // depend on content_translation, so on sites without it this service is
    // absent. Only inject it when present, otherwise the property stays NULL
    // and access()/execute() degrade gracefully instead of throwing during
    // plugin instantiation (which would break ECA plugin discovery).
    // phpstan infers from the container's PHPDoc that has() is always true for
    // a known service id, but service availability is a runtime concern here.
    // @phpstan-ignore if.alwaysTrue
    if ($container->has('content_translation.manager')) {
      $instance->setContentTranslationManager($container->get('content_translation.manager'));
    }
    return $instance;
  }

  /**
   * Sets the content translation manager.
   *
   * Follows the setter injection pattern established by LanguageActionBase,
   * required because ActionBase::__construct() is final.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $manager
   *   The content translation manager.
   */
  public function setContentTranslationManager(ContentTranslationManagerInterface $manager): void {
    $this->contentTranslationManager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'source_langcode' => '',
      'target_langcode' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $langcodes = $this->getLanguageOptions();

    $form['source_langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Source language'),
      '#options' => $langcodes,
      '#default_value' => $this->configuration['source_langcode'],
      '#description' => $this->t('The language to copy field values from. Typically the site default language.'),
      '#weight' => -20,
      '#eca_token_select_option' => TRUE,
    ];

    $form['target_langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Target language'),
      '#options' => $langcodes,
      '#default_value' => $this->configuration['target_langcode'],
      '#description' => $this->t('The language of the new translation to create.'),
      '#weight' => -10,
      '#eca_token_select_option' => TRUE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['source_langcode'] = $form_state->getValue('source_langcode');
    $this->configuration['target_langcode'] = $form_state->getValue('target_langcode');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!($object instanceof TranslatableInterface)) {
      $access_result = AccessResult::forbidden('No content entity provided.');
      return $return_as_object ? $access_result : $access_result->isAllowed();
    }

    // Bail out if the content translation manager is unavailable, which happens
    // when the core content_translation module is not installed. Guard before
    // the isEnabled() call below, which would otherwise dereference NULL.
    if ($this->contentTranslationManager === NULL) {
      $access_result = AccessResult::forbidden('Content translation module is not enabled.');
      return $return_as_object ? $access_result : $access_result->isAllowed();
    }

    // Bail out if content translation is not enabled for this entity type and
    // bundle, so execute() is never reached for a non-translatable bundle.
    if (!$this->contentTranslationManager->isEnabled($object->getEntityTypeId(), $object->bundle())) {
      $access_result = AccessResult::forbidden('Content translation is not enabled for this entity type and bundle.');
      return $return_as_object ? $access_result : $access_result->isAllowed();
    }

    // Assert that the given languages exist and that the translation from
    // source to target is possible and allowed.
    $source_langcode = $this->resolveLangcode('source_langcode');
    $target_langcode = $this->resolveLangcode('target_langcode');

    if ($source_langcode === '' || $target_langcode === '') {
      $access_result = AccessResult::forbidden('Source or target langcode could not be resolved.');
      return $return_as_object ? $access_result : $access_result->isAllowed();
    }

    // Source and target must differ, otherwise there is nothing to translate.
    // Check this before the "target exists" check below, so an identical-
    // language misconfiguration is not masked as an existing translation.
    if ($source_langcode === $target_langcode) {
      $access_result = AccessResult::forbidden('Source and target languages must differ.');
      return $return_as_object ? $access_result : $access_result->isAllowed();
    }

    // Bail out if the object (entity) does not have the source translation.
    if (!$object->hasTranslation($source_langcode)) {
      $access_result = AccessResult::forbidden('Source translation does not exist.');
      return $return_as_object ? $access_result : $access_result->isAllowed();
    }

    // Bail out silently if the target translation already exists.
    // This mirrors core's behavior: the "Add" button is only shown when no
    // translation exists yet, so we simply do nothing instead of overwriting.
    if ($object->hasTranslation($target_langcode)) {
      $access_result = AccessResult::forbidden('Target translation already exists.');
      return $return_as_object ? $access_result : $access_result->isAllowed();
    }

    /** @var \Drupal\Core\Access\AccessResultInterface $access_result */
    $access_result = parent::access($object, $account, TRUE);

    if ($access_result->isAllowed()) {
      $account = $account ?? $this->currentUser;
      // The action adds a translation to an existing entity and then saves it,
      // which modifies that entity. Therefore the correct entity-access
      // operation to verify is 'update', not 'create'.
      $access_result = $object->access('update', $account, TRUE);
    }

    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    // Defense in depth: the content translation manager is unavailable because
    // the core content_translation module is not installed. access() already
    // denies in this case, so this is a safe no-op should execute() ever be
    // reached directly without an access check.
    if ($this->contentTranslationManager === NULL) {
      return;
    }

    $source_langcode = $this->resolveLangcode('source_langcode');
    $target_langcode = $this->resolveLangcode('target_langcode');

    // --- Replicate ContentTranslationController::prepareTranslation() ---
    /** @var \Drupal\Core\Entity\ContentEntityInterface $source_translation */
    $source_translation = $entity->getTranslation($source_langcode);

    // Core uses toArray() to seed the new translation with all field values
    // from the source, including Paragraph references and Media references.
    // This is intentional: it gives translators a pre-filled starting point,
    // exactly as the UI does when the editor opens the translation add form.
    $target_translation = $entity->addTranslation(
      $target_langcode,
      $source_translation->toArray()
    );

    // Reset revision translation affected status so this new translation does
    // not inherit the source's revision tracking state (core does this too).
    if ($entity->getEntityType()->isRevisionable()) {
      $target_translation->setRevisionTranslationAffected(NULL);
    }

    // Update translation metadata: author and creation time.
    // Core loads the full user object here; we follow the same pattern.
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());

    $metadata = $this->contentTranslationManager
      ->getTranslationMetadata($target_translation);

    // Mirror core's prepareTranslation() metadata updates exactly.
    $metadata->setAuthor($user);
    $metadata->setCreatedTime($this->time->getRequestTime());

    // Record the source language on the new translation's metadata.
    // Core does this implicitly via the translation handler; we set it
    // explicitly to guarantee correctness in automated contexts.
    $metadata->setSource($source_langcode);

    // Mark this translation as outdated (needs to be updated).
    // This is the flag visible as "This translation needs to be updated"
    // in the translation UI, and the primary purpose of this action.
    $metadata->setOutdated(TRUE);

    // Persist. The entity storage handles the translation insert hooks
    // (hook_entity_translation_insert etc.) just as the UI save would.
    $entity->save();
  }

  /**
   * Resolves a langcode from configuration, supporting token substitution.
   *
   * Follows the same pattern as NewEntity::execute() for language resolution.
   *
   * @param string $config_key
   *   Either 'source_langcode' or 'target_langcode'.
   *
   * @return string
   *   The resolved language code, or empty string on failure.
   */
  protected function resolveLangcode(string $config_key): string {
    $value = $this->configuration[$config_key] ?? '';

    if ($value === '_eca_token') {
      $value = $this->getTokenValue($config_key, '');
    }

    // Fall back to the interface language if explicitly requested.
    if ($value === '_interface' || $value === '') {
      $value = $this->languageManager
        ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)
        ->getId();
    }

    return $value;
  }

  /**
   * Returns a keyed array of available language options for select fields.
   *
   * Includes the special token option, following NewEntity's pattern.
   *
   * @return array
   *   Language options keyed by langcode.
   */
  protected function getLanguageOptions(): array {
    $options = [];
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $options[$langcode] = $language->getName();
    }
    $options['_interface'] = $this->t('Interface language');
    return $options;
  }

}
