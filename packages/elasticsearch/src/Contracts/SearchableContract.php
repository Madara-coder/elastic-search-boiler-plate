<?php

declare(strict_types=1);

namespace App\Packages\Elasticsearch\Contracts;

interface SearchableContract
{
    /**
     * The Elasticsearch index that stores documents for this model.
     */
    public function searchableIndex(): string;

    /**
     * The unique document ID used in Elasticsearch.
     */
    public function searchableId(): string;

    /**
     * The field map that is stored as the Elasticsearch document body.
     *
     * Only include fields that should be searchable or filterable —
     * avoid large blobs or sensitive columns.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array;
}
