<?php

namespace App\Services;

use App\Models\Setting;

class StoreSettings
{
    /** @var array<string, string|null>|null */
    private ?array $resolved = null;

    /**
     * Every setting, with saved values layered over the configured defaults.
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return $this->resolved ??= array_merge(
            config('store.settings'),
            Setting::whereIn('key', array_keys(config('store.settings')))->pluck('value', 'key')->all(),
        );
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function update(array $values): void
    {
        foreach (array_intersect_key($values, config('store.settings')) as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->resolved = null;
    }

    /**
     * Shipping fee for an order subtotal, as a decimal string.
     */
    public function shippingFeeFor(string $subtotal): string
    {
        $threshold = $this->get('free_shipping_threshold');

        if ($threshold !== null && $threshold !== '' && bccomp($subtotal, $threshold, 2) >= 0) {
            return '0.00';
        }

        return bcadd($this->get('shipping_fee') ?? '0', '0', 2);
    }
}
