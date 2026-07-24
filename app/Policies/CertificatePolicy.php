<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Certificate;
use App\Models\User;

/**
 * The registry is the record-keeping view — admins manage it, and per FacultyPolicy's
 * precedent, the read-only auditor may browse it (certificates.view, granted
 * automatically as a ".view" permission). A student may only ever view/download their
 * OWN certificate; there is no "view any certificate" ability for them.
 */
class CertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CertificatesView->value);
    }

    public function view(User $user, Certificate $certificate): bool
    {
        return $certificate->user_id === $user->id || $user->can(Permission::CertificatesView->value);
    }

    /**
     * Manual "Issue" has no existing Certificate instance to check against, so it's
     * authorized via the class-string form (`authorize('create', Certificate::class)`),
     * same as GradeScalePolicy::create.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::CertificatesManage->value);
    }

    public function manage(User $user, Certificate $certificate): bool
    {
        return $user->can(Permission::CertificatesManage->value);
    }
}
