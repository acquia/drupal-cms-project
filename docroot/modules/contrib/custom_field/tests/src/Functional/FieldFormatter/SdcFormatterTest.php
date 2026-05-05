<?php

namespace Drupal\Tests\custom_field\Functional\FieldFormatter;

use Drupal\Core\Entity\Entity\EntityViewDisplay;

/**
 * Tests the 'custom_field_sdc' formatter.
 *
 * @group custom_field
 */
final class SdcFormatterTest extends FormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field_test',
    'custom_field_sdc_test',
    'node',
    'field_ui',
    'sdc_test',
  ];

  /**
   * The component plugin manager.
   *
   * @var \Drupal\Core\Theme\ComponentPluginManager
   */
  protected $componentPluginManager;

  /**
   * The view display to use for testing.
   *
   * @var string
   */
  protected string $viewDisplay = 'node.custom_field_entity_test.default';

  /**
   * The display type to use for testing.
   *
   * @var string
   */
  protected string $displayType = 'custom_field_sdc';

  /**
   * The default component settings.
   *
   * @var array
   */
  protected array $defaultComponentSettings = [
    'component' => '',
    'variant' => '',
    'props' => [],
    'slots' => [],
  ];

  /**
   * The default wrappers.
   *
   * @var array
   */
  protected array $defaultWrappers = [
    'field_wrapper_tag' => 'none',
    'field_wrapper_classes' => '',
    'field_tag' => 'none',
    'field_classes' => '',
    'label_tag' => 'none',
    'label_classes' => '',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->componentPluginManager = $this->container->get('plugin.manager.sdc');
    $display = EntityViewDisplay::load($this->viewDisplay);
    $display->setComponent($this->fieldName, [
      'type' => $this->displayType,
      'label' => 'above',
      'settings' => $this->defaultComponentSettings,
      'weight' => 1,
      'region' => 'content',
    ])->save();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests my-banner component from core sdc_test module.
   */
  public function testMyBanner(): void {
    $display = EntityViewDisplay::load($this->viewDisplay);
    $component = $display->getComponent($this->fieldName);
    $component['settings']['component'] = 'sdc_test:my-banner';
    $component['settings']['props'] = [
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
    ];
    $component['settings']['slots'] = [
      'banner_body' => [
        'source' => 'field',
        'field' => 'string',
        'format_type' => 'string',
        'formatter_settings' => [
          'key_label' => 'label',
          'label_display' => 'hidden',
          'field_label' => '',
          'prefix_suffix' => FALSE,
        ],
        'wrappers' => $this->defaultWrappers,
      ],
    ];
    $display->setComponent($this->fieldName, $component)->save();
    $session = $this->assertSession();
    $node1 = $this->drupalCreateNode([
      'title' => 'Test Node',
      'type' => 'custom_field_entity_test',
      $this->fieldName => [
        'string' => 'Test 1',
      ],
    ]);
    $this->drupalGet('/node/' . $node1->id());
    // Test prop value.
    $session->elementTextEquals('css', '[data-component-id="sdc_test:my-banner"] h3', 'Join us at The Conference');
    // Test slot value.
    $session->elementTextEquals('css', '[data-component-id="sdc_test:my-banner"] div.component--my-banner--body', 'Test 1');
  }

  /**
   * Tests a card component from the custom_field_test_sdc module.
   */
  public function testCard(): void {
    $display = EntityViewDisplay::load($this->viewDisplay);
    $component = $display->getComponent($this->fieldName);
    $component['settings']['component'] = 'custom_field_sdc_test:card';
    $component['settings']['props'] = [
      'attributes' => [
        'widget' => 'attributes',
        'value' => [
          'class' => 'card',
        ],
      ],
      'heading' => [
        'widget' => 'string',
        'value' => 'Card heading',
      ],
      'content' => [
        'widget' => 'string',
        'value' => 'Card body',
      ],
      'footer' => [
        'widget' => 'string',
        'value' => 'Card footer',
      ],
      'tags' => [
        'widget' => 'array_string',
        'value' => [
          'tag1',
          'tag2',
          'tag3',
        ],
      ],
    ];
    $component['settings']['slots'] = [
      'subheading' => [
        'source' => 'field',
        'field' => 'string',
        'format_type' => 'string',
        'formatter_settings' => [
          'key_label' => 'label',
          'label_display' => 'hidden',
          'field_label' => '',
          'prefix_suffix' => FALSE,
        ],
        'wrappers' => $this->defaultWrappers,
      ],
    ];
    $display->setComponent($this->fieldName, $component)->save();
    $session = $this->assertSession();
    $node2 = $this->drupalCreateNode([
      'title' => 'Test Node',
      'type' => 'custom_field_entity_test',
      $this->fieldName => [
        'string' => 'Subheading text',
      ],
    ]);
    $this->drupalGet('/node/' . $node2->id());
    // Test prop values.
    $session->elementAttributeContains('css', '[data-component-id="custom_field_sdc_test:card"]', 'class', 'card');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card"] header h2', 'Card heading');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card"] div.content p', 'Card body');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card"] footer', 'Card footer');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card"] div.tags', 'tag1, tag2, tag3');
    // Test slot value.
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card"] div.subheading', 'Subheading text');
  }

  /**
   * Tests a card-list component from the custom_field_test_sdc module.
   */
  public function testCardList(): void {
    $display = EntityViewDisplay::load($this->viewDisplay);
    $component = $display->getComponent($this->fieldName);
    $component['settings']['component'] = 'custom_field_sdc_test:card-list';
    $component['settings']['props'] = [
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
    ];
    $component['settings']['slots'] = [
      'title' => [
        'source' => 'field',
        'field' => 'string',
        'format_type' => 'string',
        'formatter_settings' => [
          'key_label' => 'label',
          'label_display' => 'hidden',
          'field_label' => '',
          'prefix_suffix' => FALSE,
        ],
        'wrappers' => $this->defaultWrappers,
      ],
    ];
    $display->setComponent($this->fieldName, $component)->save();
    $session = $this->assertSession();
    $node = $this->drupalCreateNode([
      'title' => 'Test Node 3',
      'type' => 'custom_field_entity_test',
      $this->fieldName => [
        'string' => 'Cards',
      ],
    ]);
    $this->drupalGet('/node/' . $node->id());
    // Test slot values.
    $session->elementTextContains('css', '[data-component-id="custom_field_sdc_test:card-list"] h2', 'Cards');
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
