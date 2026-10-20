<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Certificates;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public certificate check (CER-REQ02): by QR link or typed number.
 * Shows the holder's short name, what it is for and whether it is valid.
 */
class CertificateVerifyController extends Controller
{
    public function show(Request $request, ?string $token = null): View
    {
        $input = $token ?? trim((string) $request->query('number'));
        $certificate = $input !== '' ? Certificates::find($input) : null;

        return view('certificates.verify', [
            'certificate' => $certificate?->load('user:user_id,full_name'),
            'checked' => $input !== '',
            'number' => $token ? '' : $input,
        ]);
    }
}
