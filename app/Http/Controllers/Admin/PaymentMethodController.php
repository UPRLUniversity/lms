<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Store → Payment methods.
 *
 * One card per driver declared in config/commerce.php. A driver with no database row
 * yet shows as "not installed" with an Install action, which is what lets the screen
 * list PayPal-style options the app could support without pretending they are live.
 *
 * Secret keys are never echoed back to the page. The form posts a blank secret to mean
 * "leave it alone", so an admin editing the environment does not have to re-paste a key
 * they cannot see.
 */
class PaymentMethodController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $installed = PaymentMethod::query()->ordered()->get()->keyBy('key');

        // Driver definitions are the source of truth for what exists; rows say what is
        // configured. Present both so nothing is silently missing from the screen.
        $cards = collect(config('commerce.drivers', []))
            ->map(fn (array $driver, string $key) => [
                'key' => $key,
                'label' => $driver['label'] ?? $key,
                'supports_subscriptions' => (bool) ($driver['supports_subscriptions'] ?? false),
                'method' => $installed->get($key),
            ])
            ->values();

        return view('admin.payment-methods.index', [
            'cards' => $cards,
            'environments' => PaymentEnvironment::cases(),
            'canManage' => request()->user()->can('create', PaymentMethod::class),
        ]);
    }

    /**
     * Create the row for a driver that has none yet.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PaymentMethod::class);

        $key = (string) $request->input('key');
        $driver = config("commerce.drivers.{$key}");

        abort_if($driver === null, 404);

        PaymentMethod::firstOrCreate(
            ['key' => $key],
            [
                'label' => $driver['label'] ?? $key,
                'is_enabled' => false,     // installing is not the same as switching on
                'environment' => PaymentEnvironment::Test,
                'config' => $driver['config'] ?? [],
                'position' => (int) PaymentMethod::max('position') + 1,
            ],
        );

        return back()->with('status', ($driver['label'] ?? $key).' is ready to configure.');
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $request->validated();

        $paymentMethod->update([
            'label' => $data['label'] ?? $paymentMethod->label,
            'environment' => $data['environment'] ?? $paymentMethod->environment->value,
            'instructions' => $data['instructions'] ?? null,
            // Blank secrets mean "unchanged" — see mergedConfig().
            'config' => $request->mergedConfig($paymentMethod),
        ]);

        return back()->with('status', $paymentMethod->label.' updated.');
    }

    /**
     * Toggle a method on or off. Refuses to enable one that would fail on first use.
     */
    public function toggle(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorize('update', $paymentMethod);

        $enable = $request->boolean('enable');

        if ($enable && ! $paymentMethod->isConfigured()) {
            return back()->with('error', $paymentMethod->label.' is missing its configuration, so it cannot be switched on yet.');
        }

        $paymentMethod->update(['is_enabled' => $enable]);

        return back()->with('status', $paymentMethod->label.($enable ? ' is now accepting payments.' : ' was switched off.'));
    }

    /**
     * Remove the configuration for a method, credentials and all.
     */
    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorize('delete', $paymentMethod);

        $label = $paymentMethod->label;
        $paymentMethod->delete();

        return back()->with('status', $label.' was removed. Its saved keys are gone.');
    }
}
