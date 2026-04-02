<?php

namespace App\Events;

use App\Models\KycDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KycDocumentReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public KycDocument $document,
    ) {}
}
