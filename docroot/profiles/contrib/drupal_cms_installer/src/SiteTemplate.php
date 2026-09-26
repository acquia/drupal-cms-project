<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_installer;

use Composer\Factory;
use Composer\InstalledVersions;
use Composer\IO\NullIO;
use Composer\Util\Platform;
use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Link;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeFileException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\Finder\Finder;

/**
 * Defines a value object with information about a site template.
 *
 * @internal
 *   Everything in the Drupal CMS installer is internal and may be changed or
 *   removed at any time without warning. External code should not interact
 *   with this class.
 */
final class SiteTemplate {

  /**
   * The path of the recipe in the file system, or its package name.
   */
  public readonly string $locator;

  /**
   * The path, or URL, of a screenshot.
   */
  private readonly string $screenshot;

  /**
   * Informational links about the site template (demo, documentation, etc.).
   *
   * All must point to external URLs.
   *
   * @var array<\Drupal\Core\Link>
   */
  public readonly array $links;

  /**
   * The price of the site template, or 0 if it is free.
   */
  public readonly float $price;

  /**
   * A URL where the site template can be purchased, or NULL if it is free.
   *
   * Must point to an external URL.
   */
  public readonly ?Url $purchaseUrl;

  /**
   * The URL of the Composer repository that provides the package.
   */
  public readonly ?string $repository;

  /**
   * The type of authorization, if any, used by the repository.
   */
  public readonly ?string $authorization;

  /**
   * A URL where the license key can be validated.
   */
  public readonly ?Url $keyValidationUrl;

  /**
   * The default langcode of the template's content, or NULL if unknown.
   */
  private readonly ?string $defaultLangcode;

  /**
   * The langcodes the template ships content in.
   *
   * Empty when the template has no language opinion.
   *
   * @var string[]
   */
  public readonly array $availableLangcodes;

  /**
   * The language the user chose at the start of the installer.
   *
   * NULL until the template is chosen in the installer form.
   */
  public ?string $userChosenLangcode = NULL;

  /**
   * Constructs a site template.
   *
   * @param string $name
   *   The human-readable name of the template.
   * @param string|null $screenshot
   *   The path or URL of a screenshot, or NULL to use a generic one.
   * @param string|null $path
   *   The path of the recipe, if it is already in the code base.
   * @param array<mixed>|string $package
   *   The Composer package name, or an array with the package `name` and
   *   optionally the `repository` it comes from and its `authorization` type.
   * @param string|null $description
   *   A description of the template.
   * @param array<mixed> $links
   *   Informational links, as URLs or arrays with `text` and `url`.
   * @param array<mixed> $purchase
   *   Purchase information: `price`, `url` and optionally `validation_url`.
   * @param string|null $creator
   *   Who made the template.
   * @param array{default?: string, available?: string[]} $languages
   *   The langcodes the template ships content in. Discovered automatically
   *   from the recipe's content, or provided by the curated list.
   */
  public function __construct(
    public string $name,
    ?string $screenshot = NULL,
    ?string $path = NULL,
    array|string $package = [],
    public ?string $description = NULL,
    array $links = [],
    array $purchase = [],
    public ?string $creator = NULL,
    array $languages = [],
  ) {
    $screenshot ??= dirname(__DIR__) . '/default-screenshot.webp';
    if (file_exists($screenshot)) {
      assert(str_ends_with($screenshot, '.webp'));
    }
    else {
      assert(UrlHelper::isValid($screenshot) && UrlHelper::isExternal($screenshot));
    }
    $this->screenshot = $screenshot;

    if ($path) {
      assert(is_dir($path));
      $this->locator = $path;
      $this->repository = $this->authorization = NULL;
    }
    else {
      $this->locator = $package['name'] ?? $package;

      $repository = $package['repository'] ?? NULL;
      if ($repository) {
        assert(UrlHelper::isValid($repository) && UrlHelper::isExternal($repository));
      }
      elseif (is_array($package)) {
        unset($package['authorization']);
      }
      $this->repository = $repository;
      $this->authorization = $package['authorization'] ?? NULL;
    }

    $link_objects = [];
    foreach ($links as $key => $link) {
      // The link can have custom text, or we can choose based on the array key.
      $text = $link['text'] ?? match ($key) {
        'demo' => new TranslatableMarkup('Demo'),
        default => new TranslatableMarkup('Learn more'),
      };
      $url = Url::fromUri($link['url'] ?? $link);
      assert($url->isExternal());

      $link_objects[] = Link::fromTextAndUrl($text, $url);
    }
    $this->links = $link_objects;

    $this->price = $purchase['price'] ?? 0;
    if ($this->price > 0) {
      assert(array_key_exists('url', $purchase));
      $this->purchaseUrl = Url::fromUri($purchase['url']);
      assert($this->purchaseUrl->isExternal());

      $this->keyValidationUrl = isset($purchase['validation_url'])
        ? Url::fromUri($purchase['validation_url'])
        : NULL;
      assert($this->keyValidationUrl?->isExternal() ?? TRUE);
    }
    else {
      $this->purchaseUrl = $this->keyValidationUrl = NULL;
    }

    $this->defaultLangcode = $languages['default'] ?? NULL;
    $available = array_map('strval', $languages['available'] ?? []);
    // The default langcode always counts as available.
    if ($this->defaultLangcode) {
      array_unshift($available, $this->defaultLangcode);
    }
    $this->availableLangcodes = array_unique($available);
  }

