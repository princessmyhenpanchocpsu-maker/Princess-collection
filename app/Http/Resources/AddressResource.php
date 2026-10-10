<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
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
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'address_line' => $this->address_line,
            'barangay' => $this->barangay,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'delivery_instructions' => $this->delivery_instructions,
            'is_default' => $this->is_default,
        ];
    }
}
