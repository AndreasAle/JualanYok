<?php

namespace App\Payments;

use App\Payments\Providers\ManualTransferProvider;
use App\Payments\Providers\MidtransProvider;
use App\Payments\Providers\MockProvider;
use InvalidArgumentException;

/**
 * Resolves payment provider adapters from configuration. Adding a gateway means
 * writing one adapter and registering it here — no other code changes.
 */
class PaymentManager
{
    /** @var array<string, PaymentProviderInterface> */
    private array $resolved = [];

    public function driver(?string $key = null): PaymentProviderInterface
    {
        $key ??= config('payments.default');

        return $this->resolved[$key] ??= $this->build($key);
    }

    private function build(string $key): PaymentProviderInterface
    {
        $config = config("payments.providers.{$key}");

        if (! $config || ! ($config['enabled'] ?? false)) {
            throw new InvalidArgumentException("Payment provider [{$key}] tidak aktif.");
        }

        return match ($key) {
            'mock' => new MockProvider($config['secret'] ?? 'mock-secret'),
            'manual_transfer' => new ManualTransferProvider,
            'midtrans' => new MidtransProvider(
                $config['server_key'] ?? '',
                $config['client_key'] ?? '',
                (bool) ($config['production'] ?? false),
            ),
            default => throw new InvalidArgumentException("Payment provider [{$key}] tidak dikenal."),
        };
    }

    /** Every enabled provider, for rendering the checkout method picker. */
    public function enabled(): array
    {
        return collect(config('payments.providers', []))
            ->filter(fn ($cfg) => $cfg['enabled'] ?? false)
            ->keys()
            ->map(fn ($key) => $this->driver($key))
            ->all();
    }

    /**
     * Flattened list of selectable methods across all enabled providers, ready
     * for the checkout UI.
     */
    public function availableMethods(): array
    {
        $methods = [];

        foreach ($this->enabled() as $provider) {
            foreach ($provider->supportedMethods() as $method) {
                $methods[] = $method + [
                    'provider' => $provider->key(),
                    'provider_name' => $provider->displayName(),
                ];
            }
        }

        return $methods;
    }

    public function findMethod(string $provider, string $method, ?string $channel): ?array
    {
        return collect($this->availableMethods())->first(
            fn ($m) => $m['provider'] === $provider
                && $m['method'] === $method
                && (string) $m['channel'] === (string) $channel
        );
    }
}
