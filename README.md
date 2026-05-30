# Laravel Elasticsearch Boilerplate

A production-ready, decoupled Elasticsearch integration for Laravel. Drop it into any Laravel application as a local package and get full-text search, filtered queries, paginated results, and automatic model syncing via background queues — with zero Elasticsearch DSL leaking into your controllers.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.2` |
| Laravel | `^10.0` or `^11.0` |
| elasticsearch/elasticsearch | `^8.0` |

---

## File Structure

```
packages/
└── elasticsearch/
    ├── composer.json
    ├── config/
    │   └── elasticsearch.php          ← All connection, auth, index, and queue settings
    └── src/
        ├── ElasticsearchServiceProvider.php
        ├── Console/
        │   └── IndexManageCommand.php  ← php artisan elasticsearch:index
        ├── Contracts/
        │   └── SearchableContract.php  ← Interface for searchable Eloquent models
        ├── DTOs/
        │   ├── SearchQueryDTO.php      ← Typed input: query, filters, ranges, sort, pagination
        │   └── SearchResultDTO.php     ← Typed output: hits, total, pagination envelope
        ├── Jobs/
        │   ├── IndexDocumentJob.php    ← Queued: full index/replace
        │   ├── UpdateDocumentJob.php   ← Queued: partial field update
        │   └── DeleteDocumentJob.php   ← Queued: remove document
        ├── Observers/
        │   └── SearchableObserver.php  ← Wires Eloquent events → Jobs
        ├── Services/
        │   └── ElasticsearchService.php ← The only class that speaks raw DSL
        └── Traits/
            └── Searchable.php          ← Attach to any Eloquent model

app/                                    ← Example integration (your application code)
├── Http/
│   ├── Controllers/
│   │   └── ProductSearchController.php
│   └── Requests/
│       └── SearchRequest.php
└── Models/
    └── Product.php
```

---

## Installation

### 1. Register the local package path

Add a `repositories` entry and a `require` line to your root `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/elasticsearch"
        }
    ],
    "require": {
        "app/elasticsearch": "*"
    }
}
```

Then install:

```bash
composer update
```

Laravel's package auto-discovery will register `ElasticsearchServiceProvider` automatically via the `extra.laravel.providers` key in the package's `composer.json`.

### 2. Publish the config file

```bash
php artisan vendor:publish --tag=elasticsearch-config
```

This copies `packages/elasticsearch/config/elasticsearch.php` → `config/elasticsearch.php`.

### 3. Set your environment variables

Add these to your `.env` file:

```env
# ----- Connection -----
# Option A: Elastic Cloud (recommended)
ELASTICSEARCH_CLOUD_ID=my-deployment:dXMtZWFzdC0x...

# Option B: Self-managed cluster
ELASTICSEARCH_HOST=https://localhost:9200

# ----- Authentication -----
# Option A: API Key (recommended for cloud and modern self-managed)
ELASTICSEARCH_AUTH_METHOD=api_key
ELASTICSEARCH_API_KEY=VnVhQ2ZHY0JDZGJnVFR...

# Option B: Basic auth (self-managed)
ELASTICSEARCH_AUTH_METHOD=basic
ELASTICSEARCH_USERNAME=elastic
ELASTICSEARCH_PASSWORD=your-password

# ----- SSL (self-managed only) -----
# Leave verify=true in production. Point ca_bundle to the PEM exported from
# your Elasticsearch node:  docker cp es01:/usr/share/elasticsearch/config/certs/http_ca.crt .
ELASTICSEARCH_SSL_VERIFY=true
ELASTICSEARCH_CA_BUNDLE=/absolute/path/to/http_ca.crt

# ----- Index prefix (optional) -----
# Useful for separating environments on a shared cluster, e.g. "staging" → "staging_products"
ELASTICSEARCH_INDEX_PREFIX=

