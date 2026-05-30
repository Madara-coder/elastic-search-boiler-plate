<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates incoming search parameters before they reach the controller.
 * The shape mirrors SearchQueryDTO::fromArray() exactly so no extra mapping
 * is needed beyond passing $request->validated() to the DTO factory.
 */
final class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'query'          => ['nullable', 'string', 'max:255'],

            // Exact-match filters  e.g. ?filters[status]=active
            'filters'        => ['nullable', 'array'],
            'filters.*'      => ['nullable'],

            // Range filters  e.g. ?ranges[price][from]=10&ranges[price][to]=500
            'ranges'         => ['nullable', 'array'],
            'ranges.*.from'  => ['nullable', 'numeric'],
            'ranges.*.to'    => ['nullable', 'numeric'],

            // Sort  e.g. ?sort[price]=asc&sort[created_at]=desc
            'sort'           => ['nullable', 'array'],
            'sort.*'         => ['nullable', 'string', 'in:asc,desc'],

            // Pagination
            'page'           => ['nullable', 'integer', 'min:1'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
