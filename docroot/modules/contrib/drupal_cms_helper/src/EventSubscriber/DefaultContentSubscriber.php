<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\Core\DefaultContent\ExportMetadata;
use Drupal\Core\DefaultContent\PreEntityImportEvent;
use Drupal\Core\DefaultContent\PreExportEvent;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adjusts exported and imported default content for Drupal CMS.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final readonly class DefaultContentSubscriber implements EventSubscriberInterface {

  public function __construct(
    private LanguageManagerInterface $languageManager,
    private EntityFieldManagerInterface $entityFieldManager,
    private TypedDataManagerInterface $typedDataManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreExportEvent::class => 'preExport',
      PreEntityImportEvent::class => 'preEntityImport',
    ];
  }

  /**
   * Keeps the original language of content that core imports as a translation.
   *
   * When the site does not have the language of imported content (i.e., no
   * language.entity.LANGCODE eixsts for it), or Drupal is being installed in
   * another language, core's default content importer imports the content as
   * one of its translations instead, if the site has that language. But it
   * takes the first translation it knows, rather than the one in the site's
   * default language, and it throws the original values away: content written
   * in English with a Spanish translation, imported into a Spanish site, ends
   * up in Spanish with no English at all.
   *
   * This polyfills the core fix for that. This event only lets us change the
   * entity data, not its metadata, so core still performs the swap itself. We
   * arrange the data so that core swaps to the right translation and keeps
   * the original values.
   *
   * @todo Remove when https://www.drupal.org/i/3622758 is fixed in core.
   *
   * @see \Drupal\Core\DefaultContent\Importer::verifyNormalizedLanguage()
   */
  public function preEntityImport(PreEntityImportEvent $event): void {
    $content_default_langcode = $event->metadata['default_langcode'] ?? NULL;
    $translations = $event->data['translations'] ?? [];
    if (empty($content_default_langcode) || empty($translations)) {
      return;
    }

    // Core swaps languages when the site does not have the content's language.
    // It also swaps while Drupal is being installed in a language other than
    // the content's, because during installation the site default language is
    // still English when modules are installed, so core cannot rely on the
    // language list and compares to the site default language instead. Bail
    // out if core will not swap: there is nothing to fix.
    // @see \Drupal\Core\DefaultContent\Importer::verifyNormalizedLanguage()
    $site_default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    $original_language_known = (bool) $this->languageManager->getLanguage($content_default_langcode);
    $core_swaps = !$original_language_known || (InstallerKernel::installationAttempted() && $site_default_langcode !== $content_default_langcode);
    if (!$core_swaps) {
      return;
    }

    // Choose the translation core should swap to: the one in the site default
    // language if there is one, otherwise the first one in a language the site
    // has. If there is none, core imports the content as-is in the site
    // default language, and we have nothing to do.
    if (isset($translations[$site_default_langcode])) {
      $new_default_langcode = $site_default_langcode;
    }
    else {
      $new_default_langcode = array_find(
        array_map('strval', array_keys($translations)),
        fn (string $langcode): bool => (bool) $this->languageManager->getLanguage($langcode),
      );
      if ($new_default_langcode === NULL) {
        return;
      }
    }
    // Core swaps to the first translation in a language it has, so move the
    // chosen one to the front.
    $translations = [$new_default_langcode => $translations[$new_default_langcode]] + $translations;

    // Now make sure no language ends up with another language's values. Take
    // this example: the content is written in English and has fields A, B and
    // C. Its Spanish translation, which core is about to make the default, has
    // fields A, B and D. (Exports only list fields that have a value, so C is
    // empty in Spanish and D is empty in English.) Core builds the new default
    // by merging the Spanish values over the English ones. If we leave the
    // data alone, the Spanish default gets A_es, B_es, C_en and D_es: an
    // English value shows up in Spanish.
    if (!is_array($event->data['default'] ?? NULL)) {
      $event->data['translations'] = $translations;
      return;
    }
    // Step 1: list C, the fields only the original has, as explicitly empty
    // for Spanish. Now the merge empties C, and the Spanish default gets A_es,
    // B_es, C empty and D_es. We do this even if we cannot keep the original,
    // because the English values still must not show up in Spanish.
    foreach (array_diff_key($event->data['default'], $translations[$new_default_langcode]) as $field_name => $items) {
      $empty_items = $this->getEmptyItems($event->metadata, $field_name, count($items));
      if ($empty_items !== NULL) {
        $translations[$new_default_langcode][$field_name] = $empty_items;
      }
    }

    // Step 2: if the site has English, add the original values as the
    // English translation, so core keeps them. Core creates each translation
    // as a copy of the default (A_es, B_es, C empty, D_es) and then sets the
    // values we list for it. So we list all the English values, A_en, B_en and
    // C_en, to put back what Spanish replaced or emptied. We also list D as
    // explicitly empty, because the copy has D_es and English never had a value
    // in D.
    if ($original_language_known) {
      $original_values = $event->data['default'];
      foreach (array_diff_key($translations[$new_default_langcode], $event->data['default']) as $field_name => $items) {
        $empty_items = $this->getEmptyItems($event->metadata, $field_name, count($items));
        if ($empty_items !== NULL) {
          $original_values[$field_name] = $empty_items;
        }
      }
      $translations[$content_default_langcode] = $original_values;
    }
    $event->data['translations'] = $translations;
  }

  /**
   * Returns normalized field items which will be empty once imported.
   *
   * We cannot list a field as an empty list of items, because core's importer
   * does nothing with that: the translation keeps the items it copied from the
   * default (D_es in the example in ::preEntityImport()), and the merged
   * default keeps the original's items (C_en there). Instead, we list one item
   * per copied item with every stored property set to NULL. Core sets those
   * properties, the items become empty, and Drupal drops empty items when it
   * saves the entity. The field type decides what counts as empty, so we check
   * that with a detached item first. A field that is not translatable is
   * shared by all translations, so we never empty it.
   *
   * @param array<string, mixed> $metadata
   *   The metadata of the entity being imported.
   * @param string $field_name
   *   The name of the field.
   * @param int $count
   *   The number of items the default translation has in the field.
   *
   * @return list<array<string, null>>|null
   *   The items, or NULL if the field is unknown or not translatable, or its
   *   items cannot be emptied this way.
   */
  private function getEmptyItems(array $metadata, string $field_name, int $count): ?array {
    $entity_type_id = $metadata['entity_type'] ?? NULL;
    if (!is_string($entity_type_id)) {
      return NULL;
    }
    $bundle = $metadata['bundle'] ?? $entity_type_id;
    $definition = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)[$field_name] ?? NULL;
    if (!$definition?->isTranslatable()) {
      return NULL;
    }

    $item_definition = $definition->getItemDefinition();
    assert($item_definition instanceof ComplexDataDefinitionInterface);
    $value = [];
    foreach ($item_definition->getPropertyDefinitions() as $name => $property) {
      if (!$property->isComputed()) {
        $value[$name] = NULL;
      }
    }
    if ($value === []) {
      return NULL;
    }
    // Let the field item be the final arbiter of whether that makes it empty.
    $item = $this->typedDataManager->create($item_definition, $value);
    if (!$item instanceof FieldItemInterface || !$item->isEmpty()) {
      return NULL;
    }
    return array_fill(0, $count, $value);
  }

  /**
   * Prepares to export a content entity.
   */
  public function preExport(PreExportEvent $event): void {
    $callbacks = $event->getCallbacks();
    $original = $callbacks['field_item:entity_reference'];

    // @todo Remove when https://www.drupal.org/i/3577579 is fixed or released.
    $callback = function (FieldItemInterface $item, ExportMetadata $metadata) use ($original): ?array {
      $target_type = $item->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getSetting('target_type');

      // @phpstan-ignore property.notFound
      if ($target_type === 'taxonomy_term' && strval($item->target_id) === '0') {
        return NULL;
      }
      return $original($item, $metadata);
    };
    $event->setCallback('field_item:entity_reference', $callback);
  }

}
