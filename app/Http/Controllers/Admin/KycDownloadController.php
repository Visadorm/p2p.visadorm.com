<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycDownloadController extends Controller
{
    public function __invoke(KycDocument $kycDocument): StreamedResponse
    {
        abort_unless(auth()->user()?->role !== null, 403);

        abort_unless(Storage::disk('local')->exists($kycDocument->file_path), 404);

        return Storage::disk('local')->download(
            $kycDocument->file_path,
            $kycDocument->original_name
        );
    }
}