# ----- Queue -----
ELASTICSEARCH_QUEUE_CONNECTION=default
ELASTICSEARCH_QUEUE=elasticsearch
```

---

## Configuration Reference

`config/elasticsearch.php` gives you full control over:

| Key | Description |
|---|---|
| `hosts` | Array of node URLs (ignored when `cloud_id` is set) |
| `cloud_id` | Elastic Cloud deployment ID |
| `auth.method` | `"api_key"` or `"basic"` |
| `auth.api_key` | API key string |
| `auth.username` / `auth.password` | Basic auth credentials |
| `ssl.verify` | Set to `false` only for local dev |
| `ssl.ca_bundle` | Absolute path to CA PEM for self-managed TLS |
| `index_prefix` | String prepended to every index name |
| `indices` | Map of index names → Elasticsearch settings + mappings |
| `queue.connection` | Laravel queue connection for sync jobs |
| `queue.name` | Queue name for sync jobs |

### Defining an index mapping

Add a key under `indices` in `config/elasticsearch.php`. The structure maps directly to the Elasticsearch [Create index API](https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html):

```php
'indices' => [
    'products' => [
        'settings' => [
            'number_of_shards'   => 1,
            'number_of_replicas' => 1,
        ],
        'mappings' => [
            'properties' => [
                'id'          => ['type' => 'keyword'],
                'name'        => ['type' => 'text', 'analyzer' => 'standard'],
                'description' => ['type' => 'text', 'analyzer' => 'standard'],
                'price'       => ['type' => 'double'],
                'category'    => ['type' => 'keyword'],   // keyword = exact match / filterable
                'status'      => ['type' => 'keyword'],
                'created_at'  => ['type' => 'date'],
                'updated_at'  => ['type' => 'date'],
            ],
        ],
    ],

    // Add more indices here...
    'orders' => [
        'settings' => [...],
        'mappings' => [...],
    ],
],
```

> **Rule of thumb:** Use `text` for fields you want to full-text search (name, description, body). Use `keyword` for fields you filter, sort, or aggregate on (status, category, ID).

---

## Managing Indices

The package ships with an Artisan command for index lifecycle management:

```bash
# Create an index using its mapping from config/elasticsearch.php
php artisan elasticsearch:index create products

# Delete an index and all its documents (prompts for confirmation)
php artisan elasticsearch:index delete products

# Drop and recreate an index from scratch (prompts for confirmation)
# Use this after changing a mapping — Elasticsearch does not allow
# changing field types on an existing index.
php artisan elasticsearch:index recreate products
```

---

## Making a Model Searchable

### Step 1 — Implement the contract and apply the trait

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Packages\Elasticsearch\Contracts\SearchableContract;
use App\Packages\Elasticsearch\Traits\Searchable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements SearchableContract
{
    use Searchable;

    // ...

    public function searchableIndex(): string
    {
        return 'products'; // must match a key in config('elasticsearch.indices')
    }

    public function searchableId(): string
    {
        return (string) $this->id;
    }

    /**
     * Controls exactly what lands in the Elasticsearch document.
     * Keep this lean — only include what needs to be searched or filtered.
     */
    public function toSearchableArray(): array
    {
        return [
            'id'          => (string) $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => $this->price,
            'category'    => $this->category,
            'status'      => $this->status,
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

### Step 2 — That's it

From this point on, every `create`, `update`, `delete`, `restore`, and `forceDelete` on `Product` automatically dispatches a queued job to keep Elasticsearch in sync. Your API response time is never blocked by the sync.

The sync chain is:

```
Model Event (created/updated/deleted)
    → SearchableObserver
        → IndexDocumentJob / UpdateDocumentJob / DeleteDocumentJob (queued)
            → ElasticsearchService::indexDocument() / updateDocument() / deleteDocument()
                → Elasticsearch PHP Client
