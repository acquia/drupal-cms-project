<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests the trash ignore functionality.
 *
 * @group trash
 */
class TrashIgnoreTest extends BrowserTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'trash',
    'trash_test',
    'user',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create Basic page node type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'page',
        'name' => 'Basic Page',
        'display_submitted' => FALSE,
      ]);
    }
  }

  /**
   * Test ignore context on multilingual sites.
   */
  public function testIgnoreOnMultilingualSite(): void {
    // Create test language.
    ConfigurableLanguage::createFromLangcode('de')->save();

    $trash_user = $this->drupalCreateUser([
      'access content',
      'delete own page content',
      'access trash',
      'view deleted entities',
      'access administration pages',
      'view the administration theme',
    ]);

    // Create a trashed node.
    $node = $this->drupalCreateNode();
    $node
      ->setOwner($trash_user)
      ->save();

    // Set the interface language to use the preferred administration language
    // and then the URL.
    /** @var \Drupal\language\LanguageNegotiatorInterface $language_negotiator */
    $language_negotiator = $this->container->get('language_negotiator');
    $language_negotiator->saveConfiguration('language_interface', [
      'language-user-admin' => 1,
      'language-url' => 2,
      'language-selected' => 3,
    ]);

    // Use a custom user admin language.
    $trash_user
      ->set('preferred_admin_langcode', 'de')
      ->save();

    // Log the user in.
    $this->drupalLogin($trash_user);

    // Delete the node.
    $this->drupalGet($node->toUrl('delete-form'));
    $this->submitForm([], 'Delete');
    $this->assertSession()->statusCodeEquals(200);

    // Trigger the language negotiation earlier than normal.
    \Drupal::keyValue('trash_test')->set('early_language_negotiation', TRUE);

    $this->drupalGet($node->toUrl());
    // The user cannot view the trashed content without the 'in_trash' query
    // string / context.
    $this->assertSession()->statusCodeEquals(404);

    $this->drupalGet($node->toUrl(NULL, [
      'query' => [
        'in_trash' => '1',
      ],
    ]));
    // The user can now view the trashed content.
    $this->assertSession()->statusCodeEquals(200);
  }

}
