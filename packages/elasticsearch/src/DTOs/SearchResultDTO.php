<?php

declare(strict_types=1);

namespace App\Packages\Elasticsearch\DTOs;

/**
 * Wraps the raw Elasticsearch response into a typed, paginated value object.
 * Callers only interact with this — never with raw response arrays.
 */
final class SearchResultDTO
{
    /**
     * @param int                        $total   Total matching documents in the index
     * @param int                        $page    Current page number (1-based)
     * @param int                        $perPage Page size
     * @param list<array<string, mixed>> $hits    _source contents merged with _id and _score
     * @param float                      $maxScore Relevance score of the top-ranked hit
     * @param array<string, mixed>       $raw     Full Elasticsearch response for advanced consumers
     */
    public function __construct(
        public readonly int   $total,
        public readonly int   $page,
        public readonly int   $perPage,
        public readonly array $hits,
        public readonly float $maxScore,
        public readonly array $raw = [],
    ) {}

    /**
     * Hydrate from the array returned by `$client->search()->asArray()`.
     */
    public static function fromResponse(array $response, int $page, int $perPage): self
    {
        $hitsWrapper = $response['hits'] ?? [];
        $total       = (int) ($hitsWrapper['total']['value'] ?? 0);
        $maxScore    = (float) ($hitsWrapper['max_score'] ?? 0.0);

        $hits = array_map(
            static fn (array $hit): array => array_merge(
                $hit['_source'] ?? [],
                ['_id' => $hit['_id'] ?? null, '_score' => $hit['_score'] ?? null],
            ),
            $hitsWrapper['hits'] ?? [],
        );

        return new self(
            total:    $total,
            page:     $page,
            perPage:  $perPage,
            hits:     $hits,
            maxScore: $maxScore,
            raw:      $response,
        );
    }

    public function lastPage(): int
    {
        if ($this->perPage === 0) {
            return 1;
        }

        return (int) ceil($this->total / $this->perPage);
    }

    public function hasMorePages(): bool
    {
        return $this->page < $this->lastPage();
    }

    /**
     * Returns a structure compatible with Laravel's standard pagination envelope.
     *
     * @return array{
     *   data: list<array<string, mixed>>,
     *   meta: array{
     *     current_page: int,
     *     per_page: int,
     *     total: int,
     *     last_page: int,
     *     from: int|null,
     *     to: int|null
     *   }
     * }
     */
    public function toPaginatedArray(): array
    {
        $from = $this->total === 0 ? null : (($this->page - 1) * $this->perPage) + 1;
        $to   = $this->total === 0 ? null : min($this->page * $this->perPage, $this->total);

        return [
            'data' => $this->hits,
            'meta' => [
                'current_page' => $this->page,
                'per_page'     => $this->perPage,
                'total'        => $this->total,
                'last_page'    => $this->lastPage(),
                'from'         => $from,
                'to'           => $to,
            ],
        ];
    }
}
