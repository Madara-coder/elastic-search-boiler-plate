<?php

declare(strict_types=1);

namespace App\Packages\Elasticsearch\Jobs;

use App\Packages\Elasticsearch\Services\ElasticsearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class UpdateDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    /**
     * @param array<string, mixed> $fields Only the fields to be updated
     */
    public function __construct(
        private readonly string $index,
        private readonly string $id,
        private readonly array  $fields,
    ) {}

    public function handle(ElasticsearchService $service): void
    {
        $service->updateDocument($this->index, $this->id, $this->fields);
    }
}
