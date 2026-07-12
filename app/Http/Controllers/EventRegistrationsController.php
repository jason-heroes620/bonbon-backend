<?php

namespace App\Http\Controllers;

use App\Models\EventRegistration;
use App\Models\Events;
use Illuminate\Http\Request;

class EventRegistrationsController extends Controller
{
    public function index(Request $request, Events $event)
    {
        $this->assertAdmin($request);

        $query = EventRegistration::query()
            ->where('event_id', $event->event_id)
            ->leftJoin('users', 'event_registrations.user_id', '=', 'users.user_id')
            ->select([
                'event_registrations.*',
                'users.email as user_email',
                'users.full_name as user_full_name',
            ]);

        if ($status = $request->input('status')) {
            $query->where('registration_status', $status);
        }

        if ($membershipType = $request->input('membership_type')) {
            $query->where('membership_type_at_registration', $membershipType);
        }

        $query->orderByDesc('joined_at');

        $perPage = $request->per_page ?? 10;
        $items = $query->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    private function assertAdmin(Request $request): void
    {
        if ((string) $request->user()->role !== 'admin') {
            abort(403);
        }
    }
}

