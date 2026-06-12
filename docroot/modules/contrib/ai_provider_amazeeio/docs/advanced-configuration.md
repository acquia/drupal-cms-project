# Advanced Configuration

By default the amazee.ai provider stores both credentials — the LLM API key and the VectorDB password — inside Drupal's configuration system via the [Key module](https://www.drupal.org/project/key) using the **Configuration** key provider. This is the simplest setup and works out of the box.

However, storing secrets inside configuration is not always appropriate — especially when config is exported to version control (e.g. via `config/sync`). This page documents how to use alternative key providers so that secrets never leave a secure storage backend.

---

## The Two Managed Keys

| Key ID | Label | What it stores |
|---|---|---|
| `amazeeio_ai` | amazee.ai AI API Key | The LiteLLM bearer token used to call the LLM gateway |
| `amazeeio_ai_database` | amazee.ai AI Database Key | The password for the managed pgvector VectorDB |

Both keys are created automatically when you connect the module. The module reads the key IDs from `ai_provider_amazeeio.settings`:

```yaml
api_key: amazeeio_ai
postgres_password: amazeeio_ai_database
```

You can point these settings at **any** Key module entity, regardless of which key provider backend that entity uses.

---

## Changing the Key Provider

### Option 1: Environment Variable (recommended for Lagoon / Docker)

