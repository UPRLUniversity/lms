<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CertificateTemplate;
use App\Models\User;

/**
 * Template design is admin-only — like GradeScalePolicy, no auditor read access, since
 * template configuration (signatories, layout) isn't part of the auditor's record-
 * keeping view (that's the certificate registry, see CertificatePolicy).
 */
class CertificateTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CertificateTemplatesManage->value);
    }

    public function view(User $user, CertificateTemplate $template): bool
    {
        return $user->can(Permission::CertificateTemplatesManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CertificateTemplatesManage->value);
    }

    public function update(User $user, CertificateTemplate $template): bool
    {
        return $user->can(Permission::CertificateTemplatesManage->value);
    }
}
