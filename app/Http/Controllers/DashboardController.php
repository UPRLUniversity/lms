<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\Reporting\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The role-aware dashboard (Section 10) — the app's landing page. Every figure comes
 * from DashboardService (real queries), and the view branches on the viewer's role.
 * The read-only auditor sees the admin overview with no mutating affordances.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $isAdmin = $user->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]);
        $isAuditor = $user->hasRole(Role::Auditor->value);
        $isInstructor = $user->hasRole(Role::Instructor->value);

        $data = [
            'isAdmin' => $isAdmin,
            'isAuditor' => $isAuditor,
            'isStaff' => $isAdmin || $isInstructor,
        ];

        if ($isAdmin || $isAuditor) {
            $data += [
                'stats' => $this->dashboard->adminStats(),
                'trend' => $this->dashboard->enrollmentTrend(),
                'topCourses' => $this->dashboard->topCourses(),
                'activity' => $this->dashboard->recentActivity(),
            ];
        } elseif ($isInstructor) {
            $data += $this->dashboard->instructorOverview($user);
        } else {
            $data += $this->dashboard->studentDashboard($user);
        }

        return view('dashboard', $data);
    }
}
