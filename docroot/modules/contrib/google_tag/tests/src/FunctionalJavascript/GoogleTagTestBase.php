<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Base class for Google Tag FunctionalJavascript tests.
 *
 * Only behavior that needs a browser belongs in these tests: what gtag.js
 * pushes to the dataLayer, and Ajax responses. Page output is tested with
 * kernel or Functional tests.
 */
abstract class GoogleTagTestBase extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Asserts that the gtag() function exists on the page.
   */
  protected function assertGoogleTagExists(): void {
    $this->assertTrue($this->getSession()->evaluateScript('typeof gtag !== "undefined"'), 'gtag() exists on the page.');
  }

  /**
   * Asserts that the dataLayer contains an entry.
   *
   * @param array $expected
   *   The expected dataLayer entry, such as ['config', 'G-XXXXXX'].
   */
  protected function assertDataLayerContains(array $expected): void {
    $data_layer = $this->getSession()->evaluateScript('window.dataLayer || []');
    $data_layer = array_map([self::class, 'normalize'], is_array($data_layer) ? $data_layer : []);
    $this->assertContains(self::normalize($expected), $data_layer, 'dataLayer contains ' . json_encode($expected));
  }

  /**
   * Asserts that gtag() pushed Arguments objects, not arrays.
   *
   * Google's gtag.js only runs dataLayer entries that are Arguments objects
   * and silently ignores arrays with the same values, so gtag() must keep
   * pushing `arguments`. WebDriver returns both as arrays, so the check has
   * to run in the browser.
   */
  protected function assertGtagPushesArguments(): void {
    $result = $this->getSession()->evaluateScript(<<<'JS'
      (function () {
        var entries = window.dataLayer || [];
        var isArguments = function (entry) {
          return Object.prototype.toString.call(entry) === '[object Arguments]';
        };
        return {
          commands: entries.filter(isArguments).length,
          arrays: entries.filter(Array.isArray).length,
        };
      })()
      JS);
    $this->assertGreaterThan(0, $result['commands'], 'gtag() pushed Arguments objects to dataLayer.');
    $this->assertSame(0, $result['arrays'], 'No arrays were pushed to dataLayer.');
  }

  /**
   * Sorts the keys of associative arrays, recursively.
   *
   * WebDriver does not guarantee the order of object keys, so comparisons are
   * made on key-sorted values.
   *
   * @param mixed $value
   *   The value to normalize.
   *
   * @return mixed
   *   The normalized value.
   */
  protected static function normalize(mixed $value): mixed {
    if (!is_array($value)) {
      return $value;
    }
    $value = array_map([self::class, 'normalize'], $value);
    if (!array_is_list($value)) {
      ksort($value);
    }
    return $value;
  }

}
