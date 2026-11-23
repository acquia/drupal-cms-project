<?php

namespace Drupal\Tests\easy_email\Functional;

/**
 * Tests the footer section of the Theme settings tab.
 *
 * @group easy_email
 */
class EasyEmailThemeSettingsTest extends EasyEmailTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    $permissions = parent::getAdministratorPermissions();
    $permissions[] = 'access easy email settings';
    $permissions[] = 'administer themes';
    return $permissions;
  }

  /**
   * Tests the footer section shows only while Easy Email Theme is enabled.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testFooterSectionNeedsTheme() {
    $this->drupalGet('admin/structure/email-templates/theme');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->checkboxNotChecked('easy_email_theme[enable]');
    $this->assertSession()->fieldNotExists('easy_email_theme[footer][enabled]');

    $this->submitForm(['easy_email_theme[enable]' => TRUE], 'Save theme settings');
    $this->assertSession()->pageTextContains('Easy Email Theme has been installed.');

    // The footer is off by default, and the theme shows both parts once on.
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-easy-email-theme-footer"]');
    $this->assertSession()->checkboxNotChecked('easy_email_theme[footer][enabled]');
    $this->assertSession()->checkboxChecked('easy_email_theme[footer][settings][site_name]');
    $this->assertSession()->checkboxChecked('easy_email_theme[footer][settings][slogan]');
    $this->assertSession()->linkExists('Basic site settings');

    // Site name and Slogan follow Show a footer.
    $settings = $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-easy-email-theme-footer-settings"]');
    $states = json_decode($settings->getAttribute('data-drupal-states'), TRUE);
    $this->assertEquals(['checked' => TRUE], $states['visible']['input[name="easy_email_theme[footer][enabled]"]']);
  }

  /**
   * Tests saving the footer settings.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testFooterSettingsSave() {
    \Drupal::service('theme_installer')->install(['easy_email_theme']);

    $this->drupalGet('admin/structure/email-templates/theme');
    $this->submitForm([
      'easy_email_theme[footer][enabled]' => TRUE,
      'easy_email_theme[footer][settings][site_name]' => TRUE,
      'easy_email_theme[footer][settings][slogan]' => FALSE,
    ], 'Save theme settings');
    $this->assertSession()->pageTextContains('The theme settings have been saved.');
    $this->assertSession()->checkboxChecked('easy_email_theme[footer][enabled]');
    $this->assertSession()->checkboxChecked('easy_email_theme[footer][settings][site_name]');
    $this->assertSession()->checkboxNotChecked('easy_email_theme[footer][settings][slogan]');

    $config = $this->config('easy_email_theme.settings');
    $this->assertTrue($config->get('footer.enabled'));
    $this->assertTrue($config->get('footer.site_name'));
    $this->assertFalse($config->get('footer.slogan'));

    // Turning the footer off keeps the chosen parts for next time.
    $this->submitForm([
      'easy_email_theme[footer][enabled]' => FALSE,
    ], 'Save theme settings');
    $this->assertSession()->checkboxNotChecked('easy_email_theme[footer][enabled]');

    $config = $this->config('easy_email_theme.settings');
    $this->assertFalse($config->get('footer.enabled'));
    $this->assertTrue($config->get('footer.site_name'));
    $this->assertFalse($config->get('footer.slogan'));
  }

}
