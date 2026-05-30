<?php

declare(strict_types=1);

namespace App\Packages\Elasticsearch\DTOs;

/**
 * Carries all user-supplied search parameters in a typed value object so
 * controllers never touch raw Elasticsearch DSL.
 */
final class SearchQueryDTO
{
    /**
     * @param string                                         $index   Logical index name (no prefix)
     * @param string                                         $query   Full-text search string
     * @param array<string, mixed>                           $filters Exact-match filters: ['status' => 'active'] or ['status' => ['active','pending']]
     * @param array<string, array{from?: mixed, to?: mixed}> $ranges  Range filters: ['price' => ['from' => 10, 'to' => 500]]
     * @param array<string, 'asc'|'desc'>                    $sort    Sort fields: ['price' => 'asc', 'created_at' => 'desc']
     * @param list<string>                                   $fields  Full-text fields; ['*'] searches all mapped fields
     * @param int                                            $page    1-based current page
     * @param int                                            $perPage Results per page (capped at 100)
     */
    public function __construct(
        public readonly string $index,
        public readonly string $query = '',
        public readonly array  $filters = [],
        public readonly array  $ranges = [],
        public readonly array  $sort = [],
        public readonly array  $fields = ['*'],
        public readonly int    $page = 1,
        public readonly int    $perPage = 15,
    ) {}

    /**
     * Convenience factory: builds a DTO from a plain associative array
     * (e.g. directly from a validated FormRequest).
     *
     * @param array<string, mixed> $params
     */
    public static function fromArray(string $index, array $params): self
    {
        return new self(
            index:   $index,
            query:   (string) ($params['query'] ?? ''),
            filters: (array)  ($params['filters'] ?? []),
            ranges:  (array)  ($params['ranges'] ?? []),
            sort:    (array)  ($params['sort'] ?? []),
            fields:  (array)  ($params['fields'] ?? ['*']),
            page:    max(1, (int) ($params['page'] ?? 1)),
            perPage: min(100, max(1, (int) ($params['per_page'] ?? 15))),
        );
    }

    /** Zero-based offset for Elasticsearch `from` parameter. */
    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
