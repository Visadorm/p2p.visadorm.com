<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Merchant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KycProfileSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Merchant $merchant) {}
}
