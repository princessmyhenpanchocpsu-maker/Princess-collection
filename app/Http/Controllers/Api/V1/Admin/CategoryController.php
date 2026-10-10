<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\ImageStorage;
use App\Support\Slugger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Category::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return CategoryResource::collection(
            Category::query()
                ->withCount('products')
                ->when($filters['search'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
                ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', (bool) $filters['is_active']))
                ->orderBy('name')
                ->paginate($filters['per_page'] ?? 20)
        );
    }

    public function store(CategoryRequest $request, ImageStorage $images): JsonResponse
    {
        $data = $request->validated();

        $category = Category::create([
            ...Arr::only($data, ['name', 'description', 'is_active']),
            'slug' => $data['slug'] ?? Slugger::unique(Category::class, $data['name']),
            'image_path' => $request->hasFile('image') ? $images->store($request->file('image'), 'categories') : null,
        ]);

        return CategoryResource::make($category)->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        Gate::authorize('view', $category);

        return CategoryResource::make($category->loadCount('products'));
    }

    public function update(CategoryRequest $request, Category $category, ImageStorage $images): CategoryResource
    {
        $data = $request->validated();

        $category->fill(Arr::only($data, ['name', 'description', 'is_active']));

        if (filled($data['slug'] ?? null)) {
            $category->slug = $data['slug'];
        }

        if ($request->hasFile('image')) {
            $previous = $category->image_path;
            $category->image_path = $images->store($request->file('image'), 'categories');
            $images->delete($previous);
        }

        $category->save();

        return CategoryResource::make($category->loadCount('products'));
    }

    /**
     * Archive rather than delete: the category and its products disappear from the storefront
     * but stay in the database, and can be restored by setting it active again.
     */
    public function destroy(Category $category): CategoryResource
    {
        Gate::authorize('delete', $category);

        $category->update(['is_active' => false]);

        return CategoryResource::make($category);
    }
}
