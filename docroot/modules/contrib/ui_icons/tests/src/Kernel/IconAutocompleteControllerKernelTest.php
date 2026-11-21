<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons\Controller\IconAutocompleteController;
use Drupal\ui_icons\IconSearch;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the IconAutocompleteController class.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconAutocompleteController::class)]
#[Group('ui_icons')]
class IconAutocompleteControllerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_test',
  ];

  /**
   * The IconAutocompleteController instance.
   *
   * @var \Drupal\ui_icons\Controller\IconAutocompleteController
   */
  private IconAutocompleteController $iconAutocompleteController;

  /**
   * The App root instance.
   *
   * @var string
   */
  private string $appRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $iconSearch = $this->container->get('ui_icons.search');

    $this->iconAutocompleteController = new IconAutocompleteController(
      $iconSearch,
    );
  }

  /**
   * Tests the handleSearchIcons method of the IconAutocompleteController.
   */
  public function testHandleSearchIconsResultList(): void {
    $icon_full_id = 'test_minimal:foo';
    $search = $this->iconAutocompleteController->handleSearchIcons(new Request(['q' => $icon_full_id]));
    $result = json_decode($search->getContent(), TRUE);

    // Load the response to test, cannot simply compare string as `src` path is
    // based on physical path than can be specific for example in CI.
    $result_dom = new \DOMDocument();
    $result_dom->loadHTML($result[0]['label']);
    $this->assertSame('Footest_minimal', trim($result_dom->lastChild->textContent));

    $result_xpath = new \DOMXpath($result_dom);

    $div = $result_xpath->query('//div');
    $this->assertSame('ui-icons-result', $div->item(0)->getAttribute('class'));
    $span = $result_xpath->query('//div/span');
    $this->assertSame('ui-icons-result-icon-name', $span->item(0)->getAttribute('class'));

    $img = $result_xpath->query('//div/img');
    $this->assertSame('icon icon-preview', $img->item(0)->getAttribute('class'));
    $this->assertSame($icon_full_id, $img->item(0)->getAttribute('title'));
    $this->assertSame(IconSearch::ICON_PREVIEW_SIZE, (int) $img->item(0)->getAttribute('width'));
    $this->assertSame(IconSearch::ICON_PREVIEW_SIZE, (int) $img->item(0)->getAttribute('height'));

    $src = $img->item(0)->getAttribute('src');
    $this->assertStringEndsWith('tests/modules/ui_icons_test/icons/flat/foo.png', $src);
  }

  /**
   * Tests the handleSearchIcons method of the IconAutocompleteController.
   */
  public function testHandleSearchIconsResultGrid(): void {
    $icon_full_id = 'test_minimal:foo';
    $req = [
      'q' => $icon_full_id,
      'result_format' => 'grid',
    ];
    $search = $this->iconAutocompleteController->handleSearchIcons(new Request($req));
    $result = json_decode($search->getContent(), TRUE);

    // Load the response to test, cannot simply compare string as `src` path is
    // based on physical path than can be specific for example in CI.
    $result_dom = new \DOMDocument();
    $result_dom->loadHTML($result[0]['label']);
    $this->assertSame('Foo (test_minimal)', trim($result_dom->lastChild->textContent));

    $result_xpath = new \DOMXpath($result_dom);

    $span = $result_xpath->query('//span');
    $this->assertSame('ui-icons-result-grid', $span->item(0)->getAttribute('class'));
    $spanName = $result_xpath->query('//span/span');
    $this->assertSame('ui-icons-result-icon-name', $spanName->item(0)->getAttribute('class'));

    $img = $result_xpath->query('//span/img');
    $this->assertSame('icon icon-preview', $img->item(0)->getAttribute('class'));
    $this->assertSame($icon_full_id, $img->item(0)->getAttribute('title'));
    $this->assertSame(IconSearch::ICON_PREVIEW_SIZE, (int) $img->item(0)->getAttribute('width'));
    $this->assertSame(IconSearch::ICON_PREVIEW_SIZE, (int) $img->item(0)->getAttribute('height'));

    $src = $img->item(0)->getAttribute('src');
    $this->assertStringEndsWith('tests/modules/ui_icons_test/icons/flat/foo.png', $src);
  }

}
