<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drupal_cms_helper\ProjectResolver;
use Drupal\drupal_cms_helper\RecipeInventory;
use Drupal\drupal_cms_helper\Telemetry;
use Drupal\drupal_cms_helper\TelemetryManager;
use Drupal\language\Entity\ConfigurableLanguage;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to recipe events for Drupal CMS.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class RecipeSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly TelemetryManager $telemetry,
    private readonly RecipeInventory $inventory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RecipeAppliedEvent::class => 'onApply',
      ConfigEvents::IMPORT => 'onImport',
      ConsoleEvents::TERMINATE => 'onConsoleTerminate',
    ];
  }

  /**
   * Reacts when a recipe is applied.
   *
   * @param \Drupal\Core\Recipe\RecipeAppliedEvent $event
   *   The event being handled.
   */
  public function onApply(RecipeAppliedEvent $event): void {
    $recipe = $event->recipe;
    $extra = [
      'name' => $this->isReportable($recipe) ? basename($recipe->path) : '?',
    ];

    // This structure may look janky, but we do NOT want to log recipes that are
    // applied during the course of installing Drupal from a site template.
    if (InstallerKernel::installationAttempted()) {
      if ($recipe->type === 'Site') {
        $extra['cli'] = PHP_SAPI === 'cli';

        if ($extra['cli']) {
          // Work around a core bug (documented in onImport()).
          $this->onImport();

          // If the recipe's path is anywhere in the command line, we can assume
          // that it was specifically chosen by the user.
          $extra['cli_specified'] = in_array($recipe->path, $_SERVER['argv'] ?? [], TRUE);
        }
        $this->telemetry->log(Telemetry::SiteTemplateInstall, $extra);
      }
    }
    else {
      $this->telemetry->log(Telemetry::RecipeApplied, $extra);
    }

    // If the recipe has installed Language, then we need to ensure at least one
    // configurable language exists (the site's default), ignoring locked ones.
    // @todo Remove when https://www.drupal.org/i/3622004 is released.
    if (in_array('language', $recipe->install->modules, TRUE)) {
      $languages = $this->entityTypeManager->getStorage('configurable_language')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('locked', FALSE)
        ->range(0, 1)
        ->execute();

      if (empty($languages)) {
        // The language manager synthesizes a default even with no entities, so
        // this is the language the site is already pretending it has.
        $language = $this->languageManager->getDefaultLanguage()->getId();
        ConfigurableLanguage::createFromLangcode($language)->save();
      }
    }
  }

  /**
   * Reacts when config is imported.
   */
  public function onImport(): void {
    // When installing Drupal using a monolithic site template at the command
    // line, user 1 may be statically cached with stale field definitions, which
    // can cause errors when the account is modified during the installation by
    // \Drupal\Core\Installer\Form\SiteConfigureForm. To prevent that, clear the
    // static cache for user 1. This bug can also affect installing from
    // existing configuration (i.e., an import).
    // @todo Remove when https://www.drupal.org/i/3578151 is released.
    $this->entityTypeManager->getStorage('user')->resetCache([1]);
  }

  /**
   * Adds additional information to the output of `recipe:info`.
   *
   * @api
   *   The additional information provided by this method is part of Drupal
   *   CMS's developer-facing API and may be relied upon. The method itself is
   *   internal and can be changed or removed at any time without warning.
   */
  public function onConsoleTerminate(ConsoleTerminateEvent $event): void {
    if ($event->getCommand()->getName() === 'recipe:info') {
      $input = $event->getInput();

      $directory = realpath($input->getArgument('path'));
      assert(is_string($directory));

      $stack = RecipeInventory::toStack(
        Recipe::createFromDirectory($directory),
      );
      $io = new SymfonyStyle($input, $event->getOutput());

      $io->section((string) $this->t('Package'));
      $io->text(
        str_contains($directory, '/core/recipes/')
          ? 'drupal/core'
          : ProjectResolver::readPackageInfo($directory, 'name') ?? '?',
      );

      // Show where all the imported configuration is from.
      $imports = $this->inventory->listImportSources(...$stack)->map;
      if ($imports) {
        $io->section((string) $this->t('Imports'));
        $io->table(
          array_map('strval', [
            $this->t('Name'),
            $this->t('Path'),
          ]),
          // Awkward as hell, but this is what an array zip looks like in PHP.
          array_map(NULL, array_keys($imports), array_values($imports)),
        );
      }

      // Show all extensions installed by the recipe.
      $extensions = $this->inventory->getExtensions(...$stack);
      if ($extensions) {
        $io->section((string) $this->t('Extensions'));
        $io->table(
          array_map('strval', [
            $this->t('Name'),
            $this->t('Package'),
          ]),
          array_map(NULL, array_keys($extensions), array_values($extensions)),
        );
      }

      // List all content.
      $content = $this->inventory->getContent(...$stack);
      if ($content) {
        $io->section((string) $this->t('Content'));
        $io->table(
          array_map('strval', [
            $this->t('Type'),
            $this->t('UUID'),
          ]),
          array_map(NULL, array_values($content), array_keys($content)),
        );
      }
    }
  }

  /**
   * Checks if a recipe is known, and may be reported to telemetry.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe to check.
   *
   * @return bool
   *   TRUE if the recipe is a known contributed or core recipe.
   */
  private function isReportable(Recipe $recipe): bool {
    $name = basename($recipe->path);

    $is_match = function (string $pattern) use ($name): bool {
      if (str_ends_with($pattern, '*')) {
        return str_starts_with($name, rtrim($pattern, '*'));
      }
      return $name === $pattern;
    };

    if ($recipe->type === 'Site') {
      return array_any(Telemetry::SITE_TEMPLATES, $is_match);
    }
    else {
      // This is a fairly naïve heuristic for detecting a core recipe, but it's
      // good enough for our purposes.
      return str_ends_with($recipe->path, "/core/recipes/$name")
        ? in_array($name, Telemetry::CORE_RECIPES, TRUE)
        : array_any(Telemetry::CONTRIB_RECIPES, $is_match);
    }
  }

}
