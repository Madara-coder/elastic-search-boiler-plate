<?php

declare(strict_types=1);

namespace App\Packages\Elasticsearch\Jobs;

use App\Packages\Elasticsearch\Services\ElasticsearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class IndexDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Maximum number of attempts before the job is marked as failed. */
    public int $tries = 3;

    /** Seconds to wait between retries (exponential back-off handled by the queue driver). */
    public int $backoff = 5;

    /**
     * @param array<string, mixed> $document
     */
    public function __construct(
        private readonly string $index,
        private readonly string $id,
        private readonly array  $document,
    ) {}

    public function handle(ElasticsearchService $service): void
    {
        $service->indexDocument($this->index, $this->id, $this->document);
    }
}
