# Deployment Guide

This page covers recommended strategies for running the amazee.ai provider across different environments: **local**, **development**, **staging**, and **production**. It also discusses the trade-offs of sharing API keys and VectorDB connections between environments — a decision that significantly affects Search AI / RAG behaviour.

---

## Environment Overview

| Environment | Typical goal | Recommended key strategy |
|---|---|---|
| Local | Developer iteration, UI testing | Shared dev key **or** personal trial key |
| Development | Integration testing, CI | Shared dev key with a dedicated VectorDB database |
| Staging | Pre-production validation | Production-equivalent keys with an isolated VectorDB database |
| Production | Live traffic | Dedicated keys, isolated VectorDB |

---

## Local Development

### Option A — Use a Personal Trial Account

The simplest approach. Each developer signs in with their own amazee.ai account via the settings UI. This gives full isolation but requires each developer to manage their own subscription budget.

### Option B — Shared Development Key via Environment Variable

A team key is provisioned once and distributed via environment variables (e.g. in a `.env` file not committed to version control):

```bash
# .env.local (gitignored)
AMAZEEIO_AI_KEY=sk-dev-...
AMAZEEIO_AI_DB_PASSWORD=dev-secret
```

Configure the Key entities to use the **Environment variable** provider (see [Advanced Configuration](advanced-configuration.md)). Every developer automatically picks up the shared key without using the sign-in UI.

> **Note for Lando / DDEV users:** Inject the variables through your tool's environment configuration (e.g. `lando.env` or `ddev/config.yaml`) so they are available inside the container.

---

## Development / CI Environment

For automated testing pipelines:

1. Store the key and VectorDB password as **CI secret variables** (e.g. GitLab CI/CD variables, GitHub Actions secrets).
2. Inject them at pipeline run time.
3. Use a **dedicated VectorDB database** (not shared with other environments) to prevent test data contaminating the index seen by other environments.

```yaml
# .gitlab-ci.yml example
variables:
  AMAZEEIO_AI_KEY: $CI_AMAZEEIO_AI_KEY
  AMAZEEIO_AI_DB_PASSWORD: $CI_AMAZEEIO_AI_DB_PASSWORD
```

---

## Staging Environment

Staging should validate behaviour as close to production as possible. Recommended approach:

- Use **production-equivalent credentials** (same region, same subscription tier) so rate limits and model availability match production.
- Connect to a **separate VectorDB database** within the same PostgreSQL instance (or request a separate provisioned instance from amazee.ai support).
- Populate the staging VectorDB with a known dataset so Search AI results are predictable and reviewable.

---

## Production Environment

- Store keys in a secrets manager (Vault, AWS Secrets Manager, Lagoon secrets) — see [Advanced Configuration](advanced-configuration.md).
- Never expose key values in version-controlled config exports.
- Set up monitoring and alerting on your amazee.ai account's budget via the [amazee.ai dashboard](https://amazee.ai).

---

## Key Sharing: Pros and Cons

The most impactful architectural decision for teams using **Search AI / RAG** is whether to share the same VectorDB connection (and therefore the same indexed content) across environments.

### Sharing the Same VectorDB and API Key Across Environments

| ✅ Pros | ⚠️ Cons / Risks |
|---|---|
| No re-indexing required when deploying to a new environment | Changes to the index on dev/staging affect what users see on production |
| Content indexed on production is immediately available on a local clone | Developers could accidentally delete or corrupt the production index |
| Simpler key management — one key, one VectorDB | Budget is shared; heavy testing can consume production quota |
| Useful for read-only preview scenarios (e.g. reviewing what production Search AI returns) | Privacy / compliance risk if production data is visible on non-production environments |

### Using Separate VectorDB Databases Per Environment

| ✅ Pros | ⚠️ Cons / Risks |
|---|---|
| Full isolation — testing cannot affect production data | Each environment must index its own content; this takes time and consumes API budget |
| Safe to experiment with indexing settings, chunking strategies, etc. | Index state may differ between environments, making pre-production validation harder |
| Clearer budget attribution per environment | Additional operational overhead (more Key entities, more connection strings) |
| Meets data residency / privacy requirements more easily | |

### Recommendation

| Scenario | Recommendation |
|---|---|
| Small team, read-heavy Search AI | Share the **production VectorDB** on staging (read-only) so staging Search results mirror production. Keep a separate VectorDB for local/CI to avoid corruption. |
| Active index experimentation or heavy LLM usage on dev | Use fully isolated VectorDB per environment. |
| Compliance / regulated data | Always use separate VectorDB databases per environment. |

---

## Lagoon-Specific Setup

If your Drupal project is hosted on [Lagoon](https://lagoon.sh/) (for example via amazee.io), the recommended approach is:

1. Store the LLM API key and VectorDB password as **Lagoon project variables** with `scope: global` for values shared across all environments, or `scope: environment` for per-environment overrides.
2. Use the **Environment variable** Key provider in Drupal.
3. Use a dedicated `postgres.yml` Lagoon service definition if you want a fully managed VectorDB separate from your application database.

```bash
# Add a project-level variable in Lagoon (available in all environments)
lagoon add variable \
  --project my-project \
  --name AMAZEEIO_AI_KEY \
  --value sk-prod-... \
  --scope global

# Override for a specific environment
lagoon add variable \
  --project my-project \
  --environment development \
  --name AMAZEEIO_AI_KEY \
  --value sk-dev-... \
  --scope runtime
```

Contact [ai.support@amazee.ai](mailto:ai.support@amazee.ai) if you need help provisioning additional VectorDB instances for a Lagoon project.
