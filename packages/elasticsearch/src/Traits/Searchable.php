<?php

declare(strict_types=1);

namespace App\Packages\Elasticsearch\Traits;

use App\Packages\Elasticsearch\Jobs\DeleteDocumentJob;
use App\Packages\Elasticsearch\Jobs\IndexDocumentJob;
use App\Packages\Elasticsearch\Jobs\UpdateDocumentJob;
use App\Packages\Elasticsearch\Observers\SearchableObserver;

/**
 * Attach to any Eloquent model to keep Elasticsearch automatically in sync.
 *
 * The model MUST also implement SearchableContract (or provide the three
 * required methods itself). Overriding searchableIndex() / searchableId() /
 * toSearchableArray() in the model customises what gets indexed.
 *
 * Usage:
 *   class Product extends Model implements SearchableContract
 *   {
 *       use Searchable;
 *
 *       public function toSearchableArray(): array { ... }
 *   }
 */
trait Searchable
{
    /** Registers the observer that triggers sync on create / update / delete. */
    public static function bootSearchable(): void
    {
        static::observe(SearchableObserver::class);
    }

    /** Default index name — the model's DB table name. Override as needed. */
    public function searchableIndex(): string
    {
        return $this->getTable();
    }

    /** Default document ID — the model's primary key cast to string. */
    public function searchableId(): string
    {
        return (string) $this->getKey();
    }

    /**
     * Default document body — the full model array.
     * Override to control exactly what fields are indexed.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return $this->toArray();
    }

    // -------------------------------------------------------------------------
    // Public sync helpers (called by the observer; also usable directly)
    // -------------------------------------------------------------------------

    /**
     * Dispatch a queued job to (re-)index this document in full.
     * Replaces any existing document with the same ID.
     */
    public function syncToElasticsearch(): void
    {
        IndexDocumentJob::dispatch(
            $this->searchableIndex(),
            $this->searchableId(),
            $this->toSearchableArray(),
        )
        ->onConnection((string) config('elasticsearch.queue.connection'))
        ->onQueue((string) config('elasticsearch.queue.name'));
    }

    /**
     * Dispatch a queued job to apply a partial field update.
     * Only the supplied fields are rewritten; all others are preserved.
     *
     * @param array<string, mixed> $fields
     */
    public function updateInElasticsearch(array $fields): void
    {
        UpdateDocumentJob::dispatch(
            $this->searchableIndex(),
            $this->searchableId(),
            $fields,
        )
        ->onConnection((string) config('elasticsearch.queue.connection'))
        ->onQueue((string) config('elasticsearch.queue.name'));
    }

    /** Dispatch a queued job to remove this document from the index. */
    public function removeFromElasticsearch(): void
    {
        DeleteDocumentJob::dispatch(
            $this->searchableIndex(),
            $this->searchableId(),
        )
        ->onConnection((string) config('elasticsearch.queue.connection'))
        ->onQueue((string) config('elasticsearch.queue.name'));
    }
}