1. Install the [Key module](https://www.drupal.org/project/key) and the [Environment Key provider](https://www.drupal.org/project/key) (or a compatible sub-module such as `key_env`).
2. Set environment variables on your host:
   ```bash
   # LLM API key
   AMAZEEIO_AI_KEY=sk-...
   # VectorDB password
   AMAZEEIO_AI_DB_PASSWORD=supersecret
   ```
3. Update the Key entities (e.g. via `config/sync` or Drush) to use the **Environment variable** provider and point at the variable names above.
4. The module will pick up the new values automatically on the next request.

> **Lagoon tip:** Add the variables via the Lagoon API or the Lagoon UI as **project-level** or **environment-level** variables with `scope: runtime` so they are injected at container boot time.

### Option 2: AWS Secrets Manager / HashiCorp Vault

Use a community Key provider module (e.g. [key_aws_kms](https://www.drupal.org/project/key_aws_kms), [vault_key](https://www.drupal.org/project/vault_key)) and configure it to fetch the secret by ARN or path. Update the Key entity's **key provider** to point at the relevant secret — the module does not need to be reconfigured.

### Option 3: Drush / Manually Overriding Key Values

If you want to inject the key at deploy time without changing the Key entity's provider:

```bash
drush php-eval "
  \$key = \Drupal::entityTypeManager()->getStorage('key')->load('amazeeio_ai');
  \$key->setKeyValue(getenv('AMAZEEIO_AI_KEY'));
  \$key->save();
"
```

> **Warning:** This writes the secret value into the database. Prefer a proper key provider backend for production environments.

---

## Overriding Configuration with `settings.php`

All module settings can be overridden in `settings.php` without touching the database. This is useful for setting host URLs or key IDs per-environment:

```php
// settings.php (or settings.local.php)
$config['ai_provider_amazeeio.settings']['host'] = 'https://api.amazee.ai';
$config['ai_provider_amazeeio.settings']['postgres_host'] = 'postgres.example.com';
$config['ai_provider_amazeeio.settings']['postgres_port'] = 5432;
$config['ai_provider_amazeeio.settings']['postgres_default_database'] = 'my_vdb';
$config['ai_provider_amazeeio.settings']['postgres_username'] = 'my_user';

// Override which Key entity to use (must exist):
$config['ai_provider_amazeeio.settings']['api_key'] = 'my_custom_llm_key';
$config['ai_provider_amazeeio.settings']['postgres_password'] = 'my_custom_db_key';
```

> **Note:** Config overrides via `settings.php` are **read-only** — they are not written back to the database and will not appear in config exports.

---

## Pre-provisioning Keys Without Using the UI

For automated deployments (CI/CD, Ansible, etc.) you may want to pre-create the Key entities and module config before any admin visits the settings page. The snippet below shows how to do this via Drush `php-eval` or a custom deploy hook:

```php
use Drupal\Core\Config\Config;

// Write module settings.
\Drupal::configFactory()
  ->getEditable('ai_provider_amazeeio.settings')
  ->set('host', 'https://<your-region>.api.amazee.ai')
  ->set('postgres_host', '<pgvector-host>')
  ->set('postgres_port', 5432)
  ->set('postgres_default_database', '<db-name>')
  ->set('postgres_username', '<db-user>')
  ->set('postgres_password', 'amazeeio_ai_database')
  ->set('api_key', 'amazeeio_ai')
  ->save();

// Create / update the LLM API key entity.
$key_storage = \Drupal::entityTypeManager()->getStorage('key');
$key = $key_storage->load('amazeeio_ai')
  ?? $key_storage->create([
    'id' => 'amazeeio_ai',
    'label' => 'amazee.ai AI API Key',
    'key_type' => 'authentication',
    'key_provider' => 'config',
    'key_input' => 'text_field',
  ]);
$key->set('key_provider_settings', ['key_value' => '<LiteLLM token>']);
$key->save();

// Create / update the VectorDB password key entity.
$db_key = $key_storage->load('amazeeio_ai_database')
  ?? $key_storage->create([
    'id' => 'amazeeio_ai_database',
    'label' => 'amazee.ai AI Database Key',
    'key_type' => 'authentication',
    'key_provider' => 'config',
    'key_input' => 'text_field',
  ]);
$db_key->set('key_provider_settings', ['key_value' => '<VectorDB password>']);
$db_key->save();
```

Once both keys exist and the config is saved, the provider will report as **Connected** without requiring the sign-in flow.

---

## Manual Migration / Sync Configuration

If you have received credentials manually (e.g. for a dedicated environment) and want to manually update your `config/sync` files, you can adapt the following YAML structures.

### `config/sync/ai_provider_amazeeio.settings.yml`

There are 4 main places to change the connection details in this file:

```yaml
api_key: amazeeio_ai
moderation: false
# The base amazee.ai API host (typically https://api.amazee.ai)
amazee_host: 'https://api.amazee.ai'
# The specific LLM gateway URL for your environment
host: 'LLM_HOST_GOES_HERE'
# The PostgreSQL server address for the VectorDB
postgres_host: DB_HOST_GOES_HERE
postgres_port: 5432
# The database name for the VectorDB
postgres_default_database: DB_NAME_GOES_HERE
# The database user for the VectorDB
postgres_username: DB_USERNAME_GOES_HERE
postgres_password: amazeeio_ai_database
```

### `config/sync/key.key.amazeeio_ai.yml`

This file handles the LLM API key (the LiteLLM bearer token). You only need to change the `key_value` part:

```yaml
langcode: en
status: true
dependencies: {  }
id: amazeeio_ai
label: 'amazee.ai AI API Key'
description: 'Automatically created by the amazee.ai AI provider.'
key_type: authentication
key_type_settings: {  }
key_provider: config
key_provider_settings:
  key_value: LLM_KEY_GOES_HERE
key_input: text_field
key_input_settings: {  }
```

### `config/sync/key.key.amazeeio_ai_database.yml`

This file handles the VectorDB password. You only need to change the `key_value` part:

```yaml
langcode: en
status: true
dependencies: {  }
id: amazeeio_ai_database
label: 'amazee.ai AI Database Key'
description: 'Automatically created by the amazee.ai AI provider.'
key_type: authentication
key_type_settings: {  }
key_provider: config
key_provider_settings:
  key_value: DATABASE_KEY_GOES_HERE
key_input: text_field
key_input_settings: {  }
```

> **Reminder:** After making manual changes to `config/sync`, run `drush cim -y` to import the new configuration into your database.
