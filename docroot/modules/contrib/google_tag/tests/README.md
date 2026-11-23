# Google Tag module tests

All tests are PHPUnit tests. Earlier releases also shipped Nightwatch tests;
they have been replaced by the Functional and FunctionalJavascript tests below.

Pick the cheapest test type that can see the behavior:

- **Kernel** (`tests/src/Kernel`): event payloads, `drupalSettings.gtag`,
  conditions, forms, upgrades. Most coverage lives here.
- **Functional** (`tests/src/Functional`): flows across real requests, such as
  form submissions, redirects and delayed events.
- **FunctionalJavascript** (`tests/src/FunctionalJavascript`): only what needs
  a browser: what `js/gtag.js` pushes to the dataLayer, and Ajax responses.

## Running the tests

From the project root, with the module in `modules/contrib/google_tag`:

```bash
# All Google Tag tests.
vendor/bin/phpunit -c core/phpunit.xml.dist modules/contrib/google_tag/tests

# One test class.
vendor/bin/phpunit -c core/phpunit.xml.dist modules/contrib/google_tag/tests/src/Functional/TagContainerUiTest.php
```

FunctionalJavascript tests need a WebDriver server such as Selenium with
Chrome, configured through `MINK_DRIVER_ARGS_WEBDRIVER`. See
`core/tests/README.md` for the setup. The Commerce tests need Drupal Commerce,
which is in the module's `require-dev`.

## Functional tests

- `TagContainerUiTest`: saving tag IDs and a user role condition through the
  container form, checked in the page's `drupalSettings`.
- `CommerceEventsTest`: `view_item`, `add_to_cart`, `begin_checkout` and
  `purchase` reach the right pages during a guest checkout.

## FunctionalJavascript tests

- `GoogleTagTestBase`: dataLayer assertions, including
  `assertGtagPushesArguments()`. Google's gtag.js ignores dataLayer entries
  that are arrays, so `gtag()` must push `arguments`; WebDriver returns both
  as arrays, so this is checked in the browser.
- `IntegrationFunctionalJavascriptTest`: the config, set and event commands
  pushed for tag IDs, events, and custom dimensions and metrics.
- `UserInterfaceFunctionalJavascriptTest`: the Ajax "Add another ID" button.
- `CommerceEventsFunctionalJavascriptTest`: `view_item` sent by the
  Commerce variation Ajax change.
