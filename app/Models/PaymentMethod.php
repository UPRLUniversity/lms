<?php

namespace App\Models;

use App\Casts\RichHtml;
use App\Enums\PaymentEnvironment;
use App\Models\Concerns\LogsAuditActivity;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-managed gateway configuration. The row's `key` names a driver declared in
 * config/commerce.php; PaymentGatewayManager resolves the class from there.
 *
 * `config` holds credentials and is cast ENCRYPTED, so secret keys are never at rest
 * in plaintext and never surface in a database dump or query log. The admin form
 * writes to it; nothing else should read it except the driver that owns it.
 */
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = [
        'key',
        'label',
        'is_enabled',
        'environment',
        'config',
        'instructions',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'environment' => PaymentEnvironment::class,
            // Encrypted at rest. Note this makes `config` unqueryable, which is
            // correct — nothing should ever search on a secret key.
            'config' => 'encrypted:array',
            'instructions' => RichHtml::class,
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @param  Builder<PaymentMethod>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    /**
     * @param  Builder<PaymentMethod>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('label');
    }

    /*
    |--------------------------------------------------------------------------
    | Driver metadata (config/commerce.php)
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    public function driverConfig(): array
    {
        return config("commerce.drivers.{$this->key}", []);
    }

    /**
     * Whether the app actually has code for this row's key. A row whose driver has
     * been removed from config must not be offered at checkout.
     */
    public function hasDriver(): bool
    {
        return isset($this->driverConfig()['class']);
    }

    public function supportsSubscriptions(): bool
    {
        return (bool) ($this->driverConfig()['supports_subscriptions'] ?? false);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->config ?? [])[$key] ?? $default;
    }

    public function isLive(): bool
    {
        return $this->environment === PaymentEnvironment::Live;
    }

    /**
     * Whether this method has everything it needs to take a payment. Asked by the
     * admin screen (to show an "incomplete" state) and by the checkout form (to avoid
     * offering a method that will fail on submit).
     */
    public function isConfigured(): bool
    {
        if (! $this->hasDriver()) {
            return false;
        }

        return match ($this->key) {
            'paystack' => filled($this->setting('secret_key')),
            'bank_transfer' => filled($this->instructions),
            default => true,       // sandbox needs nothing
        };
    }

    /**
     * The URL a gateway should POST events to. Read-only on the admin form with a
     * copy button — staff paste it into the gateway dashboard.
     */
    public function webhookUrl(): string
    {
        return route('payments.webhook', ['method' => $this->key]);
    }
}
