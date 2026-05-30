<?php

declare(strict_types=1);

namespace App\Packages\Elasticsearch\Services;

use App\Packages\Elasticsearch\DTOs\SearchQueryDTO;
use App\Packages\Elasticsearch\DTOs\SearchResultDTO;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Illuminate\Support\Facades\Log;

final class ElasticsearchService
{
    public function __construct(private readonly Client $client) {}

    // -------------------------------------------------------------------------
    // Document CRUD
    // -------------------------------------------------------------------------

    /**
     * Index (create or replace) a document by its ID.
     *
     * @param array<string, mixed> $document
     */
    public function indexDocument(string $index, string $id, array $document): bool
    {
        try {
            $this->client->index([
                'index' => $this->prefixed($index),
                'id'    => $id,
                'body'  => $document,
            ]);

            return true;
        } catch (ClientResponseException | ServerResponseException | MissingParameterException $e) {
            Log::error('ES indexDocument failed', ['index' => $index, 'id' => $id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Apply a partial update to an existing document (only the supplied fields
     * are rewritten; all other fields are preserved by Elasticsearch).
     *
     * @param array<string, mixed> $fields
     */
    public function updateDocument(string $index, string $id, array $fields): bool
    {
        try {
            $this->client->update([
                'index' => $this->prefixed($index),
                'id'    => $id,
                'body'  => ['doc' => $fields],
            ]);

            return true;
        } catch (ClientResponseException | ServerResponseException | MissingParameterException $e) {
            Log::error('ES updateDocument failed', ['index' => $index, 'id' => $id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Remove a document from the index.
     * Returns true even when the document was already absent (idempotent).
     */
    public function deleteDocument(string $index, string $id): bool
    {
        try {
            $this->client->delete([
                'index' => $this->prefixed($index),
                'id'    => $id,
            ]);

            return true;
        } catch (ClientResponseException $e) {
            // 404 means the document is already gone — treat as success
            if ($e->getCode() === 404) {
                return true;
            }

            Log::error('ES deleteDocument failed', ['index' => $index, 'id' => $id, 'error' => $e->getMessage()]);

            return false;
        } catch (ServerResponseException | MissingParameterException $e) {
            Log::error('ES deleteDocument failed', ['index' => $index, 'id' => $id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    /**
     * Execute a structured search built from a SearchQueryDTO.
     * All DSL construction is isolated here; callers only work with the DTO.
     */
    public function search(SearchQueryDTO $dto): SearchResultDTO
    {
        $params = [
            'index' => $this->prefixed($dto->index),
            'body'  => [
                'from'  => $dto->offset(),
                'size'  => $dto->perPage,
                'query' => $this->buildQuery($dto),
                'sort'  => $this->buildSort($dto),
            ],
        ];

        try {
            $response = $this->client->search($params)->asArray();

            return SearchResultDTO::fromResponse($response, $dto->page, $dto->perPage);
        } catch (ClientResponseException | ServerResponseException | MissingParameterException $e) {
            Log::error('ES search failed', ['index' => $dto->index, 'error' => $e->getMessage()]);

            return new SearchResultDTO(0, $dto->page, $dto->perPage, [], 0.0);
        }
    }

    // -------------------------------------------------------------------------
    // Index management
    // -------------------------------------------------------------------------

    /**
     * Create an index using settings/mappings defined in config('elasticsearch.indices').
     */
    public function createIndex(string $index): bool
    {
        try {
            $this->client->indices()->create([
                'index' => $this->prefixed($index),
                'body'  => (array) config("elasticsearch.indices.{$index}", []),
            ]);

            return true;
        } catch (ClientResponseException | ServerResponseException | MissingParameterException $e) {
            Log::error('ES createIndex failed', ['index' => $index, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Drop an entire index and all of its documents. */
    public function deleteIndex(string $index): bool
    {
        try {
            $this->client->indices()->delete(['index' => $this->prefixed($index)]);

            return true;
        } catch (ClientResponseException | ServerResponseException | MissingParameterException $e) {
            Log::error('ES deleteIndex failed', ['index' => $index, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Returns true when the prefixed index already exists in the cluster. */
    public function indexExists(string $index): bool
    {
        try {
            $response = $this->client->indices()->exists(['index' => $this->prefixed($index)]);

            return $response->getStatusCode() === 200;
        } catch (ClientResponseException $e) {
            if ($e->getCode() === 404) {
                return false;
            }

            throw $e;
        } catch (ServerResponseException | MissingParameterException) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // DSL builders (private — callers only interact with DTOs)
    // -------------------------------------------------------------------------

    /**
     * Translate a SearchQueryDTO into a bool query.
     *
     * DSL shape produced:
     *   bool:
     *     must:   [ multi_match ]          — scores hits by relevance
     *     filter: [ term | terms | range ] — zero-scoring hard constraints
     *
     * Falls back to match_all when the DTO carries no constraints.
     *
     * @return array<string, mixed>
     */
    private function buildQuery(SearchQueryDTO $dto): array
    {
        $must   = [];
        $filter = [];

        if ($dto->query !== '') {
            $must[] = [
                'multi_match' => [
                    'query'     => $dto->query,
                    'fields'    => $dto->fields,
                    'type'      => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        foreach ($dto->filters as $field => $value) {
            $filter[] = is_array($value)
                ? ['terms' => [$field => $value]]
                : ['term'  => [$field => $value]];
        }

        foreach ($dto->ranges as $field => $bounds) {
            $range = array_filter([
                'gte' => $bounds['from'] ?? null,
                'lte' => $bounds['to']   ?? null,
            ], static fn (mixed $v): bool => $v !== null);

            if ($range !== []) {
                $filter[] = ['range' => [$field => $range]];
            }
        }

        if ($must === [] && $filter === []) {
            return ['match_all' => (object) []];
        }

        $bool = [];

        if ($must !== []) {
            $bool['must'] = $must;
        }

        if ($filter !== []) {
            $bool['filter'] = $filter;
        }

        return ['bool' => $bool];
    }

    /**
     * Build the sort array. Defaults to relevance-first when no sort is requested.
     *
     * @return list<array<string, mixed>>
     */
    private function buildSort(SearchQueryDTO $dto): array
    {
        if ($dto->sort === []) {
            return [['_score' => ['order' => 'desc']]];
        }

        return array_map(
            static fn (string $field, string $dir): array => [$field => ['order' => $dir]],
            array_keys($dto->sort),
            array_values($dto->sort),
        );
    }

    /** Apply the configured index prefix. */
    private function prefixed(string $index): string
    {
        $prefix = (string) config('elasticsearch.index_prefix', '');

        return $prefix !== '' ? "{$prefix}_{$index}" : $index;
    }
}
