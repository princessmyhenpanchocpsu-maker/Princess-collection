<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CatalogService
{
    // Lowest and highest price a customer can pay across a product's active variants.
    private const PRICE_SQL = '(select %s(coalesce(pv.price_override, products.base_price)) from product_variants pv where pv.product_id = products.id and pv.is_active = 1)';

    private const UNITS_SOLD_SQL = '(select coalesce(sum(oi.quantity), 0) from order_items oi join orders o on o.id = oi.order_id where oi.product_id = products.id and o.order_status <> ?)';

    /**
     * Products customers may see: published, in an active category, with listing data attached.
     */
    public function visibleProducts(): Builder
    {
        return Product::query()
            ->published()
            ->whereHas('category', fn (Builder $q) => $q->active())
            ->select('products.*')
            ->selectRaw('coalesce('.sprintf(self::PRICE_SQL, 'min').', products.base_price) as min_price')
            ->selectRaw('coalesce('.sprintf(self::PRICE_SQL, 'max').', products.base_price) as max_price')
            ->selectRaw('(select coalesce(sum(pv.stock_quantity), 0) from product_variants pv where pv.product_id = products.id and pv.is_active = 1) as total_stock')
            ->with([
                'category',
                'images',
                'variants' => fn ($q) => $q->active(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters  validated ProductIndexRequest data
     */
    public function paginate(array $filters, ?Category $category = null): LengthAwarePaginator
    {
        $query = $this->visibleProducts();

        if ($category) {
            $query->where('category_id', $category->id);
        } elseif (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $filters['category']));
        }

        if (! empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('products.name', 'like', $term)->orWhere('products.description', 'like', $term));
        }

        // Size, colour and availability must all be satisfied by the same variant.
        $size = $filters['size'] ?? null;
        $color = $filters['color'] ?? null;
        $inStock = ! empty($filters['in_stock']);

        if ($size || $color || $inStock) {
            $query->whereHas('variants', function (Builder $q) use ($size, $color, $inStock) {
                $q->active()
                    ->when($size, fn (Builder $q) => $q->where('size', $size))
                    ->when($color, fn (Builder $q) => $q->where('color', $color))
                    ->when($inStock, fn (Builder $q) => $q->where('stock_quantity', '>', 0));
            });
        }

        $minPrice = 'coalesce('.sprintf(self::PRICE_SQL, 'min').', products.base_price)';

        if (isset($filters['min_price'])) {
            $query->whereRaw("$minPrice >= ?", [$filters['min_price']]);
        }

        if (isset($filters['max_price'])) {
            $query->whereRaw("$minPrice <= ?", [$filters['max_price']]);
        }

        if (! empty($filters['has_sales'])) {
            $query->whereRaw(self::UNITS_SOLD_SQL.' > 0', [OrderStatus::Cancelled->value]);
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('min_price')->orderBy('products.id'),
            'price_desc' => $query->orderByDesc('min_price')->orderBy('products.id'),
            'name' => $query->orderBy('products.name')->orderBy('products.id'),
            'bestselling' => $query->orderByRaw(self::UNITS_SOLD_SQL.' desc', [OrderStatus::Cancelled->value])->orderByDesc('products.id'),
            default => $query->orderByDesc('products.created_at')->orderByDesc('products.id'),
        };

        return $query->paginate($filters['per_page'] ?? 12)->withQueryString();
    }

    /**
     * @return Collection<int, Product>
     */
    public function related(Product $product, int $limit = 4): Collection
    {
        return $this->visibleProducts()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->latest('products.created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * The sizes, colours and price bounds that exist among visible products, for the filter panel.
     *
     * @return array{sizes: list<string>, colors: list<string>, price: array{min: string|null, max: string|null}}
     */
    public function filterOptions(): array
    {
        $variants = ProductVariant::query()
            ->active()
            ->whereHas('product', fn (Builder $q) => $q->published()->whereHas('category', fn (Builder $c) => $c->active()));

        $order = config('store.sizes');
        $sizes = (clone $variants)->distinct()->pluck('size')
            ->sortBy(fn (string $size) => ($i = array_search($size, $order, true)) === false ? PHP_INT_MAX : $i)
            ->values()->all();

        $bounds = DB::query()->fromSub($this->visibleProducts()->toBase(), 'p')
            ->selectRaw('min(min_price) as min, max(min_price) as max')
            ->first();

        return [
            'sizes' => $sizes,
            'colors' => (clone $variants)->distinct()->orderBy('color')->pluck('color')->all(),
            'price' => ['min' => $bounds?->min, 'max' => $bounds?->max],
        ];
    }
}
