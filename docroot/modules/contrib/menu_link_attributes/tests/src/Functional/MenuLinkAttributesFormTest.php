<?php

namespace Drupal\Tests\menu_link_attributes\Functional;

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the menu_link_attributes UI.
 *
 * @group menu_link_attributes
 */
class MenuLinkAttributesFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'menu_link_content',
    'menu_link_attributes',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests attributes are saved correctly when editing a menu link.
   */
  public function testMenuLinkAttributesForm(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer menu',
      'link to any page',
      'use menu link attributes',
    ]));

    $menu_link = MenuLinkContent::create([
      'title' => 'Menu link test',
      'provider' => 'menu_link_content',
      'menu_name' => 'admin',
      'link' => [
        'uri' => 'internal:/user/login',
        'options' => [
          'attributes' => [
            'class' => ['foo'],
          ],
        ],
      ],
    ]);
    $menu_link->save();

    $this->drupalGet($menu_link->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('attributes[class]', 'foo');

    $this->submitForm([
      'attributes[class]' => 'bar',
    ], 'Save');

    // Attributes should be replaced on save.
    $menuLinkContent = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadUnchanged($menu_link->id());
    $options = $menuLinkContent->getUrlObject()->getOptions();
    $this->assertEquals(['bar'], $options['attributes']['class']);
  }

  /**
   * Tests that unsafe attribute names are rejected in the configuration.
   */
  public function testUnsafeAttributeConfig(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer menu link attributes',
    ]));

    $unsafe_configs = [
      "attributes:\n  container_onmouseover:\n    label: 'LI mouseover'",
      "attributes:\n  onclick:\n    label: 'Click'",
      "attributes:\n  OnFocus:\n    label: 'Focus'",
      "attributes:\n  'x onclick=alert(1) y':\n    label: 'Broken'",
    ];

    foreach ($unsafe_configs as $unsafe_config) {
      $this->drupalGet('admin/config/menu_link_attributes/config');
      $this->submitForm(['config' => $unsafe_config], 'Save configuration');
      $this->assertSession()->pageTextContains('is not allowed');
    }

    // Names with underscores get a hyphenated suggestion.
    $this->drupalGet('admin/config/menu_link_attributes/config');
    $this->submitForm(['config' => "attributes:\n  container_menu_color:\n    label: 'Color'"], 'Save configuration');
    $this->assertSession()->pageTextContains('use container_menu-color instead');

    $attributes = $this->config('menu_link_attributes.config')->get('attributes');
    $this->assertArrayHasKey('class', $attributes);
    $this->assertArrayNotHasKey('container_menu_color', $attributes);
    $this->assertArrayNotHasKey('container_onmouseover', $attributes);
    $this->assertArrayNotHasKey('onclick', $attributes);

    // Container attributes with a safe name are allowed.
    $this->drupalGet('admin/config/menu_link_attributes/config');
    $this->submitForm(['config' => "attributes:\n  container_class:\n    label: 'LI class'"], 'Save configuration');
    $this->assertSession()->pageTextNotContains('is not allowed');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $attributes = $this->config('menu_link_attributes.config')->get('attributes');
    $this->assertArrayHasKey('container_class', $attributes);
  }

  /**
   * Tests that unsafe stored attributes are not rendered.
   */
  public function testUnsafeAttributesAreNotRendered(): void {
    $this->drupalPlaceBlock('system_menu_block:main');

    MenuLinkContent::create([
      'title' => 'Unsafe link',
      'provider' => 'menu_link_content',
      'menu_name' => 'main',
      'link' => [
        'uri' => 'internal:/',
        'options' => [
          'attributes' => [
            'onclick' => 'alert(1)',
            'data-safe' => 'link',
          ],
          'container_attributes' => [
            'onmouseover' => 'alert(2)',
            'x onclick=alert(3) y' => 'broken',
            'data-safe' => 'container',
          ],
        ],
      ],
    ])->save();

    $this->drupalGet('user/login');
    $this->assertSession()->linkExists('Unsafe link');
    $this->assertSession()->elementExists('css', 'li[data-safe="container"]');
    $this->assertSession()->elementExists('css', 'a[data-safe="link"]');
    $this->assertSession()->responseNotContains('alert(1)');
    $this->assertSession()->responseNotContains('alert(2)');
    $this->assertSession()->responseNotContains('alert(3)');
  }

}
