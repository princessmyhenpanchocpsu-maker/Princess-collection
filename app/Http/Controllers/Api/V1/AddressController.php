<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Address::class);

        return AddressResource::collection(
            $request->user()->addresses()->orderByDesc('is_default')->latest()->orderByDesc('id')->get()
        );
    }

    public function store(AddressRequest $request): JsonResponse
    {
        $user = $request->user();

        $address = DB::transaction(function () use ($request, $user) {
            // The first saved address is always the default.
            $makeDefault = $request->boolean('is_default') || ! $user->addresses()->exists();

            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create([...$request->validated(), 'is_default' => $makeDefault]);
        });

        return AddressResource::make($address)->response()->setStatusCode(201);
    }

    public function show(Address $address): AddressResource
    {
        Gate::authorize('view', $address);

        return AddressResource::make($address);
    }

    public function update(AddressRequest $request, Address $address): AddressResource
    {
        DB::transaction(function () use ($request, $address) {
            $data = $request->validated();

            if ($request->boolean('is_default')) {
                $address->user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            } else {
                // An address cannot un-default itself; choose another default instead.
                unset($data['is_default']);
            }

            $address->update($data);
        });

        return AddressResource::make($address);
    }

    public function destroy(Address $address): Response
    {
        Gate::authorize('delete', $address);

        DB::transaction(function () use ($address) {
            $address->delete();

            if ($address->is_default) {
                $address->user->addresses()->latest()->orderByDesc('id')->first()?->update(['is_default' => true]);
            }
        });

        return response()->noContent();
    }
}