```

---

## Running the Queue Worker

Background sync jobs are dispatched to the `elasticsearch` queue. Start a worker before testing sync:

```bash
php artisan queue:work --queue=elasticsearch
```

For production, add this queue to your Supervisor config alongside your other workers. The jobs retry up to **3 times** with a **5-second backoff** before being marked as failed.

---

## Searching from a Controller

### Validation — `SearchRequest`

```php
// app/Http/Requests/SearchRequest.php
// Already generated. Validates: query, filters, ranges, sort, page, per_page.
```

### Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Packages\Elasticsearch\DTOs\SearchQueryDTO;
use App\Packages\Elasticsearch\Services\ElasticsearchService;
use Illuminate\Http\JsonResponse;

final class ProductSearchController extends Controller
{
    public function __construct(private readonly ElasticsearchService $search) {}

    public function __invoke(SearchRequest $request): JsonResponse
    {
        $dto = SearchQueryDTO::fromArray('products', [
            'query'    => $request->string('query')->trim()->value(),
            'filters'  => $request->array('filters'),
            'ranges'   => $request->array('ranges'),
            'sort'     => $request->array('sort'),
            'fields'   => ['name', 'description'],  // restrict full-text to these fields
            'page'     => $request->integer('page', 1),
            'per_page' => $request->integer('per_page', 15),
        ]);

        return response()->json(
            $this->search->search($dto)->toPaginatedArray()
        );
    }
}
```

### Route

```php
// routes/api.php
Route::get('/products/search', ProductSearchController::class);
```

---

## API Usage Examples

All examples target `GET /api/products/search`.

### Full-text search

```
GET /api/products/search?query=wireless+headphones
```

### Full-text search with an exact-match filter

```
GET /api/products/search?query=headphones&filters[status]=active
```

### Multiple values for a filter (terms query)

```
GET /api/products/search?filters[category][]=electronics&filters[category][]=audio
```

### Price range filter

```
GET /api/products/search?ranges[price][from]=50&ranges[price][to]=300
```

### Sorting

```
GET /api/products/search?sort[price]=asc
GET /api/products/search?sort[created_at]=desc
GET /api/products/search?sort[price]=asc&sort[created_at]=desc   # multi-sort
```

### Pagination

```
GET /api/products/search?page=2&per_page=20
```

### Everything combined

```
GET /api/products/search
    ?query=headphones
    &filters[status]=active
    &filters[category][]=electronics
    &ranges[price][from]=50&ranges[price][to]=300
    &sort[price]=asc
    &page=1
    &per_page=20
```

### Response shape

```json
{
    "data": [
        {
            "id": "42",
            "name": "Sony WH-1000XM5",
            "description": "Industry-leading noise cancelling wireless headphones",
            "price": 279.99,
            "category": "electronics",
            "status": "active",
            "created_at": "2024-01-15T10:30:00+00:00",
            "updated_at": "2024-03-01T08:00:00+00:00",
            "_id": "42",
            "_score": 4.823
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 143,
        "last_page": 8,
        "from": 1,
        "to": 20
    }
}
```

---

## Advanced Usage

### Manually triggering a sync

If you update a model outside of Eloquent (e.g. a bulk DB update), call the sync helpers directly:

```php
$product = Product::find(1);

// Full re-index
$product->syncToElasticsearch();

// Partial update — only the supplied fields are rewritten in ES
$product->updateInElasticsearch(['price' => 199.99, 'status' => 'sale']);

// Remove from the index
$product->removeFromElasticsearch();
```

### Using the service directly

Inject `ElasticsearchService` wherever you need lower-level control:

```php
use App\Packages\Elasticsearch\DTOs\SearchQueryDTO;
use App\Packages\Elasticsearch\Services\ElasticsearchService;

class ReportService
{
    public function __construct(private readonly ElasticsearchService $es) {}

    public function expensiveProducts(): array
    {
        $dto = new SearchQueryDTO(
            index:   'products',
            ranges:  ['price' => ['from' => 1000]],
            sort:    ['price' => 'desc'],
            perPage: 50,
        );

        return $this->es->search($dto)->hits;
    }
}
```

