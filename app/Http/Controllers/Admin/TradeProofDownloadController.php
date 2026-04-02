<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradeProofDownloadController extends Controller
{
    public function bankProof(Trade $trade): StreamedResponse
    {
        abort_unless(auth()->user()?->role !== null, 403);
        abort_unless($trade->bank_proof_path && Storage::disk('local')->exists($trade->bank_proof_path), 404);

        return Storage::disk('local')->download(
            $trade->bank_proof_path,
            'bank-proof-' . substr($trade->trade_hash, 0, 10) . '.' . pathinfo($trade->bank_proof_path, PATHINFO_EXTENSION)
        );
    }

    public function buyerId(Trade $trade): StreamedResponse
    {
        abort_unless(auth()->user()?->role !== null, 403);
        abort_unless($trade->buyer_id_path && Storage::disk('local')->exists($trade->buyer_id_path), 404);

        return Storage::disk('local')->download(
            $trade->buyer_id_path,
            'buyer-id-' . substr($trade->trade_hash, 0, 10) . '.' . pathinfo($trade->buyer_id_path, PATHINFO_EXTENSION)
        );
    }
}
