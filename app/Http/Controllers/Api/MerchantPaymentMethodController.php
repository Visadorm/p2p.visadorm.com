<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\PaymentMethodType;
use App\Models\MerchantPaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MerchantPaymentMethodController extends Controller
{
    /**
     * List merchant's payment methods.
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $methods = $merchant->paymentMethods()->latest()->get();

        return response()->json([
            'data' => $methods,
            'message' => __('p2p.payment_methods_loaded'),
        ]);
    }

    /**
     * Create a new payment method.
     */
    public function store(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $validated = $request->validate([
            'type' => ['required', Rule::enum(PaymentMethodType::class)],
            'provider' => ['required', 'string', 'max:100'],
            'label' => ['required', 'string', 'max:100'],
            'details' => ['required', 'array'],
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location_lat' => ['sometimes', 'nullable', 'numeric'],
            'location_lng' => ['sometimes', 'nullable', 'numeric'],
            'safety_note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $method = $merchant->paymentMethods()->create($validated);

        return response()->json([
            'data' => $method,
            'message' => __('p2p.payment_method_created'),
        ], 201);
    }

    /**
     * Update a payment method.
     */
    public function update(Request $request, MerchantPaymentMethod $paymentMethod): JsonResponse
    {
        $merchant = $request->merchant;

        if ($paymentMethod->merchant_id !== $merchant->id) {
            return response()->json([
                'message' => __('p2p.forbidden'),
            ], 403);
        }

        $validated = $request->validate([
            'type' => ['sometimes', Rule::enum(PaymentMethodType::class)],
            'provider' => ['sometimes', 'string', 'max:100'],
            'label' => ['sometimes', 'string', 'max:100'],
            'details' => ['sometimes', 'array'],
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location_lat' => ['sometimes', 'nullable', 'numeric'],
            'location_lng' => ['sometimes', 'nullable', 'numeric'],
            'safety_note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $paymentMethod->update($validated);

        return response()->json([
            'data' => $paymentMethod->fresh(),
            'message' => __('p2p.payment_method_updated'),
        ]);
    }

    /**
     * Delete a payment method.
     */
    public function destroy(Request $request, MerchantPaymentMethod $paymentMethod): JsonResponse
    {
        $merchant = $request->merchant;

        if ($paymentMethod->merchant_id !== $merchant->id) {
            return response()->json([
                'message' => __('p2p.forbidden'),
            ], 403);
        }

        $paymentMethod->delete();

        return response()->json([
            'message' => __('p2p.payment_method_deleted'),
        ]);
    }
}
