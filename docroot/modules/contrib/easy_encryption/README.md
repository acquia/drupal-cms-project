# Easy Encryption

## Overview

Easy Encryption provides a zero-configuration solution for securing sensitive data and credentials at rest in Drupal. Born from discussions in the [META issue to improve security of AI and VDB provider credential storage](https://www.drupal.org/i/3559052) and created specifically to address [insecure credential storage concerns in Drupal CMS](https://www.drupal.org/i/3560518), this module aims to make encryption accessible to all Drupal users without requiring cryptography expertise.

The module integrates seamlessly with the [Key module](https://www.drupal.org/project/key) to automatically encrypt sensitive values. By default it uses libsodium sealed box encryption, but the module’s public APIs are designed around encryption keys and key IDs (rather than a specific cryptography backend), so implementations can be swapped.

## Why this module exists

Drupal CMS and many contributed modules handle sensitive credentials such as API keys, database passwords, and authentication tokens. Historically, these credentials were often stored in plain text in configuration or the database, creating security risks if configuration files were accidentally exposed or databases were compromised.

Easy Encryption was developed to solve this problem by:

- Providing encryption that works out of the box with zero configuration
- Using modern, audited cryptography (libsodium by default)
- Integrating with Drupal's existing Key module ecosystem
- Supporting secure workflows for teams and multi-environment deployments
- Making encrypted configuration safely exportable and version-controllable

## Features

- **Zero-configuration setup**: Install and start encrypting immediately
- **Encryption keys with stable IDs**: Encrypted values carry an encryption key ID so the right key can be used for decryption
- **Exportable configuration**: Encrypted values can be safely committed to version control
- **Multi-environment support**: Encrypt in development or CI, decrypt in production (where the private key is available)
- **Key rotation**: Built-in support for rotating encryption keys via CLI
- **Transparent credential security upgrades**: Automatically upgrades insecure keys (Config/State) to the Easy Encrypted provider upon creation. This works seamlessly for manual entry, Recipes, and automation, ensuring sensitive credentials never touch your storage in plaintext.
- **Uninstall protection**: Prevents accidental uninstallation while encrypted keys exist
- **Pluggable cryptography backend**: Ships with a libsodium sealed box encryptor by default, but can be swapped via the provided APIs
- **Provider upgrade safety**: New keys created with insecure providers (such as `config` and `state`) are automatically upgraded to Easy Encrypted before being saved, which prevents plaintext credential storage and avoids data loss for recipe and automation-created keys
- **Admin UI (optional)**: Install the Easy Encryption Admin module to manage encryption key import and export in the UI (key transfer)

## How it works

Easy Encryption’s primary abstractions are an encryption key and an encryption key ID. By default, encryption keys are backed by a libsodium sealed box key pair (public and private key), but most users only need to understand when a public key is required (encrypt) and when a private key is required (decrypt).

### Initial setup

When you install the module, it automatically generates an encryption key and assigns it an encryption key ID.

In the default implementation, that encryption key is stored as a key pair consisting of:

- **Public key**: Used for encryption. Safe to export and commit to your repository.
- **Private key**: Used for decryption. Must be kept secure and never committed to version control.

### Encrypting credentials

When you create a Key entity using the "Easy Encrypted" provider:

1. Your sensitive value is encrypted
2. The encrypted ciphertext is stored in configuration
3. A reference to the encryption key ID is stored alongside the ciphertext
4. The configuration can be safely exported and committed to your repository

### Decrypting credentials

When Drupal needs to use an encrypted credential:

1. The module loads the appropriate encryption key using the stored encryption key ID
2. The private key decrypts the ciphertext (in the default implementation)
3. The plaintext credential is returned to the calling code
4. The private key is immediately cleared from memory

### Key storage locations

By default, the private key is stored in a directory called `.easy_encryption` next to your web root, with restricted file permissions (0600). The key is wrapped in PHP code so that even if accidentally exposed via the web, it produces no output.

If file storage fails, the private key falls back to Drupal's state system (database storage). This is less secure than file-based storage but still not publicly accessible.

**Important**: Do NOT commit the `.easy_encryption` directory or its contents to version control.

### Recommended security practices

For production environments, consider moving the private key to more secure storage:

1. **Environment variables** (better): Store the private key in an environment variable using the Key module's Environment provider
2. **External key management** (best): Use a dedicated secrets manager like HashiCorp Vault, AWS Secrets Manager, or Azure Key Vault

While these approaches require additional configuration, Easy Encryption gives you meaningful security improvement with zero configuration, then allows you to enhance security as your needs grow.

## Usage

### Creating an encrypted key

1. Navigate to **Configuration > System > Keys** (`/admin/config/system/keys`)
2. Click **Add key**
3. Fill in the key label and description
4. For **Key provider**, select **Easy Encrypted**
5. Enter your sensitive value (API key, password, etc.)
6. Save the key... When you create a Key entity using the "Easy Encrypted" provider (the module sets this as the default option), your sensitive value is encrypted and stored as ciphertext in configuration alongside the encryption key identifier.

### Using encrypted keys in code

Encrypted keys work exactly like any other Key entity:

```php
$key = \Drupal::service('key.repository')->getKey('my_api_key');
$api_key_value = $key->getKeyValue();

// Use $api_key_value to authenticate with an external API.
```

### Checking key status

Visit **Reports > Status report** to verify:

- An active encryption key is configured
- The private key is available (for environments that need decryption)

### Rotating keys

If you need to rotate your encryption keys (for compliance or security):

```shell
  drush easy-encryption:rotate
```

This generates a new encryption key and marks it as active. Existing encrypted values remain decryptable with their original keys unless you use the `--reencrypt` option.

### Admin UI (Easy Encryption Admin)

Easy Encryption also ships with an optional admin module: **Easy Encryption Admin**. It adds UI screens for importing and exporting Easy Encryption encryption keys, so key transfer is not Drush-only anymore.

The admin UI is currently focused on key transfer. For planned admin features, see [issues tagged with the `easy_encryption_admin` component](https://www.drupal.org/project/issues/3562833?component=easy_encryption_admin).

## Configuration

### Private key directory

To change where the private key is stored, add this to your `settings.php`:

```
$settings['easy_encryption']['private_key_directory'] = '/secure/path/outside/webroot';
```

Make sure this directory:
- Exists and is writable by the web server
- Is outside the web root
- Is excluded from version control
- Has appropriate filesystem permissions

### Transparent credential security upgrades

Easy Encryption provides a transparent "security-by-default" path. To prevent accidental exposure of sensitive data, the module automatically upgrades **new** Key entities targeting insecure providers (such as "Configuration" and "State") to the **Easy Encrypted** provider before they are saved.

**Why this is a key benefit:**
- **Recipe & automation compatibility**: You can use existing recipes or config actions (e.g., `setupAiProvider`) without modification. Easy Encryption transparently intercepts the key creation, encrypts the value, and stores it securely—even if the recipe was originally written to use plaintext configuration.
- **Human-error prevention**: If a site builder manually selects an insecure provider, Easy Encryption provides a helpful UI message explaining that the key is being automatically secured for them.
- **No "Plaintext Leakage"**: Because this happens before the entity is written to storage, your credentials are never written to the database or exported to YAML in plaintext, not even for a second.

You can configure which providers are upgraded via `settings.php`:

```php
$settings['easy_encryption']['upgraded_key_providers'] = ['config', 'state'];
```

To disable automatic upgrades entirely:

```php
$settings['easy_encryption']['upgraded_key_providers'] = [];
```

### Transferring encryption keys between environments or sites

Encrypted Key entities store ciphertext and an encryption key ID. To decrypt those values in another environment (or on another site), you must also transfer the matching Easy Encryption encryption key material for that encryption key ID.

In the default setup, encryption keys are represented by Key config entities (public and private key Key entities) plus private key material stored outside config. In other words, transferring the private key file or State alone is not enough. You also need the corresponding Key config entities that identify the encryption key and link it to the encrypted values.

There are three practical approaches:

1. Transfer the Key config entities that belong to the encryption key, then copy the private key files from `.easy_encryption` (when the default file-based private encryption material key storage is used)
2. Transfer the Key config entities that belong to the encryption key, then transfer the private key from Drupal State (only relevant if the default file-based private encryption material key storage was unavailable)
3. Use the Key transfer feature (recommended for manual site moves and cross-site transfers)

#### Option A: Copying `.easy_encryption` (file-based transfer)

If your private key is stored on disk (the default), you can transfer it by securely copying the `.easy_encryption` directory contents from the source environment to the target environment.

This only works if the target site also has the matching Key config entities for the encryption key (typically via config sync or a full site copy). Keep the same file permissions (0600) and ensure the directory is excluded from version control.

This approach is simple, but it is also easy to do unsafely. Treat the private key as a secret, move it over an encrypted channel, and restrict who can access it.

#### Option B: State-based private key (fallback)

If the private key is stored in Drupal State (fallback), you still need the matching Key config entities for the encryption key.

The most common transfer is simply moving the whole site database (or using whatever “copy site” feature your hosting platform provides). Advanced users can also extract State values with Drush (for example `drush state:get ...`) and re-inject them on the target site, but the safest general option is to use the Key transfer UI described below.

#### Option C: Key transfer (UI import and export)

Key transfer is the safest way to move an encryption key between sites or environments because it is designed to move the key in a portable form.

Easy Encryption provides a key transfer API that can export an encryption key as a portable text package and import it on another site. This is exposed in the optional **Easy Encryption Admin** UI as an Export operation and an Import form.

Typical workflow:
1. On the source site, export the encryption key ID you want to move (download or copy the exported key text).
2. On the target site, import the exported key text, optionally activating it after import.
3. Deploy your configuration that contains encrypted Key entities. Once the required encryption key is present, the target site can decrypt and use the credentials.

### Encrypt-only environments

Some deployment workflows use "encrypt-only" environments where encryption is allowed (public key available) but decryption is not (private key not deployed).

Easy Encryption supports this workflow:

1. Encrypt credentials on your development or CI environment (public key only)
2. Export configuration containing the encrypted values
3. Deploy to production with the private key available for decryption (and optionally the public key if you want production to encrypt new values too)

This is useful for workflows where you want to restrict which environments can decrypt credentials, while still allowing encryption during development or CI.

## Architecture

Easy Encryption is built with security and maintainability in mind:

- **Immutable value objects**: Encryption keys and encrypted values are immutable objects that prevent accidental modification
- **Exception hierarchy**: Clear exception types for different failure scenarios
- **Memory safety**: Private keys are cleared from memory immediately after use using `sodium_memzero()`
- **Type safety**: Uses PHP 8.3+ features like typed properties and the `#[\SensitiveParameter]` attribute
- **Layered design**: Separates cryptographic operations, key management, and Drupal integration

## Security considerations

### What this module protects against

- Accidental exposure of credentials in configuration files
- Credentials leaked through configuration exports
- Credentials visible to users who have access to view configuration

### What this module does NOT protect against

- Server compromise where an attacker gains filesystem or database access
- Compromised private keys
- Runtime memory inspection attacks
- Social engineering or credential phishing

Easy Encryption improves your security posture significantly, but it is not a substitute for proper server hardening, access controls, and operational security practices.

### Best practices

1. **Never commit the private key**: Add `.easy_encryption` to your `.gitignore`
2. **Restrict file permissions**: The module sets private key files to 0600, but verify this on your server
3. **Use environment-specific keys**: Consider using different encryption keys per environment
4. **Rotate keys periodically**: Establish a key rotation policy for compliance
5. **Monitor key access**: Review logs for unexpected decryption failures
6. **Upgrade to external KMS**: For production, migrate private keys to a dedicated key management system

## Related resources

- [META: Improve security of AI and VDB provider credential storage](https://www.drupal.org/i/3559052)
- [Insecure credential storage used by drupal_cms_ai recipe as default](https://www.drupal.org/project/i/3560518)
- [Key module documentation](https://www.drupal.org/docs/contributed-modules/key)
- [libsodium documentation](https://libsodium.gitbook.io/)