  /**
   * Returns the langcode the template wants the site to be set up in.
   *
   * If the template does not ship multilingual content, or it ships content in
   * the user's chosen language, that language is used directly. Otherwise the
   * template's default content langcode is used, so the shipped content is not
   * labeled as a language it was never translated into.
   *
   * @return string
   *   The langcode to use as the site default.
   */
  public function getDefaultLangcode(): string {
    assert(
      is_string($this->userChosenLangcode),
      'userChosenLangcode must be set before calling ' . __FUNCTION__ . '().',
    );
    if ($this->defaultLangcode === NULL || in_array($this->userChosenLangcode, $this->availableLangcodes, TRUE)) {
      return $this->userChosenLangcode;
    }
    return $this->defaultLangcode;
  }

  /**
   * Returns the screenshot, suitable for use in an <img src> attribute.
   *
   * @return string
   *   The screenshot as a base64-encoded data URI if it is a local file, or
   *   the original URL if it is an external link.
   */
  public function getScreenshot(): string {
    if (file_exists($this->screenshot)) {
      return 'data:image/webp;base64,' . base64_encode((string) file_get_contents($this->screenshot));
    }
    return $this->screenshot;
  }

  /**
   * Constructs an instance of this class from a recipe.
   *
   * The recipe can explicitly declare its languages in
   * `extra.drupal_cms_installer.languages`, using the same format as the
   * constructor's `$languages` parameter (keys: `default`, `available`).
   * If not declared, the languages are detected by scanning the recipe's
   * shipped content files.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe.
   */
  public static function createFromRecipe(Recipe $recipe): self {
    $extra = $recipe->getExtra('drupal_cms_installer');

    return new self(
      $recipe->name,
      $recipe->path . DIRECTORY_SEPARATOR . 'screenshot.webp',
      $recipe->path,
      [],
      $recipe->description,
      $extra['links'] ?? [],
      [],
      $extra['creator'] ?? NULL,
      $extra['languages'] ?? self::detectLanguages($recipe),
    );
  }

  /**
   * Finds the default langcode of a recipe's shipped content.
   *
   * Each content file can declare a `default_langcode` in its `_meta` section.
   * This method returns the first one it finds. It does not look at the
   * recipe's `extra` metadata -- that override is the caller's responsibility.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe whose content to scan.
   *
   * @return string|null
   *   The first default langcode found, or NULL when the recipe has no content
   *   or none of its content declares a langcode.
   */
  private static function detectDefaultLangcode(Recipe $recipe): ?string {
    foreach ($recipe->content->data as $item) {
      $langcode = $item['_meta']['default_langcode'] ?? NULL;
      if ($langcode) {
        assert(is_string($langcode));
        return $langcode;
      }
    }
    return NULL;
  }

  /**
   * Collects every language a recipe's shipped content exists in.
   *
   * This scans all content files for `_meta.default_langcode` values and
   * translation langcodes (the keys under `translations`), and returns them
   * as a flat, deduplicated list.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe whose content to scan.
   *
   * @return string[]
   *   The langcodes found, or an empty array when the recipe has no content.
   */
  private static function getLangcodesFromContent(Recipe $recipe): array {
    $langcodes = [];

    foreach ($recipe->content->data as $item) {
      $default = $item['_meta']['default_langcode'] ?? NULL;
      if ($default) {
        $langcodes[] = $default;
      }
      array_push($langcodes, ...array_keys($item['translations'] ?? []));
    }
    assert(Inspector::assertAllStrings($langcodes));

    return array_unique($langcodes);
  }

