<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\FunctionalJavascript;

use Drupal\Tests\google_tag\Traits\CommerceStoreTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the view_item event sent by the Commerce variation Ajax change.
 *
 * The events sent on full page loads are covered by CommerceEventsTest.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class CommerceEventsFunctionalJavascriptTest extends GoogleTagTestBase {

  use CommerceStoreTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'google_tag',
    'commerce_product',
    'commerce_cart',
  ];

  /**
   * Tests that selecting another variation sends view_item for it.
   */
  public function testVariationChange(): void {
    $this->createCommerceFixtures();
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('view commerce_product')->save();

    $this->drupalGet('product/1');
    $this->assertDataLayerContains([
      'event',
      'view_item',
      [
        'currency' => 'USD',
        'items' => [
          [
            'affiliation' => 'FooBar Store',
            'item_id' => 'ABC123',
            'item_name' => 'FooBaz Widget - Blue',
          ],
        ],
        'value' => '12.00',
      ],
    ]);

    $this->getSession()->getPage()->selectFieldOption('purchased_entity[0][attributes][attribute_color]', 'Red');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDataLayerContains([
      'event',
      'view_item',
      [
        'currency' => 'USD',
        'items' => [
          [
            'affiliation' => 'FooBar Store',
            'item_id' => 'DEF456',
            'item_name' => 'FooBaz Widget - Red',
          ],
        ],
        'value' => '12.00',
      ],
    ]);
    $this->assertGtagPushesArguments();
  }

}
