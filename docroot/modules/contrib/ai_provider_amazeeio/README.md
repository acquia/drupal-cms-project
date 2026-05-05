# amazee.ai AI Provider

The **amazee.ai AI Provider** module integrates amazee.ai's AI services into Drupal, providing a seamless bridge between the Drupal AI ecosystem and powerful, data-sovereign AI capabilities.

For more detailed information, features, and documentation, please visit the official project page:
[https://www.drupal.org/project/ai_provider_amazeeio](https://www.drupal.org/project/ai_provider_amazeeio)

## Installation

1. **Using Composer**:
   ```bash
   composer require drupal/ai_provider_amazeeio
   ```

2. **Enable the Module**:
   ```bash
   drush en ai_provider_amazeeio
   ```

3. **Configure the Provider**:
   - Navigate to `/admin/config/ai/settings`.
   - Select "amazee.io" as your AI provider.
   - Type in your email address.
   - Receive a code in your email inbox from amazee, enter this code into the verification field, and submit.
   - You should now be authenticated with the provider module, an amazee.ai LLM key and amazee.ai VectorDB key should exist in the Keys module: `/admin/config/system/keys`

## Support and Issues

Please use the [Drupal.org issue queue](https://www.drupal.org/project/issues/ai_provider_amazeeio) for support and bug reports.
