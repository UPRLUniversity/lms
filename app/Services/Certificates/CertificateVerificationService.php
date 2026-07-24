<?php

namespace App\Services\Certificates;

use App\Models\Certificate;

/**
 * The public verification lookup — no auth, deliberately narrow in what it exposes.
 * A VALID result never includes the grade (privacy decision: a certificate proves
 * completion, not a public transcript) and a REVOKED result withholds the reason. A
 * miss never distinguishes "wrong format" from "no such serial", so a stranger can't
 * learn anything about the serial scheme by probing it.
 */
class CertificateVerificationService
{
    /**
     * @return array{status: 'valid'|'revoked'|'not_found', certificate: ?Certificate}
     */
    public function verify(string $serial): array
    {
        $normalized = strtoupper(trim($serial));

        $certificate = Certificate::query()
            ->where('serial', $normalized)
            ->with(['user:id,name', 'course:id,title'])
            ->first();

        if ($certificate === null) {
            return ['status' => 'not_found', 'certificate' => null];
        }

        if ($certificate->isRevoked()) {
            return ['status' => 'revoked', 'certificate' => $certificate];
        }

        return ['status' => 'valid', 'certificate' => $certificate];
    }
}
