<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function notifications(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $perPage = (int) ($request->input('per_page') ?? 10);
        $perPage = max(1, min($perPage, 100));

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $items = collect($notifications->items())
            ->map(fn($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ])
            ->all();

        return response()->json([
            'data' => empty($items) ? [] : $items,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
                'next_page_url' => $notifications->nextPageUrl(),
                'prev_page_url' => $notifications->previousPageUrl(),
            ],
        ]);
    }

    public function markAsRead(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $ids = $request->input('ids', []);

        $query = $user->notifications()->whereNull('read_at');
        if (is_array($ids) && !empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $updated = $query->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notifications marked as read',
            'updated' => (int) $updated,
        ]);
    }

    public function readAll(Request $request)
    {
        return $this->markAsRead($request);
    }
}
