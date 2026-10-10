<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'orders_count' => (int) ($this->orders_count ?? 0),
            // Total of non-cancelled orders.
            'total_spent' => bcadd((string) ($this->total_spent ?? '0'), '0', 2),
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'joined_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
