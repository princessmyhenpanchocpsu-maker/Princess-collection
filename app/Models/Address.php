<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    /**
     * `user_id` is not fillable: addresses are always created through the owner's relationship.
     */
    protected $fillable = [
        'recipient_name',
        'phone',
        'address_line',
        'barangay',
        'city',
        'province',
        'postal_code',
        'delivery_instructions',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