  /**
   * Detects the language metadata of a recipe from its shipped content.
   *
   * Most recipes ship content in a single language (usually English) without
   * any translations. Every content file has a `default_langcode` in its
   * metadata, so even a plain English-only recipe looks like it has language
   * information -- you'd find ['en'].
   *
   * Without the monolingual check, getDefaultLangcode() would see that 'en' is
   * the only available language, and force the site into English even when the
   * user chose German. That's wrong: a monolingual recipe doesn't care which
   * language the site runs in. Drupal core imports its content in whatever
   * language the site is set up in.
   *
   * So: if the content only has one language, we return an empty array -- the
   * recipe has no language opinion. Only genuine multilingual content (2+
   * languages) produces a result.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe whose content to scan.
   *
   * @return array{default?: string|null, available?: string[]}
   *   The detected languages, or an empty array for monolingual recipes.
   */
  private static function detectLanguages(Recipe $recipe): array {
    $available_langcodes = self::getLangcodesFromContent($recipe);

    if (count($available_langcodes) < 2) {
      return [];
    }
    return [
      'default' => self::detectDefaultLangcode($recipe),
      'available' => $available_langcodes,
    ];
  }

  /**
   * Returns the directory where recipes are installed.
   *
   * Placeholder tokens, like {$name} and {$vendor}, are left as-is. If the
   * recipe directory cannot be determined, returns NULL.
   */
  private static function getRecipeDirectory(): ?string {
    // This is expensive to compute, and very unlikely to change in a request.
    static $cookbook;
    if (isset($cookbook)) {
      return $cookbook;
    }

    ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    $project_root = realpath($project_root);
    assert(is_string($project_root));

    // This is needed for Composer to work properly. Nothing should actually be
    // written here, since we're doing a strictly read-only operation.
    $home = Platform::getEnv('COMPOSER_HOME');
    if (empty($home)) {
      Platform::putEnv('COMPOSER_HOME', $project_root . DIRECTORY_SEPARATOR . '.composer');
    }

    // Get the installer paths from Composer directly. We need to do this so
    // that plugins, which may influence the result, will run. (Plugin effects
    // are inconsistently applied by the `composer config` command, so running
    // Composer via Symfony's Console or Process components won't suffice here.)
    try {
      $extra = (new Factory())
        ->createComposer(new NullIO(), $project_root . DIRECTORY_SEPARATOR . 'composer.json')
        ->getPackage()
        ->getExtra();
    }
    finally {
      // If we had to set COMPOSER_HOME, undo that.
      if (empty($home)) {
        Platform::clearEnv('COMPOSER_HOME');
      }
    }
    $directory = array_find_key(
      $extra['installer-paths'] ?? [],
      fn (array $criteria): bool => in_array('type:' . Recipe::COMPOSER_PROJECT_TYPE, $criteria, TRUE),
    );
    if ($directory) {
      $cookbook = $project_root . DIRECTORY_SEPARATOR . $directory;
    }
    return $cookbook;
  }

  /**
   * Returns the local install path of the site template.
   *
   * The returned path may or may not exist; if the site template is not known
   * to Composer, its expected path will be guessed by extrapolating Composer's
   * configuration.
   */
  public function getPath(): string {
    if (is_dir($this->locator)) {
      // It should be impossible for this to return FALSE, because we just
      // confirmed that it's an extant directory.
      // @phpstan-ignore return.type
      return realpath($this->locator);
    }

    try {
      return InstalledVersions::getInstallPath($this->locator);
    }
    catch (\OutOfBoundsException $e) {
      // Composer doesn't know where it is, so try to extrapolate the path.
      return str_replace(
        ['{$vendor}', '{$name}'],
        explode('/', $this->locator, 2),
        // If we can't tell where Composer is configured to install recipes,
        // it's time to panic.
        self::getRecipeDirectory() ?? throw $e,
      );
    }
  }

  /**
   * Scans the recipe directory for site template recipes.
   *
   * @return iterable<string, self>
   *   An iterable of recipes, keyed by machine name.
   */
  public static function findAll(): iterable {
    // The general recipe directory will not have package-specific placeholders,
    // because that makes no sense.
    $directory = str_replace(['{$name}', '{$vendor}'], '', self::getRecipeDirectory());
    // In `composer.json`, the directory separator is always a forward slash
    // because a backslash is for escaping.
    $directory = rtrim($directory, '/');

    $finder = Finder::create()
      ->in($directory)
      ->files()
      ->followLinks()
      ->name('recipe.yml');

    foreach ($finder as $file) {
      try {
        $recipe = Recipe::createFromDirectory($file->getPath());
        if ($recipe->type === 'Site') {
          yield basename($recipe->path) => self::createFromRecipe($recipe);
        }
      }
      catch (RecipeFileException $e) {
        // The recipe is unusable in the current environment; log the complaint
        // and move on to the next guy.
        \Drupal::messenger()->addError($e->getMessage());
      }
    }
  }

}
