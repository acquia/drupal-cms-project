# Canvas Integration

When [Drupal Canvas](https://www.drupal.org/project/canvas) is installed alongside Config Language Lock, additional protections are enforced to ensure all configuration and Canvas pages remain in the site default language.

## Protections

### 1. Configuration language forced to site default

When Canvas is installed, the Config Language Lock settings form forces the user to only be able to lock to the site default language.

The form remains submittable to allow:
- Initial setup when Config Language Lock is first installed
- Repairing a mismatch if the lock was set to a different language before Canvas was installed or there is config in incorrect languages

### 2. Site default language change blocked

When Canvas pages exist on the site, the site default language cannot be changed on the language administration overview page (`/admin/config/regional/language`). This prevents creating a situation where existing Canvas pages are in a different language than the site default.

The site default language select list in the **Default language** fieldset below the language table is disabled, and the fieldset explains why and what to do about it: delete the Canvas pages, and pick a different front page first if the front page is a Canvas page. See [Language management](language-management.md#site-default-language-selector) for how this module reworks that fieldset.

To change the site default language, remove all Canvas pages first.

### 3. Runtime status warning

If Canvas is installed but the configuration language lock is not set to the site default (or is not configured at all), a runtime requirements error is shown on the Status Report page. The error links to the Config Language Lock settings page where the user can reconcile the configuration.
