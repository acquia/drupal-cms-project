<?php

namespace Drupal\Tests\custom_field_sdc\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that view modes render through a component.
 *
 * @group custom_field_sdc
 *
 * @internal
 */
final class ComponentViewModeRenderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'field_ui',
    'custom_field',
    'custom_field_sdc',
    'custom_field_sdc_test',
    'sdc_test',
  ];

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->adminUser = $this->drupalCreateUser(['access content', 'administer node display']);
    $display = EntityViewDisplay::load('node.article.default');
    $display->setThirdPartySetting('custom_field_sdc', 'settings', [
      'enabled' => '1',
      'component' => 'sdc_test:my-banner',
      'props' => [
        'heading' => [
          'widget' => 'string',
          'value' => 'Join us at The Conference',
        ],
        'ctaText' => [
          'widget' => 'string',
          'value' => 'Click me',
        ],
        'ctaHref' => [
          'widget' => 'string',
          'value' => 'https://www.example.org',
        ],
        'ctaTarget' => [
          'widget' => 'string',
          'value' => '_blank',
        ],
        'image' => [
          'widget' => 'string',
          'value' => '',
        ],
      ],
      'slots' => [
        'banner_body' => [
          'source' => 'field',
          'field' => 'body',
        ],
      ],
    ]);
    $display->save();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests view mode render.
   */
  public function testMyBanner(): void {
    $display = EntityViewDisplay::load('node.article.default');
    $session = $this->assertSession();

    // 1. Test with values.
    $node1 = $this->drupalCreateNode([
      'title' => $this->randomString(),
      'type' => 'article',
      'body' => $this->randomString(),
    ]);
    $this->drupalGet('/node/' . $node1->id());
    $session->elementTextEquals('css', '[data-component-id="sdc_test:my-banner"] h3', 'Join us at The Conference');
    $session->elementTextEquals('css', '[data-component-id="sdc_test:my-banner"] .component--my-banner--body', $node1->get('body')->first()->value);
    $cta = $session->elementExists('css', '[data-component-id="sdc_test:my-cta"]');
    $this->assertSame('_blank', $cta->getAttribute('target'));
    $this->assertSame('https://www.example.org', $cta->getAttribute('href'));
    $this->assertSame('Click me', $cta->getText());
  }

  /**
   * Tests a card-list component from the custom_field_test_sdc module.
   */
  public function testCardList(): void {
    $display = EntityViewDisplay::load('node.article.default');
    $display->setThirdPartySetting('custom_field_sdc', 'settings', [
      'enabled' => '1',
      'component' => 'custom_field_sdc_test:card-list',
      'props' => [
        'attributes' => [
          'widget' => 'attributes',
          'value' => [
            'class' => 'card-list',
          ],
        ],
        'cards' => [
          'widget' => 'array_object',
          'value' => [
            [
              'heading' => [
                'widget' => 'string',
                'value' => 'Card heading1',
              ],
              'content' => [
                'widget' => 'string',
                'value' => 'Card body1',
              ],
              'footer' => [
                'widget' => 'string',
                'value' => 'Card footer1',
              ],
              'tags' => [
                'widget' => 'array_string',
                'value' => [
                  'Card 1 tag1',
                  'Card 1 tag2',
                  'Card 1 tag3',
                ],
              ],
            ],
            [
              'heading' => [
                'widget' => 'string',
                'value' => 'Card heading2',
              ],
              'content' => [
                'widget' => 'string',
                'value' => 'Card body2',
              ],
              'footer' => [
                'widget' => 'string',
                'value' => 'Card footer2',
              ],
              'tags' => [
                'widget' => 'array_string',
                'value' => [
                  'Card 2 tag1',
                  'Card 2 tag2',
                  'Card 2 tag3',
                ],
              ],
            ],
          ],
        ],
      ],
      'slots' => [
        'title' => [
          'source' => 'field',
          'field' => 'title',
        ],
      ],
    ]);
    $display->save();
    $session = $this->assertSession();
    $node = $this->drupalCreateNode([
      'title' => 'Test Node 3',
      'type' => 'article',
    ]);
    $this->drupalGet('/node/' . $node->id());
    // Test slot values.
    $session->elementTextContains('css', '[data-component-id="custom_field_sdc_test:card-list"] h2', $node->getTitle());
    // Test prop values.
    $session->elementAttributeContains('css', '[data-component-id="custom_field_sdc_test:card-list"]', 'class', 'card-list');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card-list"] div.cards article:nth-child(1) header h3', 'Card heading1');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card-list"] div.cards article:nth-child(1) div.content p', 'Card body1');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card-list"] div.cards article:nth-child(1) footer', 'Card footer1');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card-list"] div.cards article:nth-child(1) div.tags', 'Card 1 tag1, Card 1 tag2, Card 1 tag3');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card-list"] div.cards article:nth-child(2) header h3', 'Card heading2');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card-list"] div.cards article:nth-child(2) div.content p', 'Card body2');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card-list"] div.cards article:nth-child(2) footer', 'Card footer2');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card-list"] div.cards article:nth-child(2) div.tags', 'Card 2 tag1, Card 2 tag2, Card 2 tag3');
  }

}
