<?php

declare(strict_types=1);

namespace App\Packages\Elasticsearch\Observers;

use Illuminate\Database\Eloquent\Model;

/**
 * Wired up by Searchable::bootSearchable().
 * Every method receives the model that fired the event.
 *
 * The @var casts below tell static analysers that the model
 * always has the Searchable trait methods at this call site.
 */
final class SearchableObserver
{
    public function created(Model $model): void
    {
        /** @var Model&\App\Packages\Elasticsearch\Traits\Searchable $model */
        $model->syncToElasticsearch();
    }

    public function updated(Model $model): void
    {
        /** @var Model&\App\Packages\Elasticsearch\Traits\Searchable $model */
        $model->syncToElasticsearch();
    }

    public function deleted(Model $model): void
    {
        /** @var Model&\App\Packages\Elasticsearch\Traits\Searchable $model */
        $model->removeFromElasticsearch();
    }

    /** Soft-delete restored — re-index the document. */
    public function restored(Model $model): void
    {
        /** @var Model&\App\Packages\Elasticsearch\Traits\Searchable $model */
        $model->syncToElasticsearch();
    }

    /** Force-delete always removes from the index, even for soft-delete models. */
    public function forceDeleted(Model $model): void
    {
        /** @var Model&\App\Packages\Elasticsearch\Traits\Searchable $model */
        $model->removeFromElasticsearch();
    }
}
