<?php

declare(strict_types=1);

namespace Drupal\ui_icons_font\Plugin\IconExtractor;

// cspell:ignore codepoints
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\Attribute\IconExtractor;
use Drupal\Core\Theme\Icon\Exception\IconPackConfigErrorException;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\IconDefinitionInterface;
use Drupal\Core\Theme\Icon\IconExtractorBase;
use Drupal\Core\Theme\Icon\IconPackExtractorForm;
use FontLib\Font;

/**
 * Plugin implementation of the icon_extractor.
 */
#[IconExtractor(
  id: 'font',
  label: new TranslatableMarkup('Web Font'),
  description: new TranslatableMarkup('Provide Icons from web fonts.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class FontExtractor extends IconExtractorBase {

  /**
   * {@inheritdoc}
   */
  public function discoverIcons(): array {
    if (!isset($this->configuration['config']['sources'])) {
      throw new IconPackConfigErrorException(sprintf('Missing `config: sources` in your definition, extractor %s require this value.', $this->getPluginId()));
    }

    $icons = [];
    foreach ($this->configuration['config']['sources'] as $filename) {
      $filepath = sprintf('%s/%s', $this->configuration['absolute_path'], $filename);
      $fileinfo = pathinfo($filepath);

      if (!isset($fileinfo['extension'])) {
        continue;
      }

      switch ($fileinfo['extension']) {
        case 'codepoints':
          $icons = array_merge($icons, $this->getCodePoints($filepath));
          break;

        case 'ttf':
        case 'woff':
          $icons = array_merge($icons, $this->getFontIcons($filepath));
          break;

        case 'json':
          $icons = array_merge($icons, $this->getJsonIcons($filepath));
          break;

        case 'yml':
        case 'yaml':
          $icons = array_merge($icons, $this->getYamlIcons($filepath));
          break;

        default:
          break;
      }
    }

    if (isset($this->configuration['config']['offset'])) {
      $icons = array_slice($icons, (int) $this->configuration['config']['offset']);
    }

    return $icons;
  }

  /**
   * {@inheritdoc}
   */
  public function loadIcon(array $icon_data): ?IconDefinitionInterface {
    if (!isset($icon_data['icon_id']) || empty($icon_data['icon_id'])) {
      return NULL;
    }

    $data = [];
    if (isset($icon_data['content'])) {
      $data['content'] = $icon_data['content'];
    }

    return $this->createIcon(
      $icon_data['icon_id'],
      '',
      NULL,
      $data,
    );
  }

  /**
   * Wrapper to get a file content.
   *
   * @param string $filepath
   *   The absolute path to the file.
   *
   * @return string|bool
   *   File content or false.
   */
  private function getFileContents(string $filepath): string|bool {
    return file_get_contents($filepath);
  }

  /**
   * Extract Icon names from TTF or Woff file.
   *
   * @param string $filepath
   *   The Code points file absolute path.
   *
   * @return array
   *   List of icons indexed by ID.
   */
  private function getFontIcons(string $filepath): array {
    $icons = [];

    if (!class_exists('\FontLib\Font')) {
      // @todo log error?
      return [];
    }

    $font = Font::load($filepath);
    if (NULL === $font) {
      return [];
    }

    $font->parse();
    $icons_names = $font->getData('post')['names'] ?? [];
    $icons = [];
    foreach ($icons_names as $icon_id) {
      $id = IconDefinition::createIconId($this->configuration['id'], (string) $icon_id);
      $icons[$id] = [];
    }

    return $icons;
  }

  /**
   * Extract Icon names from Json file.
   *
   * @param string $filepath
   *   The Code points file absolute path.
   *
   * @return array
   *   List of icons indexed by ID.
   */
  private function getJsonIcons(string $filepath): array {
    if (!$data = $this->getFileContents($filepath)) {
      return [];
    }

    if (!json_validate((string) $data)) {
      // @todo log error?
      return [];
    }

    $icons = [];
    $icon_json_ids = array_keys(json_decode((string) $data, TRUE));
    foreach ($icon_json_ids as $icon_id) {
      $id = IconDefinition::createIconId($this->configuration['id'], (string) $icon_id);
      $icons[$id] = [];
    }

    return $icons;
  }

  /**
   * Extract Icon names from Yaml file.
   *
   * @param string $filepath
   *   The Code points file absolute path.
   *
   * @return array
   *   List of icons indexed by ID.
   */
  private function getYamlIcons(string $filepath): array {
    if (!$data = $this->getFileContents($filepath)) {
      return [];
    }

    try {
      $data = Yaml::decode((string) $data);
    }
    catch (InvalidDataTypeException $e) {
      // @todo log error?
      return [];
    }

    if (empty($data)) {
      // @todo log warning?
      return [];
    }

    $icons = [];
    foreach (array_keys($data) as $icon_id) {
      $id = IconDefinition::createIconId($this->configuration['id'], (string) $icon_id);
      $icons[$id] = [];
    }

    return $icons;
  }

  /**
   * Extract Icon codepoints from codepoints file.
   *
   * @param string $filepath
   *   The Code points file absolute path.
   *
   * @return array
   *   List of icons indexed by ID.
   */
  private function getCodePoints(string $filepath): array {
    if (!$data = $this->getFileContents($filepath)) {
      return [];
    }

    $data_lines = explode("\n", (string) $data);
    $icons = [];
    foreach ($data_lines as $line) {
      $values = explode(' ', $line);
      if (empty($values) || !isset($values[1])) {
        continue;
      }
      $icon_id = $values[0];
      if (0 === strlen($icon_id)) {
        continue;
      }
      $id = IconDefinition::createIconId($this->configuration['id'], (string) $icon_id);
      $icons[$id] = [
        'content' => $values[1],
      ];
    }

    return $icons;
  }

}
