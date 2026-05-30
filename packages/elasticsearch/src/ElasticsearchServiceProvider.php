<?php

declare(strict_types=1);

namespace App\Packages\Elasticsearch;

use App\Packages\Elasticsearch\Console\IndexManageCommand;
use App\Packages\Elasticsearch\Services\ElasticsearchService;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;

final class ElasticsearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/elasticsearch.php', 'elasticsearch');

        $this->app->singleton(Client::class, fn (): Client => $this->buildClient());

        $this->app->singleton(ElasticsearchService::class, ElasticsearchService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/elasticsearch.php' => config_path('elasticsearch.php'),
            ], 'elasticsearch-config');

            $this->commands([IndexManageCommand::class]);
        }
    }

    private function buildClient(): Client
    {
        $builder = ClientBuilder::create();

        if ($cloudId = config('elasticsearch.cloud_id')) {
            $builder->setElasticCloudId((string) $cloudId);
        } else {
            $builder->setHosts((array) config('elasticsearch.hosts', []));
        }

        match (config('elasticsearch.auth.method')) {
            'api_key' => $builder->setApiKey((string) config('elasticsearch.auth.api_key')),
            'basic'   => $builder->setBasicAuthentication(
                (string) config('elasticsearch.auth.username'),
                (string) config('elasticsearch.auth.password'),
            ),
            default => null,
        };

        if (!(bool) config('elasticsearch.ssl.verify', true)) {
            $builder->setSSLVerification(false);
        } elseif ($caBundle = config('elasticsearch.ssl.ca_bundle')) {
            $builder->setCABundle((string) $caBundle);
        }

        return $builder->build();
    }
}
