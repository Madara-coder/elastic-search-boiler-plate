<?php

declare(strict_types=1);

namespace App\Packages\Elasticsearch\Jobs;

use App\Packages\Elasticsearch\Services\ElasticsearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DeleteDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        private readonly string $index,
        private readonly string $id,
    ) {}

    public function handle(ElasticsearchService $service): void
    {
        $service->deleteDocument($this->index, $this->id);
    }
}
