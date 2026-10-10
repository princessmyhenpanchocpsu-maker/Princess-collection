<?php

namespace Database\Seeders;

use App\Enums\InventoryMovementType;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DEMO DATA. Seeds the starting categories and eight sample products so the store
 * is browsable. Images are stock photos from Unsplash (see database/seeders/demo-images/CREDITS.md),
 * not photos of real stock; a drawn placeholder is used for any product without one.
 * Safe to re-run: existing categories and products are left untouched.
 */
class DemoCatalogSeeder extends Seeder
{
    private const COLORS = [
        'Rose' => '#D98CA6', 'Blush' => '#F6DCE5', 'Cream' => '#F3E9D8', 'White' => '#FFFFFF',
        'Black' => '#2B2B2B', 'Beige' => '#D9C3A5', 'Olive' => '#7A8450', 'Sage' => '#A9BA9D',
        'Mocha' => '#7B5B4C', 'Lilac' => '#C8B6E2', 'Denim Blue' => '#5B7FA6',
    ];

    private const BASE_STOCK = ['XS' => 6, 'S' => 12, 'M' => 15, 'L' => 10, 'XL' => 4];

    private const CATEGORIES = [
        'Tops and Blouses' => 'Everyday tops and polished blouses for work and weekends.',
        'Pants' => 'Comfortable trousers and wide-leg pants that move with you.',
        'Shorts' => 'Easy shorts for warm days and casual outings.',
        'Dresses' => 'Dresses for sunny afternoons and special evenings.',
        'Crop Tops' => 'Cropped silhouettes to pair with your favourite high-waist pieces.',
    ];

    private const PRODUCTS = [
        ['Rose Knit Crop Top', 'Crop Tops', '449.00', ['Rose', 'Cream'], 'A soft ribbed-knit crop top with a flattering fitted shape. Pairs well with high-waist shorts or pants.', ['Cream-XL']],
        ['Classic White Blouse', 'Tops and Blouses', '699.00', ['White', 'Blush'], 'A crisp button-down blouse with a relaxed collar. Lightweight, breathable, and easy to dress up or down.', []],
        ['High-Waist Denim Shorts', 'Shorts', '599.00', ['Denim Blue', 'Black'], 'Classic high-waist denim shorts with a slightly frayed hem and a comfortable stretch fit.', ['Black-XS']],
        ['Wide-Leg Casual Pants', 'Pants', '899.00', ['Beige', 'Black', 'Olive'], 'Flowy wide-leg pants with an elastic back waistband and side pockets for all-day comfort.', []],
        ['Floral Summer Dress', 'Dresses', '1099.00', ['Blush', 'Sage'], 'A breezy floral dress with adjustable straps and a tiered skirt. Made for warm, sunny days.', ['Blush-XS', 'Sage-XL']],
        ['Everyday Fitted Top', 'Tops and Blouses', '349.00', ['White', 'Black', 'Rose'], 'A smooth, stretchy fitted top that layers under anything. A wardrobe staple in three colours.', []],
        ['Elegant Midi Dress', 'Dresses', '1499.00', ['Black', 'Mocha'], 'A satin-finish midi dress with a wrap front and a soft drape. An easy choice for dinners and events.', ['Mocha-L']],
        ['Basic Ribbed Crop Top', 'Crop Tops', '299.00', ['White', 'Black', 'Lilac'], 'A simple ribbed crop top with a round neckline. Soft, stretchy, and made for everyday wear.', []],
    ];

    /**
     * Stock photos per product: [file in demo-images/, colour the photo actually shows or null].
     */
    private const PHOTOS = [
        'rose-knit-crop-top' => [['rose-knit-crop-top-1', 'Cream'], ['rose-knit-crop-top-2', 'Cream']],
        'classic-white-blouse' => [['classic-white-blouse-1', 'White'], ['classic-white-blouse-2', 'White']],
        'high-waist-denim-shorts' => [['high-waist-denim-shorts-1', 'Denim Blue'], ['high-waist-denim-shorts-2', 'Denim Blue']],
        'wide-leg-casual-pants' => [['wide-leg-casual-pants-1', null]],
        'floral-summer-dress' => [['floral-summer-dress-1', null], ['floral-summer-dress-2', null]],
        'everyday-fitted-top' => [['everyday-fitted-top-1', 'Black'], ['everyday-fitted-top-2', 'White']],
        'elegant-midi-dress' => [['elegant-midi-dress-1', 'Black']],
        'basic-ribbed-crop-top' => [['basic-ribbed-crop-top-1', 'White']],
    ];

    private const CATEGORY_PHOTOS = [
        'tops-and-blouses' => 'classic-white-blouse-1',
        'pants' => 'wide-leg-casual-pants-1',
        'shorts' => 'high-waist-denim-shorts-1',
        'dresses' => 'elegant-midi-dress-1',
        'crop-tops' => 'basic-ribbed-crop-top-1',
    ];

    public function run(InventoryService $inventory): void
    {
        $categories = [];

        foreach (self::CATEGORIES as $name => $description) {
            $slug = Str::slug($name);
            $category = Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description]);

