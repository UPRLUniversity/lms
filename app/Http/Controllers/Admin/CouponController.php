<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Programme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Store → Discount codes.
 *
 * Instructors reach this screen too, but see only their own courses' codes and may only
 * create course-scoped ones — CouponPolicy decides, the controller just scopes the
 * query and the option lists to match.
 */
class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Coupon::class);

        $user = $request->user();
        $isAdmin = $user->can(\App\Enums\Permission::CouponsManage->value);

        $coupons = Coupon::query()
            ->with(['course', 'programme', 'creator'])
            ->withCount('redemptions')
            // An instructor sees codes for courses they teach, nothing else.
            ->when(! $isAdmin, fn ($q) => $q
                ->where('scope', CouponScope::Course->value)
                ->whereHas('course', fn ($c) => $c->forInstructor($user)))
            ->latest()
            ->paginate(20);

        return view('admin.coupons.index', [
            'coupons' => $coupons,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Coupon::class);

        return view('admin.coupons.create', $this->formData($request));
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $coupon = Coupon::create([
            'code' => $request->normalisedCode(),
            'name' => $data['name'] ?? null,
            'type' => $data['type'],
            'value' => $data['type'] === CouponType::Full->value ? 0 : ($data['value'] ?? 0),
            'scope' => $data['scope'],
            'course_id' => $data['scope'] === CouponScope::Course->value ? $data['course_id'] : null,
            'programme_id' => $data['scope'] === CouponScope::Programme->value ? $data['programme_id'] : null,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'per_user_limit' => $data['per_user_limit'] ?? 1,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.coupons.index')
            ->with('status', "Code {$coupon->code} created — {$coupon->describe()}.");
    }

    public function edit(Request $request, Coupon $coupon): View
    {
        $this->authorize('update', $coupon);

        return view('admin.coupons.edit', ['coupon' => $coupon] + $this->formData($request));
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $data = $request->validated();

        // The code itself is immutable once issued: someone may already be holding it,
        // and rewriting it would silently invalidate what they were given.
        $coupon->update([
            'name' => $data['name'] ?? null,
            'type' => $data['type'],
            'value' => $data['type'] === CouponType::Full->value ? 0 : ($data['value'] ?? 0),
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'per_user_limit' => $data['per_user_limit'] ?? 1,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('admin.coupons.index')->with('status', "Code {$coupon->code} updated.");
    }

    /**
     * Codes that have been redeemed are deactivated rather than deleted, so the
     * redemption ledger keeps pointing at something real.
     */
    public function destroy(Coupon $coupon): RedirectResponse
    {
        $this->authorize('delete', $coupon);

        if ($coupon->redemptionCount() > 0) {
            $coupon->update(['is_active' => false]);

            return back()->with('status', "Code {$coupon->code} has been used, so it was deactivated rather than deleted.");
        }

        $code = $coupon->code;
        $coupon->delete();

        return back()->with('status', "Code {$code} deleted.");
    }

    /**
     * Option lists for the form, narrowed to what this user may actually issue.
     *
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user->can(\App\Enums\Permission::CouponsManage->value);

        return [
            'types' => CouponType::cases(),
            // An instructor is offered only the Course scope, so the form cannot even
            // express a global code they would be refused for.
            'scopes' => collect(CouponScope::cases())
                ->filter(fn (CouponScope $scope) => ! $scope->isAdminOnly() || $isAdmin)
                ->values(),
            'courses' => Course::query()
                ->when(! $isAdmin, fn ($q) => $q->forInstructor($user))
                ->orderBy('code')
                ->get(['id', 'code', 'title']),
            'programmes' => $isAdmin
                ? Programme::query()->ordered()->get(['id', 'code', 'name'])
                : collect(),
            'isAdmin' => $isAdmin,
        ];
    }
}