### Adding a new index for a different model

1. **Add the mapping** to `config/elasticsearch.php` under `indices`:

```php
'articles' => [
    'settings' => ['number_of_shards' => 1, 'number_of_replicas' => 1],
    'mappings' => [
        'properties' => [
            'id'      => ['type' => 'keyword'],
            'title'   => ['type' => 'text'],
            'body'    => ['type' => 'text'],
            'author'  => ['type' => 'keyword'],
            'tags'    => ['type' => 'keyword'],
            'published_at' => ['type' => 'date'],
        ],
    ],
],
```

2. **Apply the trait** to the model:

```php
class Article extends Model implements SearchableContract
{
    use Searchable;

    public function searchableIndex(): string { return 'articles'; }
    public function searchableId(): string    { return (string) $this->id; }

    public function toSearchableArray(): array
    {
        return [
            'id'           => (string) $this->id,
            'title'        => $this->title,
            'body'         => $this->body,
            'author'       => $this->author->name,
            'tags'         => $this->tags->pluck('name')->all(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
```

3. **Create the index:**

```bash
php artisan elasticsearch:index create articles
```

Done. All CRUD events on `Article` are now automatically synced.

### Disabling auto-sync temporarily

Wrap bulk operations in `withoutSyncingToElasticsearch()` by disabling the observer within a closure — or simply import and sync in bulk after:

```php
// Disable the observer for an import operation
Article::withoutObserver(SearchableObserver::class, function () {
    // bulk insert thousands of rows here...
});

// Then re-index via a queued job or a custom Artisan command
Article::cursor()->each(fn ($a) => $a->syncToElasticsearch());
```

---

## How the DSL Query Builder Works

The `ElasticsearchService::buildQuery()` method translates a `SearchQueryDTO` into a Elasticsearch `bool` query. You never write raw DSL outside the service.

| DTO field | DSL clause produced | Scoring impact |
|---|---|---|
| `query` (non-empty) | `bool.must` → `multi_match` with `fuzziness: AUTO` | Affects `_score` |
| `filters` (scalar) | `bool.filter` → `term` | Zero (filtered out) |
| `filters` (array value) | `bool.filter` → `terms` | Zero |
| `ranges` | `bool.filter` → `range` with `gte`/`lte` | Zero |
| No constraints | `match_all` | N/A |

`filter` clauses are used instead of `must` for exact matches because they do not affect relevance scoring and are automatically cached by Elasticsearch — this is the correct, performant approach for faceted search.

---

## Troubleshooting

**`No alive nodes found in your cluster` / connection refused**
- Confirm your `ELASTICSEARCH_HOST` or `ELASTICSEARCH_CLOUD_ID` is correct.
- For self-managed clusters, ensure the CA bundle path is set and the certificate is valid.
- Set `ELASTICSEARCH_SSL_VERIFY=false` only as a last resort for local dev.

**Documents not appearing in search results**
- Confirm the queue worker is running: `php artisan queue:work --queue=elasticsearch`
- Check failed jobs: `php artisan queue:failed`
- Verify the index was created: `php artisan elasticsearch:index create products`

**`index_not_found_exception`**
- Run `php artisan elasticsearch:index create <name>` to create the index before syncing data.

**Mapping conflict after changing `config/elasticsearch.php`**
- Elasticsearch does not allow changing the type of an existing field. Recreate the index:
  ```bash
  php artisan elasticsearch:index recreate products
  ```
  Then re-populate it by re-saving the relevant models or running a bulk sync command.

**Jobs failing silently**
- Check `LOG_CHANNEL` and your log output — every ES exception is logged at the `error` level with the index, ID, and message.
- Failed jobs are retried up to 3 times. Inspect them with `php artisan queue:failed`.

---

## License

MIT
