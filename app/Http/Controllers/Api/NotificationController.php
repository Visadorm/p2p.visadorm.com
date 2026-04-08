<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\P2pNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List merchant's notifications (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $notifications = $merchant->notifications()
            ->with('trade:id,trade_hash,payment_method')
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $notifications,
            'message' => __('p2p.notifications_loaded'),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, P2pNotification $notification): JsonResponse
    {
        $merchant = $request->merchant;

        if ($notification->merchant_id !== $merchant->id) {
            return response()->json([
                'message' => __('p2p.notification_not_authorized'),
            ], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => __('p2p.notification_marked_read'),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $merchant->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => __('p2p.notifications_all_read'),
        ]);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $count = $merchant->notifications()
            ->where('is_read', false)
            ->count();

        return response()->json([
            'data' => ['unread_count' => $count],
            'message' => __('p2p.unread_count_loaded'),
        ]);
    }
}
