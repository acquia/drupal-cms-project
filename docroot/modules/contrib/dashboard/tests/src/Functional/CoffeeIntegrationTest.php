<?php

declare(strict_types=1);

namespace Drupal\Tests\Dashboard\Functional;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\BrowserTestBase;
use Drupal\dashboard\Entity\Dashboard;

/**
 * Test for dashboard coffee module integration.
 */
#[Group('dashboard')]
class CoffeeIntegrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['dashboard', 'layout_builder', 'coffee'];

  /**
   * A user with permission to administer dashboards.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Dashboard::create([
      'id' => 'some_id',
      'label' => 'The first',
      'status' => TRUE,
      'weight' => 0,
    ])->save();

    Dashboard::create([
      'id' => 'another_id',
      'label' => 'The second',
      'status' => TRUE,
      'weight' => 10,
    ])->save();

    Dashboard::create([
      'id' => 'unauthorized',
      'label' => 'The forbidden steps',
      'status' => TRUE,
      'weight' => 20,
    ])->save();

    $this->adminUser = $this->drupalCreateUser([
      'administer dashboard',
      'access coffee',
      'configure any layout',
      'view some_id dashboard',
      'view another_id dashboard',
    ]);
  }

  /**
   * Tests the block text addition.
   */
  public function testCoffee(): void {
    $parsed_url = parse_url($this->baseUrl);
    $base_path = isset($parsed_url['path']) ? rtrim(rtrim($parsed_url['path']), '/') : '';

    $this->drupalLogin($this->adminUser);
    $this->setupBaseUrl();
    $content = $this->drupalGet('/admin/coffee/get-data');
    $result = json_decode($content, TRUE);
    $this->assertSame([
      [
        'value' => $base_path . '/',
        'label' => 'Go to front page',
        'command' => ':front',
      ],
      [
        'value' => $base_path . '/admin/dashboard/another_id',
        'label' => 'The second',
        'command' => ':dashboard another_id',
      ],
      [
        'value' => $base_path . '/admin/dashboard/some_id',
        'label' => 'The first',
        'command' => ':dashboard some_id',
      ],
    ], $result);
  }

}
