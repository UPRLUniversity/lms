<?php

namespace App\Http\Controllers;

use App\Services\Certificates\CertificateVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public certificate verification portal — no auth. Manual serial entry (the form
 * on /verify) and a QR deep-link (/verify/{serial}) both resolve through the same
 * lookup, so they always agree.
 */
class VerificationController extends Controller
{
    public function index(): View
    {
        return view('verify.index');
    }

    /**
     * The manual-entry form submits here and is redirected to the canonical
     * /verify/{serial} URL — the same one a QR code encodes.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'serial' => ['required', 'string', 'max:50'],
        ]);

        return redirect()->route('verify.show', strtoupper(trim($data['serial'])));
    }

    public function show(string $serial, CertificateVerificationService $verification): View
    {
        $result = $verification->verify($serial);

        return view('verify.show', [
            'status' => $result['status'],
            'certificate' => $result['certificate'],
            'serial' => $serial,
        ]);
    }
}
