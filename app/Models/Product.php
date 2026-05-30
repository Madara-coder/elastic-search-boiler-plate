<?php

declare(strict_types=1);

namespace App\Models;

use App\Packages\Elasticsearch\Contracts\SearchableContract;
use App\Packages\Elasticsearch\Traits\Searchable;
use Illuminate\Database\Eloquent\Model;

/**
 * Example model showing how to wire up the Searchable trait.
 *
 * All you need to do:
 *   1. implement SearchableContract
 *   2. use Searchable
 *   3. override toSearchableArray() to control which fields are indexed
 */
final class Product extends Model implements SearchableContract
{
    use Searchable;

    protected $fillable = [
        'name',
        'description',
        'price',
        'category',
        'status',
    ];

    protected $casts = [
        'price' => 'float',
    ];

    // -------------------------------------------------------------------------
    // SearchableContract implementation
    // -------------------------------------------------------------------------

    public function searchableIndex(): string
    {
        return 'products';
    }

    public function searchableId(): string
    {
        return (string) $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id'          => (string) $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => $this->price,
            'category'    => $this->category,
            'status'      => $this->status,
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
