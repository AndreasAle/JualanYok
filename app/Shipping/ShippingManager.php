<?php

namespace App\Shipping;

use App\Shipping\Contracts\ShippingProvider;
use App\Shipping\Providers\BiteshipShippingProvider;
use App\Shipping\Providers\ManualShippingProvider;
use InvalidArgumentException;

class ShippingManager
{
    public function provider(?string $name = null): ShippingProvider
    {
        $name ??= (string) config('shipping.default', 'manual');

        if (! (bool) config("shipping.providers.{$name}.enabled", false)) {
            throw new InvalidArgumentException("Provider pengiriman [{$name}] belum diaktifkan.");
        }

        return match ($name) {
            'biteship' => app(BiteshipShippingProvider::class),
            'manual' => app(ManualShippingProvider::class),
            default => throw new InvalidArgumentException("Provider pengiriman [{$name}] tidak dikenal."),
        };
    }
}
