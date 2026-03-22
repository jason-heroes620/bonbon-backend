<?php

namespace App\Http\Controllers;

use App\Models\Notifications;
use App\Models\UserPushTokens;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;

class NotificationsController extends Controller
{
    public function index()
    {
        return Inertia::render('notifications/notifications');
    }

    public function showAll(Request $request)
    {
        $query = Notifications::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($request->has('filters')) {
            foreach ($request->filters as $column => $value) {
                if ($value !== null) {
                    $query->where($column, $value);
                }
            }
        }

        $allowedSortFields = [
            'title',
            'audience',
            'status',
            'sent_at',
            'created_at',
        ];

        if ($request->has('sort')) {
            $field = (string) ($request->sort['field'] ?? '');
            $direction = (string) ($request->sort['direction'] ?? 'asc');
            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, $allowedSortFields, true)) {
                $query->orderBy($field, $direction);
            } else {
                $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $notifications = $query->paginate($perPage);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('notifications/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'audience' => ['required', 'in:all_users,user'],
            'user_id' => ['nullable', 'uuid'],
            'data' => ['nullable'],
            'send_now' => ['nullable', 'boolean'],
        ]);

        $data = $this->coerceNotificationData($validated['data'] ?? null);

        $notification = Notifications::create([
            'notification_id' => (string) Str::uuid(),
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'audience' => $validated['audience'],
            'user_id' => $validated['audience'] === 'user' ? ($validated['user_id'] ?? null) : null,
            'data' => $data,
            'created_by' => optional($request->user())->user_id,
            'status' => 'draft',
            'sent_at' => null,
        ]);

        $sendNow = (bool) ($validated['send_now'] ?? false);
        if ($sendNow) {
            $this->sendNotification($notification);
        }

        return redirect()->route('notifications.index');
    }

    public function edit(Notifications $notification)
    {
        return Inertia::render('notifications/edit', [
            'notification' => $notification,
        ]);
    }

    public function update(Request $request, Notifications $notification)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'audience' => ['required', 'in:all_users,user'],
            'user_id' => ['nullable', 'uuid'],
            'data' => ['nullable'],
            'send_now' => ['nullable', 'boolean'],
        ]);

        $data = $this->coerceNotificationData($validated['data'] ?? null);

        $notification->update([
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'audience' => $validated['audience'],
            'user_id' => $validated['audience'] === 'user' ? ($validated['user_id'] ?? null) : null,
            'data' => $data,
        ]);

        $sendNow = (bool) ($validated['send_now'] ?? false);
        if ($sendNow && $notification->status !== 'sent') {
            $this->sendNotification($notification);
        }

        return redirect()->route('notifications.index');
    }

    public function destroy(Notifications $notification)
    {
        $notification->delete();

        return redirect()->route('notifications.index');
    }

    public function send(Request $request, Notifications $notification)
    {
        if ($notification->status === 'sent') {
            return redirect()
                ->route('notifications.index')
                ->with('success', 'Notification has already been sent.');
        }

        $this->sendNotification($notification);

        return redirect()
            ->route('notifications.index')
            ->with('success', 'Notification sent successfully.');
    }

    private function coerceNotificationData($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            return ['message' => $raw];
        }

        return null;
    }

    private function sendNotification(Notifications $notification): void
    {
        $tokenQuery = UserPushTokens::query();

        if ($notification->audience === 'user' && $notification->user_id) {
            $tokenQuery->where('user_id', $notification->user_id);
        }

        $now = now();
        $payload = [
            'title' => $notification->title,
            'body' => $notification->body ?? '',
        ];

        if (is_array($notification->data) && !empty($notification->data)) {
            $payload['data'] = $notification->data;
        }

        if ($notification->audience === 'user' && $notification->user_id) {
            DB::table('notifications')->insert([
                'notification_id' => (string) Str::uuid(),
                'type' => 'expo_push',
                'notifiable_type' => User::class,
                'notifiable_id' => $notification->user_id,
                'data' => json_encode($payload),
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            User::query()
                ->select('user_id')
                ->where('role', 'user')
                ->where('is_active', true)
                ->orderBy('user_id')
                ->chunk(500, function ($users) use ($payload, $now) {
                    $rows = [];
                    foreach ($users as $user) {
                        $rows[] = [
                            'notification_id' => (string) Str::uuid(),
                            'type' => 'expo_push',
                            'notifiable_type' => User::class,
                            'notifiable_id' => $user->user_id,
                            'data' => json_encode($payload),
                            'read_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (!empty($rows)) {
                        DB::table('notifications')->insert($rows);
                    }
                });
        }

        $tokens = $tokenQuery->pluck('expo_push_token')->filter()->values()->all();
        if (empty($tokens)) {
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            return;
        }

        $messages = array_map(function ($token) use ($notification) {
            $payload = [
                'to' => $token,
                'title' => $notification->title,
                'body' => $notification->body ?? '',
                'sound' => 'default',
            ];

            if (is_array($notification->data) && !empty($notification->data)) {
                $payload['data'] = $notification->data;
            }

            return $payload;
        }, $tokens);

        $chunks = array_chunk($messages, 100);
        foreach ($chunks as $chunk) {
            Http::post('https://exp.host/--/api/v2/push/send', $chunk);
        }

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
