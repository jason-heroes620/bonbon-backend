<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventImages;
use App\Models\Events;
use Illuminate\Http\Request;

class EventsController extends Controller
{
    //
    public function events(Request $request)
    {
        $events = Events::where('is_active', true)
            ->where('is_published', true)
            ->where('event_start_date', '>=', now())
            ->where('event_end_date', '>=', now())
            ->orderBy('event_start_date', 'asc')
            ->limit(6)
            ->get();
        return response()->json([
            'data' => $events,
        ]);
    }

    public function event(Request $request, $event_id)
    {
        $event = Events::where('is_active', true)
            ->where('is_published', true)
            ->where('event_id', $event_id)
            ->first();
        $result = collect($event->except('event_image_path'));
        $result->put('event_image_path', EventImages::where('event_id', $event_id)
            ->pluck('event_image_path')
            ->toArray());
        if (!$event) {
            return response()->json([
                'message' => 'Event not found',
            ], 404);
        }
        return response()->json([
            'data' => $result,
        ]);
    }
}
