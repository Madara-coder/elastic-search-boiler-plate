<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Packages\Elasticsearch\DTOs\SearchQueryDTO;
use App\Packages\Elasticsearch\Services\ElasticsearchService;
use Illuminate\Http\JsonResponse;

/**
 * Single-action controller for the product search endpoint.
 *
 * Route example (routes/api.php):
 *   Route::get('/products/search', ProductSearchController::class);
 *
 * Example request:
 *   GET /api/products/search
 *       ?query=wireless+headphones
 *       &filters[status]=active
 *       &filters[category][]=electronics&filters[category][]=audio
 *       &ranges[price][from]=50&ranges[price][to]=300
 *       &sort[price]=asc
 *       &page=1
 *       &per_page=20
 */
final class ProductSearchController extends Controller
{
    public function __construct(private readonly ElasticsearchService $search) {}

    public function __invoke(SearchRequest $request): JsonResponse
    {
        $dto = SearchQueryDTO::fromArray('products', [
            'query'    => $request->string('query')->trim()->value(),
            'filters'  => $request->array('filters'),
            'ranges'   => $request->array('ranges'),
            'sort'     => $request->array('sort'),
            'fields'   => ['name', 'description'],   // restrict full-text to these fields
            'page'     => $request->integer('page', 1),
            'per_page' => $request->integer('per_page', 15),
        ]);

        $result = $this->search->search($dto);

        return response()->json($result->toPaginatedArray());
    }
}
