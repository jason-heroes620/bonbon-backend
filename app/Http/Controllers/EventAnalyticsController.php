<?php

namespace App\Http\Controllers;

use App\Models\EventQuestionnaire;
use App\Models\EventRegistration;
use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventAnalyticsController extends Controller
{
    public function summary(Request $request, Events $event)
    {
        $this->assertAdmin($request);

        $statuses = $request->input('statuses');
        if (!is_array($statuses) || count($statuses) === 0) {
            $statuses = ['confirmed'];
        }

        $registrationCounts = EventRegistration::query()
            ->where('event_id', $event->event_id)
            ->select('registration_status', DB::raw('count(*) as count'))
            ->groupBy('registration_status')
            ->pluck('count', 'registration_status');

        $questions = EventQuestionnaire::query()
            ->where('event_id', $event->event_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get([
                'event_questionnaire_id',
                'question_label_snapshot',
                'question_type_snapshot',
                'is_required',
                'sort_order',
            ]);

        $aggregates = [];
        foreach ($questions as $question) {
            $type = (string) $question->question_type_snapshot;

            if (in_array($type, ['single_select', 'multi_select', 'yes_no'], true)) {
                $counts = DB::table('event_registration_answers as a')
                    ->join('event_registrations as r', 'a.event_registration_id', '=', 'r.event_registration_id')
                    ->leftJoin('event_question_options as o', 'a.event_question_option_id', '=', 'o.event_question_option_id')
                    ->where('r.event_id', $event->event_id)
                    ->whereIn('r.registration_status', $statuses)
                    ->where('a.event_questionnaire_id', $question->event_questionnaire_id)
                    ->select(DB::raw('coalesce(o.option_value, a.answer_value) as value'), DB::raw('count(*) as count'))
                    ->groupBy(DB::raw('coalesce(o.option_value, a.answer_value)'))
                    ->orderByDesc('count')
                    ->get();

                $aggregates[] = [
                    'event_questionnaire_id' => $question->event_questionnaire_id,
                    'question_label' => $question->question_label_snapshot,
                    'question_type' => $type,
                    'counts' => $counts,
                ];
                continue;
            }

            if (in_array($type, ['short_text', 'long_text'], true)) {
                $count = DB::table('event_registration_answers as a')
                    ->join('event_registrations as r', 'a.event_registration_id', '=', 'r.event_registration_id')
                    ->where('r.event_id', $event->event_id)
                    ->whereIn('r.registration_status', $statuses)
                    ->where('a.event_questionnaire_id', $question->event_questionnaire_id)
                    ->whereNotNull('a.answer_text')
                    ->count();

                $aggregates[] = [
                    'event_questionnaire_id' => $question->event_questionnaire_id,
                    'question_label' => $question->question_label_snapshot,
                    'question_type' => $type,
                    'text_answer_count' => $count,
                ];
                continue;
            }

            $aggregates[] = [
                'event_questionnaire_id' => $question->event_questionnaire_id,
                'question_label' => $question->question_label_snapshot,
                'question_type' => $type,
            ];
        }

        return response()->json([
            'data' => [
                'registration_counts' => $registrationCounts,
                'questions' => $questions,
                'aggregates' => $aggregates,
                'statuses' => $statuses,
            ],
        ]);
    }

    public function exportAnswers(Request $request, Events $event)
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string'],
            'event_questionnaire_id' => ['nullable', 'uuid', 'exists:event_questionnaires,event_questionnaire_id'],
        ]);

        $statuses = $validated['statuses'] ?? ['confirmed'];

        $query = DB::table('event_registration_answers as a')
            ->join('event_registrations as r', 'a.event_registration_id', '=', 'r.event_registration_id')
            ->join('users as u', 'r.user_id', '=', 'u.user_id')
            ->leftJoin('event_questionnaires as q', 'a.event_questionnaire_id', '=', 'q.event_questionnaire_id')
            ->leftJoin('event_question_options as o', 'a.event_question_option_id', '=', 'o.event_question_option_id')
            ->where('r.event_id', $event->event_id)
            ->whereIn('r.registration_status', $statuses)
            ->select([
                'r.event_registration_id',
                'r.registration_status',
                'r.joined_at',
                'u.user_id',
                'u.email',
                'u.full_name',
                'a.event_questionnaire_id',
                'q.question_label_snapshot as question_label',
                'q.question_type_snapshot as question_type',
                DB::raw('coalesce(o.option_label, a.answer_value) as answer_choice'),
                'a.answer_text',
            ])
            ->orderBy('r.joined_at', 'desc');

        if (!empty($validated['event_questionnaire_id'])) {
            $query->where('a.event_questionnaire_id', $validated['event_questionnaire_id']);
        }

        $rows = $query->get();

        return response()->json([
            'data' => $rows,
        ]);
    }

    private function assertAdmin(Request $request): void
    {
        if ((string) $request->user()->role !== 'admin') {
            abort(403);
        }
    }
}

