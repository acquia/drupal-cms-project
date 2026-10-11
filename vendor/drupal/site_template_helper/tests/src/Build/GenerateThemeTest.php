<?php

declare(strict_types=1);

namespace Drupal\Tests\site_template_helper\Build;

use Composer\InstalledVersions;
use Composer\Json\JsonFile;
use Drupal\BuildTests\Framework\BuildTestBase;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Symfony\Component\Filesystem\Filesystem;

final class GenerateThemeTest extends BuildTestBase {

  public function testGenerateThemeForSiteTemplate(): void {
    $workspace = $this->getWorkspaceDirectory();

    // Copy the fixture to the workspace.
    (new Filesystem())->mirror(
      dirname(__DIR__, 2) . '/fixture',
      $workspace,
    );
    // Create a vendor repository with all installed dependencies so we can
    // build a Drupal code base.
    // @see fixture/composer.json
    $file = new JsonFile($workspace . '/vendor.json');
    $vendor = [];
    foreach (InstalledVersions::getInstalledPackages() as $name) {
      $path = InstalledVersions::getInstallPath($name) . '/composer.json';
      // Certain packages (i.e., metapackages) are not physically installed.
      if (file_exists($path)) {
        $data = Json::decode(file_get_contents($path));
        $this->assertIsArray($data, "$path is not valid JSON.");

        $version = InstalledVersions::getVersion($name);
        $vendor['packages'][$name][$version] = [
          'name' => $name,
          'version' => $version,
          'dist' => [
            'type' => 'path',
            'url' => dirname($path),
          ],
        ] + $data;
      }
    }
    $file->write($vendor);

    $blank_dir = $workspace . '/themes/blank';
    $blank_info_file = $blank_dir . '/blank.info.yml';

    // Always mirror path repositories to prevent symlinking shenanigans.
    $process = $this->executeCommand('COMPOSER_MIRROR_PATH_REPOS=1 composer install --no-ansi --no-interaction -vvv');
    $this->assertCommandSuccessful();
    $output = $process->getOutput();
    $this->assertStringContainsString('Generated blank theme for drupal/from_starterkit_theme', $output);
    $this->assertStringContainsString('Generated info_only theme for drupal/info_only_theme', $output);

    $this->assertFileExists($blank_info_file);
    $info = Yaml::decode(file_get_contents($blank_info_file));
    $this->assertIsArray($info);
    // The regions defined in the fake site template should be the ones in the
    // generated info file.
    $this->assertSame(['header', 'content', 'footer'], array_keys($info['regions']));
    $this->assertDirectoryExists($blank_dir . '/css');
    $this->assertDirectoryExists($blank_dir . '/templates');

    // We should be able to define a theme with absolutely nothing in it except
    // an info file.
    $info_only_dir = $workspace . '/themes/info_only';
    $info_only_file = $info_only_dir . '/info_only.info.yml';
    $this->assertFileExists($info_only_file);
    $info = Yaml::decode(file_get_contents($info_only_file));
    $this->assertIsArray($info);
    $this->assertSame('Info Only', $info['name']);
    $this->assertSame('theme', $info['type']);
    $this->assertFalse($info['base theme']);
    $this->assertSame('1.0.0', $info['version']);
    $this->assertMatchesRegularExpression('/^\^[0-9]+$/', $info['core_version_requirement']);
    $this->assertDirectoryDoesNotExist($info_only_dir . '/css');
    $this->assertDirectoryDoesNotExist($info_only_dir . '/templates');
  }

}
