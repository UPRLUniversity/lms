<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentEnvironment;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('paymentMethod'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:80'],
            'environment' => ['nullable', Rule::in(PaymentEnvironment::values())],
            'instructions' => ['nullable', 'string'],

            // Free-form so a driver can declare whatever keys it needs without this
            // request having to know about each one. Values are bounded, and only keys
            // the driver actually declares are kept (see mergedConfig).
            'config' => ['nullable', 'array'],
            'config.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Merge submitted credentials over the stored ones.
     *
     * Secrets are never rendered back to the form, so an admin editing the environment
     * sees a masked field they cannot copy. A BLANK submitted value therefore has to
     * mean "leave this as it is" — treating it as "clear it" would silently break a
     * working gateway every time someone changed the label.
     *
     * Only keys the driver declares in config/commerce.php are accepted, so a crafted
     * post cannot stuff arbitrary data into the encrypted column.
     *
     * @return array<string, string>
     */
    public function mergedConfig(PaymentMethod $method): array
    {
        $allowed = array_keys((array) ($method->driverConfig()['config'] ?? []));
        $existing = (array) ($method->config ?? []);
        $submitted = (array) $this->input('config', []);

        // A driver with no declared config (bank transfer, sandbox) keeps whatever keys
        // it already has rather than being emptied.
        if ($allowed === []) {
            return $existing;
        }

        $merged = $existing;

        foreach ($allowed as $key) {
            $value = trim((string) ($submitted[$key] ?? ''));

            if ($value !== '') {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
