<?php

namespace App\Http\Controllers;

use App\Models\LuckyDrawEntries;
use App\Models\LuckyDrawSession;
use App\Models\LuckyDrawWinners;
use App\Models\User;
use App\Models\UserMemberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LucyDrawEntriesController extends Controller
{
    //
    public function sessionsPage(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        return Inertia::render('lucky-draw/sessions');
    }

    public function createSessionPage(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        return Inertia::render('lucky-draw/create');
    }

    public function storeSession(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        $validated = $request->validate([
            'session_name' => ['required', 'string', 'max:255'],
            'session_status' => ['required', 'in:pending,completed'],
            'winners_count' => ['required', 'integer', 'min:1'],
            'session_start_time' => ['required', 'date'],
            'session_end_time' => ['required', 'date', 'after:session_start_time'],
        ]);

        LuckyDrawSession::query()->create([
            'session_name' => $validated['session_name'],
            'session_status' => $validated['session_status'],
            'winners_count' => (int) $validated['winners_count'],
            'session_start_time' => $validated['session_start_time'],
            'session_end_time' => $validated['session_end_time'],
        ]);

        return redirect()->route('lucky_draw.sessions')->with([
            'success' => 'Lucky draw session created successfully',
        ]);
    }

    public function sessionsAll(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        $query = LuckyDrawSession::query();

        if ($search = $request->input('search')) {
            $query->where('session_name', 'like', "%{$search}%");
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderByDesc('id');
        }

        $perPage = $request->per_page ?? 10;
        $sessions = $query->paginate($perPage);

        return response()->json([
            'data' => $sessions->items(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'from' => $sessions->firstItem(),
                'to' => $sessions->lastItem(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $session_id = $request->input('session_id');
        if (!$session_id) {
            return response()->json(['error' => 'Session ID is required'], 400);
        }

        $entries = LuckyDrawEntries::query()
            ->where('session_id', $session_id)
            ->get();
        return response()->json($entries);
    }

    public function page(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        $sessions = LuckyDrawSession::query()
            ->orderByDesc('id')
            ->get([
                'id',
                'session_name',
                'session_status',
                'winners_count',
                'session_start_time',
                'session_end_time',
            ]);

        return Inertia::render('lucky-draw/winners', [
            'sessions' => $sessions,
            'initialSessionId' => $request->query('session_id'),
        ]);
    }

    public function prepareEntries(Request $request, string $sessionId)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        $session = LuckyDrawSession::query()->where('id', $sessionId)->first();
        if (!$session) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        $eligibleUsers = User::query()
            ->select(['user_id', 'email'])
            ->where('is_active', true)
            ->where('role', 'user')
            ->get();

        $now = now();

        $inserted = 0;

        DB::transaction(function () use ($sessionId, $eligibleUsers, $now, &$inserted) {
            LuckyDrawEntries::query()->where('session_id', $sessionId)->delete();
            LuckyDrawWinners::query()->where('session_id', $sessionId)->delete();

            foreach ($eligibleUsers as $user) {
                $membership = UserMemberships::query()
                    ->select(['user_id', 'membership_type', 'membership_status'])
                    ->leftJoin('memberships', 'user_memberships.membership_id', '=', 'memberships.membership_id')
                    ->where('user_id', $user->user_id)
                    ->whereIn('membership_type', ['Free', 'Standard'])
                    ->where('membership_status', 'active')
                    ->first();

                $eligible = $membership && $membership->membership
                    ? ($membership->membership->membership_type === 'Standard' ? 5 : 1)
                    : 1;

                LuckyDrawEntries::query()->create([
                    'session_id' => $sessionId,
                    'user_id' => (string) $user->user_id,
                    'email' => (string) $user->email,
                    'weight' => (int) $eligible,
                    'is_winner' => false,
                ]);

                $inserted++;
            }
        });

        return response()->json([
            'data' => [
                'inserted' => $inserted,
            ],
        ]);
    }

    public function runDraw(Request $request, string $sessionId)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        $session = LuckyDrawSession::query()->where('id', $sessionId)->first();
        if (!$session) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        $winnerCount = (int) $request->input('winner_count', $session->winners_count ?: 1);
        if ($winnerCount <= 0) {
            $winnerCount = 1;
        }

        $winners = [];

        for ($i = 0; $i < $winnerCount; $i++) {
            $totalWeight = DB::table('lucky_draw_entries')
                ->where('session_id', $sessionId)
                ->where('is_winner', false)
                ->sum('weight');

            if ((int) $totalWeight <= 0) {
                break;
            }

            $luckyNumber = random_int(1, (int) $totalWeight);

            $currentSum = 0;
            $potentialWinners = DB::table('lucky_draw_entries')
                ->where('session_id', $sessionId)
                ->where('is_winner', false)
                ->orderBy('id')
                ->get();

            foreach ($potentialWinners as $entry) {
                $currentSum += (int) $entry->weight;

                if ($luckyNumber <= $currentSum) {
                    $this->markAsWinner(
                        $sessionId,
                        (string) $entry->user_id,
                        (string) $entry->email,
                        (int) $luckyNumber
                    );
                    $winners[] = [
                        'user_id' => (string) $entry->user_id,
                        'email' => (string) $entry->email,
                        'winning_ticket_number' => (int) $luckyNumber,
                    ];
                    break;
                }
            }
        }

        return response()->json([
            'data' => [
                'winners' => $winners,
            ],
        ]);
    }

    public function winners(Request $request, string $sessionId)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        $winners = LuckyDrawWinners::query()
            ->where('session_id', $sessionId)
            ->orderByDesc('won_at')
            ->get(['id', 'user_id', 'email', 'winning_ticket_number', 'won_at']);

        return response()->json([
            'data' => $winners,
        ]);
    }

    protected function markAsWinner(string $sessionId, string $userId, string $email, int $luckyNumber): void
    {
        DB::transaction(function () use ($sessionId, $userId, $email, $luckyNumber) {
            // Update entry status so they don't win again
            DB::table('lucky_draw_entries')
                ->where('session_id', $sessionId)
                ->where('user_id', $userId)
                ->update(['is_winner' => true]);

            // Record in the audit trail
            DB::table('lucky_draw_winners')->insert([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'email' => $email,
                'winning_ticket_number' => $luckyNumber,
                'won_at' => now()
            ]);
        });
    }
}
