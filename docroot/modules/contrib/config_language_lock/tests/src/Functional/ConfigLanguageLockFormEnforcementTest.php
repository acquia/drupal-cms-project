<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests language selector visibility and langcode enforcement on config forms.
 *
 * Without a lock, the langcode selector is shown on config entity forms and
 * the saved langcode follows the request language (URL prefix). With a lock
 * set, the selector is hidden and the saved langcode is always the locked
 * language regardless of the request language prefix used to submit the form.
 * This is tested for menus, date formats, vocabularies, and views.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockFormEnforcementTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'node',
    'menu_ui',
    'taxonomy',
    'views_ui',
    'config_language_lock',
  ];

  /**
   * Tests language selector is shown and langcode follows request language.
   *
   * Without a locked language, the langcode selector is visible. When the form
   * is submitted via a non-default-language URL prefix, the saved entity gets
   * that request language as its langcode due to the language being selected
   * automatically in the dropdown. This is tested with both an English site
   * default and a non-English site default (de) — in both cases the request
   * language (xx) differs from the site default, and xx wins.
   */
  public function testFormsShowLanguageSelectorAndFollowRequestLanguageWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer menu',
      'administer taxonomy',
      'administer site configuration',
      'administer views',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('xx', 'yy');
    $this->enableLanguageUrlPrefixNegotiation();

    // Scenario 1: English site default (en). Request language xx differs from
    // the en default. Langcode selector defaults to the request language (xx),
    // so xx is saved.
    $this->assertMenuFormBehavior('xx', 'xx', TRUE);
    $this->assertDateFormatFormBehavior('xx', 'xx', TRUE);
    $this->assertVocabularyFormBehavior('xx', 'xx', TRUE);
    // No langcode selector on the view add form;
    // WizardPluginBase::instantiateView() calls getDefaultLanguage()->getId(),
    // so en is saved.
    $this->assertViewFormBehavior('xx', 'en', TRUE);

    // Scenario 2: Non-English site default (de). Request language xx still
    // differs from the de default.
    $this->createLanguages('de');
    $this->config('system.site')->set('default_langcode', 'de')->save();
    // Langcode selector defaults to the request language (xx), so xx is saved.
    $this->assertMenuFormBehavior('xx', 'xx', TRUE);
    $this->assertDateFormatFormBehavior('xx', 'xx', TRUE);
    $this->assertVocabularyFormBehavior('xx', 'xx', TRUE);
    // No langcode selector on the view add form;
    // WizardPluginBase::instantiateView() calls getDefaultLanguage()->getId(),
    // so de is saved.
    $this->assertViewFormBehavior('xx', 'de', TRUE);
  }

  /**
   * Tests hidden language selectors and enforced langcode on save.
   *
   * With a locked language, the langcode selector is hidden. When the form is
   * submitted via a different language URL prefix, the saved entity still gets
   * the locked language, not the request language.
   */
  public function testFormsHideLanguageAndSaveWithLockedLangcode(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer menu',
      'administer taxonomy',
      'administer site configuration',
      'administer views',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('xx', 'yy');
    $this->enableLanguageUrlPrefixNegotiation();

    foreach (['xx', 'yy'] as $locked_langcode) {
      // Submit forms via the other language prefix to confirm lock wins.
      $request_langcode = $locked_langcode === 'xx' ? 'yy' : 'xx';
      $this->setLockedLanguage($locked_langcode);
      $this->assertMenuFormBehavior($request_langcode, $locked_langcode, FALSE);
      $this->assertDateFormatFormBehavior($request_langcode, $locked_langcode, FALSE);
      $this->assertVocabularyFormBehavior($request_langcode, $locked_langcode, FALSE);
      $this->assertViewFormBehavior($request_langcode, $locked_langcode, FALSE);
    }
  }

  /**
   * Asserts menu form behavior for language selector and saved langcode.
   *
   * @param string $request_langcode
   *   The URL prefix language to submit the form with.
   * @param string $expected_langcode
   *   The langcode expected on the saved entity.
   * @param bool $selector_visible
   *   Whether the langcode selector should be visible on the form.
   */
  protected function assertMenuFormBehavior(string $request_langcode, string $expected_langcode, bool $selector_visible): void {
    $menu_id = 'lock-' . $request_langcode . '-' . strtolower($this->randomMachineName(6));
    $menu_label = 'Menu ' . strtoupper($request_langcode) . ' ' . $this->randomMachineName(6);

    $this->drupalGet($request_langcode . '/admin/structure/menu/add');
    if ($selector_visible) {
      $this->assertSession()->fieldExists('edit-langcode');
    }
    else {
      $this->assertSession()->fieldNotExists('edit-langcode');
    }
    $this->submitForm([
      'id' => $menu_id,
      'label' => $menu_label,
      'description' => 'Language enforcement test menu',
    ], 'Save');

    $menu = \Drupal::entityTypeManager()->getStorage('menu')->load($menu_id);
    $this->assertNotNull($menu);
    $this->assertSame($expected_langcode, $menu->get('langcode'));

    $this->drupalGet($request_langcode . '/admin/structure/menu/manage/' . $menu_id);
    if ($selector_visible) {
      $this->assertSession()->fieldExists('edit-langcode');
    }
    else {
      $this->assertSession()->fieldNotExists('edit-langcode');
    }
  }

  /**
   * Asserts date format form behavior for language selector and saved langcode.
   *
   * @param string $request_langcode
   *   The URL prefix language to submit the form with.
   * @param string $expected_langcode
   *   The langcode expected on the saved entity.
   * @param bool $selector_visible
   *   Whether the langcode selector should be visible on the form.
   */
  protected function assertDateFormatFormBehavior(string $request_langcode, string $expected_langcode, bool $selector_visible): void {
    $date_format_id = 'lock_' . $request_langcode . '_' . strtolower($this->randomMachineName(6));

    $this->drupalGet($request_langcode . '/admin/config/regional/date-time/formats/add');
    if ($selector_visible) {
      $this->assertSession()->fieldExists('edit-langcode');
    }
    else {
      $this->assertSession()->fieldNotExists('edit-langcode');
    }
    $this->submitForm([
      'id' => $date_format_id,
      'label' => 'Lock ' . strtoupper($request_langcode) . ' ' . $this->randomMachineName(6),
      'date_format_pattern' => 'Y-m-d H:i',
    ], 'Add format');

    $date_format = \Drupal::entityTypeManager()->getStorage('date_format')->load($date_format_id);
    $this->assertNotNull($date_format);
    $this->assertSame($expected_langcode, $date_format->get('langcode'));

    $this->drupalGet($request_langcode . '/admin/config/regional/date-time/formats/manage/' . $date_format_id);
    if ($selector_visible) {
      $this->assertSession()->fieldExists('edit-langcode');
    }
    else {
      $this->assertSession()->fieldNotExists('edit-langcode');
    }
  }

  /**
   * Asserts vocabulary form behavior for language selector and saved langcode.
   *
   * @param string $request_langcode
   *   The URL prefix language to submit the form with.
   * @param string $expected_langcode
   *   The langcode expected on the saved entity.
   * @param bool $selector_visible
   *   Whether the langcode selector should be visible on the form.
   */
  protected function assertVocabularyFormBehavior(string $request_langcode, string $expected_langcode, bool $selector_visible): void {
    $vid = 'lock_' . $request_langcode . '_' . strtolower($this->randomMachineName(6));

    $this->drupalGet($request_langcode . '/admin/structure/taxonomy/add');
    if ($selector_visible) {
      $this->assertSession()->fieldExists('edit-langcode');
    }
    else {
      $this->assertSession()->fieldNotExists('edit-langcode');
    }
    $this->submitForm([
      'name' => 'Vocabulary ' . strtoupper($request_langcode) . ' ' . $this->randomMachineName(6),
      'description' => 'Language enforcement test vocabulary',
      'vid' => $vid,
    ], 'Save');

    $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vid);
    $this->assertNotNull($vocabulary);
    $this->assertSame($expected_langcode, $vocabulary->get('langcode'));

    $this->drupalGet($request_langcode . '/admin/structure/taxonomy/manage/' . $vid);
    if ($selector_visible) {
      $this->assertSession()->fieldExists('edit-langcode');
    }
    else {
      $this->assertSession()->fieldNotExists('edit-langcode');
    }
  }

  /**
   * Asserts view add form behavior for langcode enforcement.
   *
   * Creates a new view via the add form. The add form has no langcode selector;
   * the views wizard sets the langcode to the site default language in
   * WizardPluginBase::instantiateView(). With a lock, entityPresave overrides
   * the saved langcode. After save, the edit-details form is checked for
   * selector visibility.
   *
   * @param string $request_langcode
   *   The URL prefix language to submit the form with.
   * @param string $expected_langcode
   *   The langcode expected on the saved entity.
   * @param bool $selector_visible
   *   Whether the langcode selector should be visible on the edit-details form.
   */
  protected function assertViewFormBehavior(string $request_langcode, string $expected_langcode, bool $selector_visible): void {
    $view_id = 'lock_view_' . $request_langcode . '_' . strtolower($this->randomMachineName(6));

    $this->drupalGet($request_langcode . '/admin/structure/views/add');
    $this->submitForm([
      'label' => 'Lock view ' . strtoupper($request_langcode),
      'id' => $view_id,
    ], 'Save and edit');

    $view = \Drupal::entityTypeManager()->getStorage('view')->load($view_id);
    $this->assertNotNull($view);
    $this->assertSame($expected_langcode, $view->get('langcode'));

    $this->drupalGet($request_langcode . "/admin/structure/views/nojs/edit-details/$view_id/default");
    if ($selector_visible) {
      $this->assertSession()->fieldExists('View language');
    }
    else {
      $this->assertSession()->fieldNotExists('View language');
    }
  }

}
