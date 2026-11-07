<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Functional\FieldFormatter;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Theme\ComponentPluginManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the 'custom_field_sdc' formatter.
 *
 * @group custom_field
 * @runTestsInSeparateProcesses
 */
#[Group('custom_field')]
#[RunTestsInSeparateProcesses]
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
  protected ComponentPluginManager $componentPluginManager;

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
   * @var array<string, mixed>
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
   * @var array<string, string>
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
   * Tests that no output is rendered when no component is configured.
   */
  public function testNoOutputWhenComponentNotConfigured(): void {
    $node = $this->drupalCreateNode([
      'title' => 'Test Node',
      'type' => 'custom_field_entity_test',
      $this->fieldName => ['string' => 'Test'],
    ]);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->elementNotExists('css', '[data-component-id]');
  }

  /**
   * Tests my-banner component from core sdc_test module.
   */
  public function testMyBanner(): void {
    $this->setupComponentAndNavigate(
      'sdc_test:my-banner',
      [
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
        ],
      ],
      [
        'title' => 'Test Node',
        $this->fieldName => ['string' => 'Test 1'],
      ],
    );

    $session = $this->assertSession();
    $session->elementExists('css', '[data-component-id="sdc_test:my-banner"]');
    $session->elementTextEquals('css', '[data-component-id="sdc_test:my-banner"] h3', 'Join us at The Conference');
    $session->elementTextEquals('css', '[data-component-id="sdc_test:my-banner"] div.component--my-banner--body', 'Test 1');
  }

  /**
   * Tests a card component from the custom_field_sdc_test module.
   */
  public function testCard(): void {
    $this->setupComponentAndNavigate(
      'custom_field_sdc_test:card',
      [
        'props' => [
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
        ],
        'slots' => [
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
        ],
      ],
      [
        'title' => 'Test Node',
        $this->fieldName => ['string' => 'Subheading text'],
      ],
    );

    $session = $this->assertSession();
    $session->elementExists('css', '[data-component-id="custom_field_sdc_test:card"]');
    $session->elementAttributeContains('css', '[data-component-id="custom_field_sdc_test:card"]', 'class', 'card');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card"] header h2', 'Card heading');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card"] div.content p', 'Card body');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card"] footer', 'Card footer');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card"] div.tags', 'tag1, tag2, tag3');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card"] div.subheading', 'Subheading text');
  }

  /**
   * Tests a card-list component from the custom_field_sdc_test module.
   */
  public function testCardList(): void {
    $this->setupComponentAndNavigate(
      'custom_field_sdc_test:card-list',
      [
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
        ],
      ],
      [
        'title' => 'Test Node 3',
        $this->fieldName => ['string' => 'Cards'],
      ],
    );

    $session = $this->assertSession();
    $session->elementExists('css', '[data-component-id="custom_field_sdc_test:card-list"]');
    $session->elementTextEquals('css', '[data-component-id="custom_field_sdc_test:card-list"] h2', 'Cards');
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

  /**
   * Configures the field formatter component and navigates to a test node.
   *
   * Verifies that the requested component exists before applying settings,
   * failing fast if the component is missing rather than producing a
   * misleading assertion failure later.
   *
   * @param string $componentId
   *   The component ID to configure (e.g. 'sdc_test:my-banner').
   * @param array<string, mixed> $settings
   *   The component settings to merge into the formatter configuration.
   * @param array<string, mixed> $fieldValues
   *   The field values for the test node.
   */
  protected function setupComponentAndNavigate(string $componentId, array $settings, array $fieldValues): void {
    $this->assertNotNull(
      $this->componentPluginManager->find($componentId),
      sprintf('Component %s must exist before testing.', $componentId),
    );
    $display = EntityViewDisplay::load($this->viewDisplay);
    $component = $display->getComponent($this->fieldName);
    $component['settings'] = array_merge(
      $component['settings'],
      ['component' => $componentId],
      $settings,
    );
    $display->setComponent($this->fieldName, $component)->save();
    $node = $this->drupalCreateNode([
      'type' => 'custom_field_entity_test',
      ...$fieldValues,
    ]);
    $this->drupalGet('/node/' . $node->id());
  }

}
