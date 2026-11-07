# amazee.ai Private AI Provider

This is the documentation for the **amazee.ai Private AI Provider** module for Drupal. It connects your Drupal site to the [amazee.ai](https://amazee.ai) managed Private AI platform, providing access to Large Language Models (LLMs) and a Vector Database (VectorDB) for AI-powered features such as Search AI, RAG (Retrieval-Augmented Generation), content generation, and more.

## Overview

The module integrates with the [Drupal AI module](https://www.drupal.org/project/ai) and provides:

- **LLM access** — Chat, completion, embeddings, and other AI operations via amazee.ai's private LiteLLM gateway.
- **VectorDB access** — A managed PostgreSQL-compatible vector database (pgvector) for RAG and Search AI.
- **Automatic key management** — Keys are provisioned and stored automatically via the [Key module](https://www.drupal.org/project/key) using config-based key storage by default.

---

## Signing In

Authentication is handled directly through the Drupal admin UI — no separate login on the amazee.ai account portal is required to connect your site.

### Steps

1. Navigate to **Administration → Configuration → AI → amazee.ai Provider** (`/admin/config/ai/providers/amazeeio`).
2. Enter your **email address** and click **Sign in**.
3. Check your inbox for a **one-time verification code** sent by amazee.ai, enter it, and click **Validate**.
4. Choose the key to use for this site:
   - **Use an existing key** — if your account already has keys, they are listed in a table (name, region, and LLM URL). Select one and click **Use selected key**.
   - **Create a new key** — enter a **Key Name** (defaults to your site's hostname) and select a **Region** (data center location), then click **Create new key**.

Once a key is chosen the module automatically:

- Stores the LLM bearer token and the VectorDB password as Key module entries (`amazeeio_ai` and `amazeeio_ai_database`).
- Creates a **management token** and stores it as a third Key entry (`amazeeio_ai_management_token`). This token lets the provider settings page display your team and account details; the LLM and VectorDB work without it.
- Writes the connection details (LLM host, VectorDB host/port/database/user) to `ai_provider_amazeeio.settings`.
- Configures default AI models for each operation type.

> **Note:** By entering your email address you agree to amazee.ai's [Terms of Service](https://amazee.ai/terms-and-conditions).

### Disconnecting

To disconnect your site from amazee.ai, navigate to the same settings page and click **Disconnect**. You will be asked to confirm. Disconnecting removes the locally stored keys (`amazeeio_ai`, `amazeeio_ai_database`, and `amazeeio_ai_management_token`) and clears the module configuration, but does **not** delete your amazee.ai account or the remote API key. You can reconnect at any time and reuse the same key.

---

## Subscribing / Paying for the Service

amazee.ai offers different plan tiers to match your usage:

- **Trial / Anonymous** — A limited free tier. When connected via a trial account you will see a notice in the provider settings. The trial has a very limited budget and is intended for evaluation only.
- **Full User Account** — Sign up at [https://my.amazee.io](https://my.amazee.io) for a full account with higher rate limits and budgets.
- **Team / Enterprise** — Contact amazee.ai directly for volume pricing, SLAs, and dedicated infrastructure options.

### Managing Your Account

[amazee.ai](https://amazee.ai) is the private, data-sovereign AI platform from [amazee.io](https://amazee.io) — the company behind managed hosting, AI, and related cloud services. Your amazee.ai account is part of your amazee.io account, and the single dashboard for all amazee.io products (including AI) is the amazee.io portal:

**[https://my.amazee.io](https://my.amazee.io)**

There you can:

1. Log in with the same email address you use in Drupal.
2. View usage and spend, set budget/spending limits, and manage billing.
3. Create, view, or revoke API keys and management tokens, and manage team membership.
4. To upgrade from a trial account, disconnect the trial in Drupal, then reconnect using your full registered account email.

> **Note:** For the standard sign-in flow you do **not** need to visit the portal first — the module creates and stores the keys for you. The portal is where you manage the account, budgets, and keys after the fact (for example to rotate a key or review spend).

---

## Manual Configuration (Recipes & Pre-provisioning)

If you have received credentials manually (e.g. via email for a dedicated environment) or need to automate the setup via CI/CD, you should use the **amazee.ai AI Provider Recipe**.

### Using the Recipe (Recommended)

The [amazee.ai AI Provider Recipe](https://www.drupal.org/project/ai_provider_amazeeio_recipe) automates the creation of Key entities and module configuration.

1. Require the recipe via Composer:
   ```bash
   composer require drupal/ai_provider_amazeeio_recipe -W
   ```
2. Run the recipe using Drush, passing the connection details as inputs (or run it non-interactively with `-n` / `-y` to provision a **free anonymous trial account** automatically):
   ```bash
   drush recipe [RECIPES_PATH]/ai_provider_amazeeio_recipe \
     --input=ai_provider_amazeeio_recipe.llm_host={llm_host} \
     --input=ai_provider_amazeeio_recipe.llm_api_key={llm_api_key} \
     --input=ai_provider_amazeeio_recipe.postgres_db_host={postgres_host} \
     --input=ai_provider_amazeeio_recipe.postgres_db_port={postgres_port} \
     --input=ai_provider_amazeeio_recipe.postgres_db_username={postgres_username} \
     --input=ai_provider_amazeeio_recipe.postgres_db_password={postgres_password} \
     --input=ai_provider_amazeeio_recipe.postgres_db_default_database={postgres_database}
   ```
3. Export your configuration to verify the settings:
   ```bash
   drush cex -y
   ```

> **Recipe limitation — no management token:** Unlike the interactive UI sign-in, the recipe provisions the LLM key (`amazeeio_ai`) and VectorDB key (`amazeeio_ai_database`) only. It does **not** create the `amazeeio_ai_management_token`. The LLM and VectorDB are fully functional, but the provider settings page cannot display the team/account panel that the management token powers. To add it, complete the sign-in flow once in the UI, or manage the account and tokens at [https://my.amazee.io](https://my.amazee.io).

For more details on manual configuration and environment-specific overrides, see [Advanced Configuration](advanced-configuration.md).

---

## Getting Support

If you encounter issues with the module or the amazee.ai platform, the following support channels are available:

| Channel | Details |
|---|---|
| **Email** | [ai.support@amazee.ai](mailto:ai.support@amazee.ai) |
| **Drupal issue queue** | [drupal.org/project/issues/ai_provider_amazeeio](https://www.drupal.org/project/issues/ai_provider_amazeeio) |
| **Account portal** | [https://my.amazee.io](https://my.amazee.io) — manage your account, keys, teams, and budgets |
| **amazee.ai website** | [https://amazee.ai](https://amazee.ai) |

When contacting support, please include:

- Your Drupal version and module version.
- The **key name** shown in the provider settings (this is your site's hostname, e.g. `www.example.com`).
- Any relevant error messages from the Drupal log (`/admin/reports/dblog`).

---

## Further Reading

- [Using the amazee.ai Vector Database](vector-database.md) — How to wire up the included VectorDB to a Search API server, what the Collection / Database Name / Similarity Metric settings mean, and how the vector dimension is determined.
- [Advanced Configuration](advanced-configuration.md) — How to manage API keys and the VectorDB password outside of the default config-based Key module setup (e.g., environment variables, AWS Secrets Manager, or Lagoon secrets).
- [Deployment Guide](deployment.md) — Best practices and trade-offs for running amazee.ai across local, development, staging, and production environments, including key-sharing strategies for the VectorDB / Search AI use case.
