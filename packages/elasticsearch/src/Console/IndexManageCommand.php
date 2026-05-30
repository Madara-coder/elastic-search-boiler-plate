<?php

declare(strict_types=1);

namespace App\Packages\Elasticsearch\Console;

use App\Packages\Elasticsearch\Services\ElasticsearchService;
use Illuminate\Console\Command;

/**
 * Usage:
 *   php artisan elasticsearch:index create   products
 *   php artisan elasticsearch:index delete   products
 *   php artisan elasticsearch:index recreate products
 */
final class IndexManageCommand extends Command
{
    protected $signature = 'elasticsearch:index
                            {action : create | delete | recreate}
                            {index  : Logical index name as defined in config/elasticsearch.php}';

    protected $description = 'Manage Elasticsearch indices (create, delete, recreate)';

    public function handle(ElasticsearchService $service): int
    {
        return match ($this->argument('action')) {
            'create'   => $this->handleCreate($service),
            'delete'   => $this->handleDelete($service),
            'recreate' => $this->handleRecreate($service),
            default    => $this->invalidAction(),
        };
    }

    private function handleCreate(ElasticsearchService $service): int
    {
        $index = (string) $this->argument('index');

        if ($service->indexExists($index)) {
            $this->warn("Index [{$index}] already exists. Use 'recreate' to rebuild it.");

            return self::FAILURE;
        }

        if ($service->createIndex($index)) {
            $this->info("Index [{$index}] created successfully.");

            return self::SUCCESS;
        }

        $this->error("Failed to create index [{$index}]. Check your application logs.");

        return self::FAILURE;
    }

    private function handleDelete(ElasticsearchService $service): int
    {
        $index = (string) $this->argument('index');

        if (!$this->confirm("Delete index [{$index}]? All documents will be permanently lost.")) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        if ($service->deleteIndex($index)) {
            $this->info("Index [{$index}] deleted.");

            return self::SUCCESS;
        }

        $this->error("Failed to delete index [{$index}]. Check your application logs.");

        return self::FAILURE;
    }

    private function handleRecreate(ElasticsearchService $service): int
    {
        $index = (string) $this->argument('index');

        if (!$this->confirm("Recreate index [{$index}]? All existing documents will be lost.")) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        if ($service->indexExists($index)) {
            $service->deleteIndex($index);
            $this->line("Dropped existing index [{$index}].");
        }

        if ($service->createIndex($index)) {
            $this->info("Index [{$index}] recreated successfully.");

            return self::SUCCESS;
        }

        $this->error("Failed to recreate index [{$index}]. Check your application logs.");

        return self::FAILURE;
    }

    private function invalidAction(): int
    {
        $this->error("Unknown action [{$this->argument('action')}]. Use: create, delete, or recreate.");

        return self::FAILURE;
    }
}
