# amazee.ai Private AI Provider

This is the documentation for the **amazee.ai Private AI Provider** module for Drupal. It connects your Drupal site to the [amazee.ai](https://amazee.ai) managed Private AI platform, providing access to Large Language Models (LLMs) and a Vector Database (VectorDB) for AI-powered features such as Search AI, RAG (Retrieval-Augmented Generation), content generation, and more.

## Overview

The module integrates with the [Drupal AI module](https://www.drupal.org/project/ai) and provides:

- **LLM access** — Chat, completion, embeddings, and other AI operations via amazee.ai's private LiteLLM gateway.
- **VectorDB access** — A managed PostgreSQL-compatible vector database (pgvector) for RAG and Search AI.
- **Automatic key management** — Keys are provisioned and stored automatically via the [Key module](https://www.drupal.org/project/key) using config-based key storage by default.

---

## Signing In

Authentication is handled directly through the Drupal admin UI — no separate amazee.ai dashboard login is required for initial setup.

### Steps

1. Navigate to **Administration → Configuration → AI → amazee.ai Provider** (`/admin/config/ai/providers/amazeeio`).
2. Enter your **email address** and click **Sign in**.
3. Check your inbox for a **one-time verification code** sent by amazee.ai.
4. Enter the code and click **Validate**.
5. Select your preferred **region** (data center location) and click **Connect**.

The module will automatically:

- Provision a private API key scoped to your site's hostname.
- Create a managed VectorDB instance in the selected region.
- Store both credentials as Key module entries (`amazeeio_ai` and `amazeeio_ai_database`).
- Configure default AI models for each operation type.

> **Note:** By entering your email address you agree to amazee.ai's [Terms of Service](https://amazee.ai/terms-and-conditions).

### Disconnecting

To disconnect your site from amazee.ai, navigate to the same settings page and click **Disconnect**. You will be asked to confirm. Disconnecting removes the locally stored API keys and clears the module configuration, but does **not** delete your amazee.ai account or the remote API key.

---

## Subscribing / Paying for the Service

amazee.ai offers different plan tiers to match your usage:

- **Trial / Anonymous** — A limited free tier. When connected via a trial account you will see a notice in the provider settings. The trial has a very limited budget and is intended for evaluation only.
- **Full User Account** — Sign up at [https://amazee.ai](https://amazee.ai) for a full account with higher rate limits and budgets.
- **Team / Enterprise** — Contact amazee.ai directly for volume pricing, SLAs, and dedicated infrastructure options.

### Managing Your Subscription

1. Visit [https://amazee.ai](https://amazee.ai) and log in with the same email address you use in Drupal.
2. Navigate to your account's **Billing** section to view usage, set spending limits, and update payment information.
3. To upgrade from a trial account, disconnect the trial in Drupal, then reconnect using your full registered account email.

> **Note:** API keys and VectorDB credentials are managed automatically within your Drupal site. There is no separate dashboard on amazee.ai for manually generating or rotating keys.

---

## Manual Configuration (Recipes & Pre-provisioning)

If you have received credentials manually (e.g. via email for a dedicated environment) or need to automate the setup via CI/CD, you should use the **amazee.ai AI Provider Recipe**.

### Using the Recipe (Recommended)

The [amazee.ai AI Provider Recipe](https://www.drupal.org/project/ai_provider_amazeeio_recipe) automates the creation of Key entities and module configuration.

1. Require the recipe via Composer:
   ```bash
   composer require drupal/ai_provider_amazeeio_recipe
   ```
2. Run the recipe using Drush:
   ```bash
   drush recipe ../recipes/ai_provider_amazeeio_recipe
   ```
3. Export your configuration to verify the settings:
   ```bash
   drush cex -y
   ```

For more details on manual configuration and environment-specific overrides, see [Advanced Configuration](advanced-configuration.md).

---

## Getting Support

If you encounter issues with the module or the amazee.ai platform, the following support channels are available:

| Channel | Details |
|---|---|
| **Email** | [ai.support@amazee.ai](mailto:ai.support@amazee.ai) |
| **Drupal issue queue** | [drupal.org/project/issues/ai_provider_amazeeio](https://www.drupal.org/project/issues/ai_provider_amazeeio) |
| **amazee.ai website** | [https://amazee.ai](https://amazee.ai) |

When contacting support, please include:

- Your Drupal version and module version.
- The **key name** shown in the provider settings (this is your site's hostname, e.g. `www.example.com`).
- Any relevant error messages from the Drupal log (`/admin/reports/dblog`).

---

## Further Reading

- [Advanced Configuration](advanced-configuration.md) — How to manage API keys and the VectorDB password outside of the default config-based Key module setup (e.g., environment variables, AWS Secrets Manager, or Lagoon secrets).
- [Deployment Guide](deployment.md) — Best practices and trade-offs for running amazee.ai across local, development, staging, and production environments, including key-sharing strategies for the VectorDB / Search AI use case.
