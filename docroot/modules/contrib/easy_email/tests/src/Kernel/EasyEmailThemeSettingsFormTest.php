<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_email\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\easy_email\Form\EasyEmailThemeSettingsForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests the footer settings saved by the Theme settings form.
 */
#[Group('easy_email')]
#[RunTestsInSeparateProcesses]
class EasyEmailThemeSettingsFormTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'token', 'easy_email'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system']);
    $this->setUpCurrentUser([], ['administer themes']);
    $this->container->get('theme_installer')->install(['easy_email_theme']);
  }

  /**
   * Tests the footer values are saved as the theme's config schema expects.
   *
   * Kernel tests check the schema on save, so a wrong type fails here.
   */
  #[TestWith([TRUE, TRUE, TRUE])]
  #[TestWith([TRUE, TRUE, FALSE])]
  #[TestWith([TRUE, FALSE, TRUE])]
  #[TestWith([FALSE, TRUE, TRUE])]
  public function testFooterSettings(bool $enabled, bool $site_name, bool $slogan): void {
    // A programmed checkbox is unticked only when its value is NULL.
    $form_state = (new FormState())->setValues([
      'easy_email_theme' => [
        'available' => TRUE,
        'enable' => 1,
        'logo' => [
          'use_default' => 1,
          'settings' => ['path' => ''],
        ],
        'footer' => [
          'enabled' => $enabled ?: NULL,
          'settings' => [
            'site_name' => $site_name ?: NULL,
            'slogan' => $slogan ?: NULL,
          ],
        ],
      ],
    ]);
    $this->container->get('form_builder')->submitForm(EasyEmailThemeSettingsForm::class, $form_state);
    $this->assertEmpty($form_state->getErrors());

    $config = $this->config('easy_email_theme.settings');
    $this->assertSame($enabled, $config->get('footer.enabled'));
    $this->assertSame($site_name, $config->get('footer.site_name'));
    $this->assertSame($slogan, $config->get('footer.slogan'));
  }

}
