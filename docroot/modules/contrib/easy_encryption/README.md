# Easy Encryption

Zero-configuration, asymmetric encryption at rest for sensitive data and credentials in Drupal.

Easy Encryption integrates seamlessly with Drupal's [Key module](https://www.drupal.org/project/key) to encrypt sensitive configuration and credentials out of the box. By default, it uses modern, audited asymmetric cryptography (libsodium sealed boxes via `paragonie/sodium_compat`). Its public APIs decouple cryptographic operations from key storage, allowing implementations and backends to be customized or swapped.

---

## Quickstart

### 1. Installation

```bash
composer require drupal/easy_encryption
drush pm:enable easy_encryption
```

Upon installation, Easy Encryption automatically generates an asymmetric encryption key pair:

- **Public key**: Stored in Drupal configuration. Used to encrypt values. Safe to commit to version control.
- **Private key**: Stored locally in `.easy_encryption/` outside the web root (with `0600` permissions). Used to decrypt values. Never committed to version control.

### 2. Create an Encrypted Key

1. Navigate to **Configuration > System > Keys** (`/admin/config/system/keys`) and click **Add key**.
2. Fill in the key label and machine name.
3. Under **Key provider**, select **Easy Encrypted** (selected by default).
4. Enter your sensitive credential (API secret, token, password).
5. Click **Save**.

The ciphertext and active encryption key ID are stored in configuration; plaintext credentials never touch disk or database storage.

### 3. Access in Code

Encrypted keys work transparently through Drupal's standard Key repository:

```php
$apiKey = \Drupal::service('key.repository')->getKey('my_api_key')->getKeyValue();

// Use $apiKey to authenticate with external services.
```

---

## Features

- **Zero-configuration setup**: Install and start encrypting immediately without manual key generation or crypto configuration.
- **Asymmetric encryption at rest**: Public key encrypts, private key decrypts (libsodium sealed boxes by default).
- **Exportable configuration**: Encrypted ciphertexts and public keys can be safely committed to version control and deployed via Drupal configuration synchronization (`config/sync`).
- **Transparent credential security upgrades**: Automatically upgrades new Key entities created with insecure providers (such as `config` and `state`) to `easy_encrypted` before they are saved. This protects credentials created manually, via Recipes, or via automation without plaintext leakage.
- **Multi-environment support**: Author and encrypt secrets in development or CI ("encrypt-only" mode) without needing access to production private keys.
- **Key rotation via CLI**: Rotate active encryption keys and re-encrypt existing credentials using Drush commands.
- **Uninstall protection**: Built-in uninstall validator prevents accidental uninstallation while encrypted keys remain in storage.
- **Pluggable cryptography backend**: Decoupled cryptographic interfaces (`EncryptorInterface`, `EncryptedValue`) support alternative encryption backends.
- **Optional Admin UI (`easy_encryption_admin`)**: Manage key import and export via a web interface for portable key transfers.

---

## How It Works

Easy Encryption centers around two core abstractions: an **encryption key** and an **encryption key ID**. By default, keys are backed by a libsodium sealed box key pair:

```
           [ Plaintext Secret ]
                    │
                    ▼  (Encrypt with Public Key - Safe in Config)
        [ Libsodium Ciphertext ] ──> Stored in Drupal config (config/sync)
                    │
                    ▼  (Decrypt with Private Key - Kept Secret)
           [ Plaintext Secret ] ──> Cleared from memory (sodium_memzero)
```

### Encryption

When creating or updating an `easy_encrypted` Key entity:

1. The secret value is encrypted in memory using the active public key.
2. The ciphertext and active encryption key ID are written to configuration.
3. The configuration can be safely exported and committed to Git.

### Decryption

When Drupal retrieves a key value (`$key->getKeyValue()`):

1. The module loads the encryption key matching the stored encryption key ID.
2. The private key decrypts the ciphertext in memory.
3. The plaintext value is returned to the caller.
4. The private key material is immediately wiped from memory using `sodium_memzero()`.

### Key Storage & Fallback

- **Filesystem (Default)**: The private key is stored in `.easy_encryption/` next to the web root. Private key files are written with restricted permissions (`0600`) and wrapped in PHP execution protection (`<?php return '...';`).
- **Drupal State (Automatic Fallback)**: If filesystem writes fail (e.g. read-only container filesystems), the private key falls back to Drupal's state database storage.

> **Important**: Never commit the `.easy_encryption` directory to version control.

---

## Operations & CLI

### Checking Key Status

Visit **Reports > Status report** (`/admin/reports/status`) to verify:

