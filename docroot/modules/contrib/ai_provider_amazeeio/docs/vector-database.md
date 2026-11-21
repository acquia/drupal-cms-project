# Using the amazee.ai Vector Database

Your amazee.ai private AI key includes a dedicated PostgreSQL database with the [pgvector](https://github.com/pgvector/pgvector) extension. The host, port, username, password, and database name are configured for you automatically when you [connect on the provider settings page](index.md#signing-in) (`/admin/config/ai/providers/amazeeio`); you do not need to enter them anywhere else.

The database password is stored as a [Key module](https://www.drupal.org/project/key) entry named `amazeeio_ai_database`. If you need to supply it from an environment variable or a secrets manager instead of config, see [Advanced Configuration](advanced-configuration.md).

## Adding the VectorDB to a Search API server

To use it as a vector store for AI Search, create a Search API server at `/admin/config/search/search-api/add-server` with:

- **Backend**: *AI Search*
- **Vector Database**: *amazee.ai Vector Database*

The provider then asks for three settings. The defaults are usually fine.

| Setting | What it is | Recommended value |
|---|---|---|
| Database Name | The PostgreSQL database allocated to your amazee.ai key. | Leave the pre-filled value. |
| Collection | The table created inside that database to hold this server's vectors. Created on save; cannot be renamed afterwards. Letters, numbers and underscores only. | `amazee_ai` (the default). Use a unique name per Search API server if you have more than one. |
| Similarity Metric | The distance function used for vector search. | `Cosine Similarity` (default), unless your embedding model's documentation explicitly recommends otherwise. |

## About the number of dimensions

The vector dimension is **not** chosen on the Search API server form — it is determined by the embedding model you pick under *Embeddings Engine* on the same form. The collection is created with that dimension on first save, and every embedding written to it afterwards must match.

Different embedding models produce vectors of different sizes (for example, some commonly used models produce vectors with 1024 dimensions, others use larger or smaller sizes). Refer to the documentation of the specific embedding model you select for its exact value.

If you later want to switch to a model with a different dimension, you must create a new Search API server with a new collection name; you cannot change the dimension of an existing collection.

## See also

- [Advanced Configuration](advanced-configuration.md) — storing the VectorDB password (`amazeeio_ai_database`) outside config, e.g. via environment variables or a secrets manager.
- [Deployment Guide](deployment.md) — trade-offs of sharing one VectorDB across environments versus isolating a database per environment.
- [my.amazee.io](https://my.amazee.io) — manage your amazee.ai account, keys, and the database allocated to them.