            // Only touch the image while it is still a demo one, never an admin's upload.
            if ($category->image_path === null || str_starts_with($category->image_path, 'categories/demo/')) {
                $category->update([
                    'image_path' => $this->photo(self::CATEGORY_PHOTOS[$slug] ?? '', "categories/demo/{$slug}.jpg")
                        ?? $this->placeholder("categories/demo/{$slug}.svg", $name, 'Collection', '#F6DCE5'),
                ]);
            }

            $categories[$name] = $category;
        }

        foreach (self::PRODUCTS as $index => [$name, $category, $price, $colors, $description, $soldOut]) {
            $slug = Str::slug($name);

            if ($existing = Product::withTrashed()->where('slug', $slug)->first()) {
                $this->syncImages($existing, $colors);

                continue;
            }

            $product = Product::create([
                'category_id' => $categories[$category]->id,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'base_price' => $price,
                'status' => ProductStatus::Published,
            ]);

            // Stagger timestamps so "new arrivals" has a stable order.
            $product->forceFill(['created_at' => now()->subDays(count(self::PRODUCTS) - $index)])->save();

            $prefix = Str::upper(implode('', array_map(fn ($word) => $word[0], explode('-', $slug))));

            $this->syncImages($product, $colors);

            foreach ($colors as $colorIndex => $color) {
                foreach (array_keys(self::BASE_STOCK) as $sizeIndex => $size) {
                    $variant = $product->variants()->create([
                        'sku' => $prefix.'-'.$size.'-'.Str::upper(Str::slug($color, '')),
                        'size' => $size,
                        'color' => $color,
                        // One priced-up variant so the storefront's price range display has real data behind it.
                        'price_override' => ($name === 'Elegant Midi Dress' && $size === 'XL') ? '1599.00' : null,
                    ]);

                    $stock = in_array("{$color}-{$size}", $soldOut, true)
                        ? 0
                        : max(1, self::BASE_STOCK[$size] - (($index * 3 + $colorIndex * 5 + $sizeIndex * 2) % 7));

                    if ($stock > 0) {
                        $inventory->adjust($variant, $stock, InventoryMovementType::StockIn, null, 'Demo data: opening stock');
                    }
                }
            }
        }
    }

    /**
     * Give a demo product its stock photos, or drawn placeholders when it has none.
     * Does nothing once an admin has uploaded images of their own.
     *
     * @param  list<string>  $colors
     */
    private function syncImages(Product $product, array $colors): void
    {
        $current = $product->images()->get();

        if ($current->contains(fn ($image) => ! str_starts_with($image->image_path, 'products/demo/'))) {
            return;
        }

        $wanted = [];

        foreach (self::PHOTOS[$product->slug] ?? [] as [$file, $color]) {
            if ($path = $this->photo($file, "products/demo/{$file}.jpg")) {
                $wanted[] = ['image_path' => $path, 'alt_text' => $color ? "{$product->name} in {$color}" : $product->name];
            }
        }

        if ($wanted === []) {
            foreach ($colors as $color) {
                $wanted[] = [
                    'image_path' => $this->placeholder("products/demo/{$product->slug}-".Str::slug($color).'.svg', $product->name, $color, self::COLORS[$color]),
                    'alt_text' => "{$product->name} in {$color} (demo placeholder image)",
                ];
            }
        }

        if ($current->pluck('image_path')->all() === array_column($wanted, 'image_path')) {
            return;
        }

        Storage::disk('public')->delete($current->pluck('image_path')->diff(array_column($wanted, 'image_path'))->all());
        $product->images()->delete();

        foreach ($wanted as $order => $image) {
            $product->images()->create([...$image, 'sort_order' => $order]);
        }
    }

    /**
     * Copy a bundled stock photo to the public disk. Returns null when the file is not bundled.
     */
    private function photo(string $file, string $path): ?string
    {
        $source = __DIR__."/demo-images/{$file}.jpg";

        if ($file === '' || ! is_file($source)) {
            return null;
        }

        Storage::disk('public')->put($path, file_get_contents($source));

        return $path;
    }

    /**
     * Write a simple labelled SVG to the public disk and return its path.
     */
    private function placeholder(string $path, string $title, string $subtitle, string $swatch): string
    {
        $title = e($title);
        $subtitle = e($subtitle);

        Storage::disk('public')->put($path, <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1000" role="img" aria-label="{$title} — {$subtitle} (demo placeholder)">
              <rect width="800" height="1000" fill="#FFF8F0"/>
              <rect x="40" y="40" width="720" height="920" rx="36" fill="#FFFFFF" stroke="#F6DCE5" stroke-width="2"/>
              <circle cx="400" cy="400" r="190" fill="{$swatch}" stroke="#6E514A" stroke-opacity="0.25" stroke-width="2"/>
              <path d="M400 318a26 26 0 1 1 26 26c-14 0-26 8-26 22v10l150 86c14 8 8 30-8 30H258c-16 0-22-22-8-30l150-86" fill="none" stroke="#6E514A" stroke-opacity="0.55" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
              <text x="400" y="700" text-anchor="middle" font-family="Georgia, 'Times New Roman', serif" font-size="46" fill="#6E514A">{$title}</text>
              <text x="400" y="760" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="28" fill="#6E514A" fill-opacity="0.75">{$subtitle}</text>
              <text x="400" y="900" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="20" letter-spacing="6" fill="#6E514A" fill-opacity="0.5">DEMO IMAGE</text>
            </svg>
            SVG);

        return $path;
    }
}
