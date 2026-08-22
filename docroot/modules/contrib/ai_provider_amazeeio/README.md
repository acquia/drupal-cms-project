# amazee.ai Private AI Provider

The **amazee.ai Private AI Provider** module integrates amazee.ai's AI services into Drupal, providing a seamless bridge between the Drupal AI ecosystem and powerful, private, data-sovereign AI capabilities.

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
   - Navigate to `/admin/config/ai/providers/amazeeio`.
   - Type in your email address and click **Sign in**.
   - Receive a code in your email inbox from amazee.ai, enter this code into the verification field, and click **Validate**.
   - Choose a key: **select an existing key** from the table, or **create a new one** by entering a key name (defaults to your site's hostname) and picking a region.
   - You should now be connected. An amazee.ai LLM key (`amazeeio_ai`), VectorDB key (`amazeeio_ai_database`), and management token (`amazeeio_ai_management_token`) will exist in the Keys module: `/admin/config/system/keys`.

For automated / CI setup, use the [amazee.ai AI Provider Recipe](https://www.drupal.org/project/ai_provider_amazeeio_recipe). Note the recipe does **not** create the management token.

## Managing Your Account

[amazee.ai](https://amazee.ai) is the private AI platform from [amazee.io](https://amazee.io). Your AI account, keys, teams, and budgets are managed alongside all other amazee.io products at the shared portal: [https://my.amazee.io](https://my.amazee.io).

## Support and Issues

Please use the [Drupal.org issue queue](https://www.drupal.org/project/issues/ai_provider_amazeeio) for support and bug reports.
