<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Course;
use App\Services\Commerce\CartService;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\CouponService;
use App\Services\Courses\ProgressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The cart, deliberately open to guests.
 *
 * A signed-out visitor can browse the catalogue and fill a cart; login is required
 * only at checkout, and CartService::merge() carries the basket across on login. The
 * alternative — demanding an account before anything can be added — is the thing the
 * public catalogue exists to avoid.
 *
 * The applied coupon code lives in the session rather than on the cart row: it is a
 * transient intent, it must not survive into someone else's session, and it is
 * re-validated on every render and again at checkout.
 */
class CartController extends Controller
{
    private const COUPON_SESSION_KEY = 'cart.coupon';

    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
        private readonly CouponService $coupons,
        private readonly ProgressionService $progression,
    ) {}

    public function index(Request $request): View
    {
        $cart = $this->carts->current($request->user());

        // Anything they have since bought or been enrolled on is dropped, so the cart
        // never offers to sell access somebody already has.
        $pruned = $request->user() ? $this->carts->pruneUnbuyable($cart, $request->user()) : 0;
        if ($pruned > 0) {
            $cart->refresh()->load('items.course.programmeParts.programme', 'items.course.media');
        }

        $totals = $this->checkout->quote($cart, $request->user(), $this->couponCode($request));

        // A code that has stopped being valid (expired, or now inapplicable because the
        // cart changed) is cleared rather than left showing an error forever.
        if ($totals->couponError !== null) {
            $request->session()->forget(self::COUPON_SESSION_KEY);
        }

        return view('commerce.cart', [
            'cart' => $cart,
            'totals' => $totals,
            'pruned' => $pruned,
            // A line can become blocked after it was added — a programme switched to
            // sequential, or the buyer signed in as somebody else's cart merged. Flag it
            // here so checkout's refusal is never a surprise.
            'verdicts' => $request->user()
                ? $this->progression->verdictsFor(
                    $request->user(),
                    $cart->items->map(fn ($item) => $item->course)->filter()->values(),
                )
                : collect(),
        ]);
    }

    public function store(Request $request, Course $course, ProgressionService $progression): RedirectResponse
    {
        // Only a course a stranger could legitimately see may be added — otherwise the
        // cart becomes a way to discover unpublished courses.
        abort_unless($course->isPublished() && $course->visibility->isPublic(), 404);

        // THE PRIMARY PROGRESSION GATE. Every programme course is paid, so a purchase
        // reaches the course through OrderFulfilmentService — which deliberately does not
        // enforce, because refusing after payment strands a paid order. Refusing here,
        // before any money moves, is what makes that safe.
        //
        // Only for a signed-in visitor: a guest's history is unknowable, and demanding an
        // account before anything can be added is the friction the public catalogue exists
        // to remove. CheckoutService re-checks once they have signed in, which they must
        // do before paying.
        if ($student = $request->user()) {
            $verdict = $progression->check($student, $course);

            if ($verdict->isBlocked()) {
                return back()->with('error', $verdict->message());
            }
        }

        $cart = $this->carts->current($request->user());

        if (! $this->carts->add($cart, $course)) {
            return back()->with('error', 'Your cart is full. Check out or remove something first.');
        }

        // "Buy now" adds and goes straight on, so a single-course purchase is two clicks
        // rather than four. Guests included: since Section 13 the checkout entry shows
        // the order summary with an inline sign-in panel, so "buy now" no longer means
        // "meet a login wall" — which is the friction the public catalogue exists to
        // remove.
        if ($request->input('then') === 'checkout') {
            return redirect()->route('checkout.show');
        }

        return back()->with('status', "“{$course->title}” was added to your cart.");
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $cart = $this->carts->current($request->user());

        // CartService checks ownership; this is the belt to its braces.
        abort_unless($item->cart_id === $cart->id, 403);

        $title = $item->course?->title;
        $this->carts->remove($cart, $item);

        return back()->with('status', $title ? "“{$title}” was removed." : 'Item removed.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->carts->clear($this->carts->current($request->user()));
        $request->session()->forget(self::COUPON_SESSION_KEY);

        return back()->with('status', 'Your cart is empty.');
    }

    /**
     * Apply a coupon. Validated here so the buyer gets the reason immediately rather
     * than discovering it at checkout.
     */
    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:60']]);

        $cart = $this->carts->current($request->user());
        $totals = $this->checkout->quote($cart, $request->user(), $data['code']);

        if ($totals->couponError !== null) {
            return back()->with('error', $totals->couponError);
        }

        $request->session()->put(self::COUPON_SESSION_KEY, $this->coupons->normalise($data['code']));

        return back()->with('status', 'Code applied — '.$totals->coupon->describe().'.');
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        $request->session()->forget(self::COUPON_SESSION_KEY);

        return back()->with('status', 'Code removed.');
    }

    /**
     * The code currently applied, if any. Shared with CheckoutController.
     */
    public static function couponCode(Request $request): ?string
    {
        return $request->session()->get(self::COUPON_SESSION_KEY);
    }

    public static function forgetCoupon(Request $request): void
    {
        $request->session()->forget(self::COUPON_SESSION_KEY);
    }
}
