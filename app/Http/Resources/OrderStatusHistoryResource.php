<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $this->relationLoaded('changedBy') ? $this->changedBy : null;

        return [
            'id' => $this->id,
            'previous_status' => $this->previous_status?->value,
            'new_status' => $this->new_status->value,
            'note' => $this->note,
            // Customers see who acted only as a role; admins also see the name.
            'changed_by' => $actor === null ? null : [
                'role' => $actor->role->value,
                'name' => $this->when((bool) $request->user()?->isAdmin(), $actor->name),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
