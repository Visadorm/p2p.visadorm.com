<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\TradingLinkType;
use App\Models\MerchantTradingLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TradingLinkController extends Controller
{
    /**
     * List merchant's trading links.
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $links = $merchant->tradingLinks()->where('is_active', true)->withCount('trades')->latest()->get();

        return response()->json([
            'data' => $links,
            'message' => __('p2p.trading_links_loaded'),
        ]);
    }

    /**
     * Create a new trading link.
     */
    public function store(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(TradingLinkType::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $hasPrimary = $merchant->tradingLinks()->where('is_primary', true)->exists();

        do {
            $slug = Str::slug($validated['label']) . '-' . Str::random(8);
        } while (MerchantTradingLink::where('slug', $slug)->exists());

        $link = $merchant->tradingLinks()->create([
            'label' => $validated['label'],
            'type' => $validated['type'],
            'slug' => $slug,
            'is_active' => $validated['is_active'] ?? true,
            'is_primary' => ! $hasPrimary,
        ]);

        return response()->json([
            'data' => $link,
            'message' => __('p2p.trading_link_created'),
        ], 201);
    }

    /**
     * Update a trading link.
     */
    public function update(Request $request, MerchantTradingLink $link): JsonResponse
    {
        $merchant = $request->merchant;

        if ($link->merchant_id !== $merchant->id) {
            return response()->json([
                'message' => __('p2p.forbidden'),
            ], 403);
        }

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', Rule::enum(TradingLinkType::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $link->update($validated);

        return response()->json([
            'data' => $link->fresh(),
            'message' => __('p2p.trading_link_updated'),
        ]);
    }

    /**
     * Deactivate (soft delete) a trading link.
     */
    public function destroy(Request $request, MerchantTradingLink $link): JsonResponse
    {
        $merchant = $request->merchant;

        if ($link->merchant_id !== $merchant->id) {
            return response()->json([
                'message' => __('p2p.forbidden'),
            ], 403);
        }

        $link->update(['is_active' => false]);

        return response()->json([
            'message' => __('p2p.trading_link_deleted'),
        ]);
    }
}
