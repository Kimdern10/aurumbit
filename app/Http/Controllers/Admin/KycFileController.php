<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycSubmission;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycFileController extends Controller
{
    /**
     * Show an identity document to an admin. The files are never public.
     */
    public function __invoke(KycSubmission $kycSubmission, string $file): StreamedResponse
    {
        $path = $file === 'selfie' ? $kycSubmission->selfie_path : $kycSubmission->document_path;

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, headers: [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
