<?php

namespace App\Policies;

use App\Enums\CouponScope;
use App\Enums\Permission;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\User;

/**
 * Who may issue a discount.
 *
 * Scope is the boundary. An instructor may discount a course they teach — that is
 * their own enrolment to grow. Global and programme-wide codes cost the institution
 * money across a whole catalogue, so they are admin-only. An instructor therefore
 * needs no `coupons.manage` permission at all; their authority comes from teaching
 * the course.
 */
class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CouponsManage->value) || $user->hasRole('instructor');
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $this->modify($user, $coupon);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $this->modify($user, $coupon);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $this->modify($user, $coupon);
    }

    /**
     * Whether $user may issue codes for a specific course — the instructor path.
     */
    public function manageForCourse(User $user, Course $course): bool
    {
        return $user->can(Permission::CouponsManage->value) || $user->can('update', $course);
    }

    /**
     * Whether $user may issue a code at the given scope.
     */
    public function useScope(User $user, CouponScope $scope): bool
    {
        return $scope->isAdminOnly()
            ? $user->can(Permission::CouponsManage->value)
            : $this->viewAny($user);
    }

    private function modify(User $user, Coupon $coupon): bool
    {
        if ($user->can(Permission::CouponsManage->value)) {
            return true;
        }

        // An instructor reaches only their own course's codes.
        return $coupon->scope === CouponScope::Course
            && $coupon->course !== null
            && $user->can('update', $coupon->course);
    }
}
