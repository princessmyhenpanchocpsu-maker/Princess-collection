<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'base_price',
        'status',
    ];

    protected $attributes = [
        'status' => ProductStatus::Draft->value,
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'status' => ProductStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The gallery photo for a colour (matched on its alt text), falling back to the main photo.
     * Expects the images relation to be loaded.
     */
    public function imageForColor(?string $color): ?ProductImage
    {
        return $this->images->first(fn (ProductImage $image) => $color !== null && str_contains(mb_strtolower((string) $image->alt_text), ' in '.mb_strtolower($color)))
            ?? $this->images->first();
    }

    /**
     * Products customers are allowed to see and buy.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Published);
    }
}
