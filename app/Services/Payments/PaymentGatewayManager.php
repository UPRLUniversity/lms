<?php

namespace App\Services\Payments;

use App\Exceptions\CheckoutException;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves a driver instance for a payment_methods row.
 *
 * The row says "the institution has switched this on"; config/commerce.php says "here
 * is the class that knows how". Both must agree before a method can take money, which
 * is why available() filters on enabled AND has-a-driver AND is-configured — an
 * enabled-but-keyless Paystack row would otherwise be offered at checkout and fail on
 * submit, which is a worse experience than not offering it.
 */
class PaymentGatewayManager
{
    /**
     * @throws CheckoutException
     */
    public function driver(PaymentMethod $method): PaymentGateway
    {
        $class = $method->driverConfig()['class'] ?? null;

        if ($class === null || ! class_exists($class)) {
            throw CheckoutException::unavailableMethod();
        }

        $driver = app($class);

        if (! $driver instanceof PaymentGateway) {
            throw CheckoutException::unavailableMethod();
        }

        return $driver;
    }

    /**
     * Every method that can actually take a payment right now.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function available(): Collection
    {
        return PaymentMethod::query()
            ->enabled()
            ->ordered()
            ->get()
            ->filter(fn (PaymentMethod $method) => $method->hasDriver() && $method->isConfigured())
            ->values();
    }

    /**
     * Resolve a method the buyer chose, refusing anything not currently on offer.
     *
     * @throws CheckoutException
     */
    public function resolveChoice(?string $key): PaymentMethod
    {
        $available = $this->available();

        if ($available->isEmpty()) {
            throw CheckoutException::noPaymentMethod();
        }

        if (blank($key)) {
            return $available->first();
        }

        $method = $available->firstWhere('key', $key);

        if ($method === null) {
            throw CheckoutException::unavailableMethod();
        }

        return $method;
    }

    /**
     * A method by key regardless of whether it is enabled — for the webhook endpoint,
     * which must still verify signatures for a method an admin has just switched off
     * (payments already in flight will keep arriving).
     */
    public function findByKey(string $key): ?PaymentMethod
    {
        return PaymentMethod::query()->where('key', $key)->first();
    }
}