- An active encryption key is configured.
- The private key is available for decryption.
- Private key storage health (notifies if running on State fallback).

### Rotating Encryption Keys

To rotate your encryption key:

```bash
# Preview keys that would be re-encrypted:
drush easy-encryption:rotate --dry-run --reencrypt

# Rotate active key and re-encrypt existing keys:
drush easy-encryption:rotate --reencrypt
```

Without `--reencrypt`, a new active key is generated for future encryptions, while existing keys remain decryptable with their original key pairs.

### Migrating Private Keys (State to Filesystem)

If a private key was originally saved to Drupal State (fallback) and filesystem storage is now available:

```bash
# Check migration status:
drush easy-encryption:migrate-private-key --check

# Execute migration:
drush easy-encryption:migrate-private-key
```

### Optional Admin UI (`easy_encryption_admin`)

The module includes an optional submodule, **Easy Encryption Admin**, which provides a user interface for:

- Viewing configured encryption keys.
- Exporting encryption keys as portable packages.
- Importing encryption keys onto another site or environment.
- Migrating private keys from database state to filesystem storage.

---

## Configuration

Customize module behavior in `settings.php`:

### Private Key Directory

Change the filesystem directory where private keys are stored:

```php
$settings['easy_encryption']['private_key_directory'] = '/secure/path/outside/webroot';
```

Ensure this directory exists, is writable by the web server process, is outside the web root, and is excluded from version control.

### Transparent Credential Security Upgrades

By default, newly created keys targeting insecure providers (`config` and `state`) are automatically converted to `easy_encrypted`.

To customize which providers are intercepted:

```php
$settings['easy_encryption']['upgraded_key_providers'] = ['config', 'state'];
```

To disable automatic upgrades:

```php
$settings['easy_encryption']['upgraded_key_providers'] = [];
```

---

## Multi-Environment & Deployment Workflows

Because Easy Encryption uses asymmetric cryptography, environments that only author or export configuration (such as local development or CI) do not require production private keys:

* **Encrypt-Only Environments (Dev / CI)**: Can create, edit, and export encrypted keys using the public key in configuration without needing production private keys.
* **Production Environments**: Require the private decryption key via server environment variables, the default local filesystem storage, or external secret managers.
* **Site Moves & Key Transfer**: When moving encrypted configuration between independent sites, matching key material must be transferred.

For detailed architectures and step-by-step guides, see the documentation:

* 📖 [Multi-Environment Key Management & Production Workflows](https://project.pages.drupalcode.org/easy_encryption/guides/multi-environment-workflows/)
* 📖 [Transferring Keys Across Sites & Environments](https://project.pages.drupalcode.org/easy_encryption/guides/key-transfer-and-site-moves/)

---

## Security Model

### What This Module Protects Against

- Credential leaks through exported configuration files (`config/sync`) or Git repositories.
- Plaintext credentials stored in database configuration tables.
- Accidental exposure of credentials to users with view-only configuration permissions.

### What This Module Does NOT Protect Against

- Full server compromise (where an attacker has local file or database read access).
- Compromised private decryption keys.
- Runtime memory dump attacks while decryption is occurring.
- Phishing or social engineering.

### Recommended Production Practices

1. **Exclude private keys from Git**: Add `.easy_encryption` to `.gitignore`.
2. **Restrict permissions**: Verify that private key files remain set to `0600`.
3. **Inject private keys via environment variables**: For containerized or high-security deployments, configure the private key entity to use Key module's `env` provider.
4. **Use external KMS**: When compliance requires it, back private keys with dedicated key management systems (HashiCorp Vault, AWS Secrets Manager, etc.).
5. **Rotate keys periodically**: Establish a regular rotation cadence using `drush easy-encryption:rotate`.

---

## Background & Related Resources

Easy Encryption was initiated to address secure credential storage needs in Drupal CMS and contributed modules (notably AI and Vector Database integrations):

- [META: Improve security of AI and VDB provider credential storage](https://www.drupal.org/i/3559052)
- [Insecure credential storage used by drupal_cms_ai recipe as default](https://www.drupal.org/project/i/3560518)

### Documentation & References

- [Full Documentation Site](https://project.pages.drupalcode.org/easy_encryption/)
- [Architectural Decision Records (ADRs)](https://project.pages.drupalcode.org/easy_encryption/architecture/adr/)
- [Key Module Documentation](https://www.drupal.org/docs/contributed-modules/key)
- [Libsodium Documentation](https://libsodium.gitbook.io/)
