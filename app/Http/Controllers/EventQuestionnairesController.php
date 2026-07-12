<?php

namespace App\Http\Controllers;

use App\Models\EventQuestionOption;
use App\Models\EventQuestionnaire;
use App\Models\Events;
use App\Models\QuestionTemplate;
use Illuminate\Http\Request;

class EventQuestionnairesController extends Controller
{
    public function index(Request $request, Events $event)
    {
        $this->assertAdmin($request);

        $questions = EventQuestionnaire::query()
            ->where('event_id', $event->event_id)
            ->with(['options' => function ($q) {
                $q->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $questions,
        ]);
    }

    public function storeCustom(Request $request, Events $event)
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'question_label' => ['required', 'string', 'max:255'],
            'question_help_text' => ['nullable', 'string'],
            'question_type' => ['required', 'in:short_text,long_text,single_select,multi_select,yes_no'],
            'is_required' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $sortOrder = (int) ($validated['sort_order'] ?? 0);
        if ($sortOrder <= 0) {
            $sortOrder = (int) (EventQuestionnaire::query()
                ->where('event_id', $event->event_id)
                ->max('sort_order') ?? 0) + 1;
        }

        $question = EventQuestionnaire::create([
            'event_id' => $event->event_id,
            'question_template_id' => null,
            'question_label_snapshot' => $validated['question_label'],
            'question_help_text_snapshot' => $validated['question_help_text'] ?? null,
            'question_type_snapshot' => $validated['question_type'],
            'is_required' => $validated['is_required'],
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);

        if ((string) $validated['question_type'] === 'yes_no') {
            $this->ensureYesNoOptions($question);
        }

        return response()->json([
            'data' => $question->fresh(['options']),
        ]);
    }

    public function attachTemplates(Request $request, Events $event)
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'template_ids' => ['required', 'array', 'min:1'],
            'template_ids.*' => ['uuid', 'exists:question_templates,question_template_id'],
        ]);

        $templates = QuestionTemplate::query()
            ->whereIn('question_template_id', $validated['template_ids'])
            ->where('is_active', true)
            ->with(['options' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }])
            ->get();

        $existingTemplateIds = EventQuestionnaire::query()
            ->where('event_id', $event->event_id)
            ->whereNotNull('question_template_id')
            ->where('is_active', true)
            ->pluck('question_template_id')
            ->map(fn($id) => (string) $id)
            ->all();

        $nextSortOrder = (int) (EventQuestionnaire::query()
            ->where('event_id', $event->event_id)
            ->max('sort_order') ?? 0) + 1;

        $created = [];
        foreach ($templates as $template) {
            if (in_array((string) $template->question_template_id, $existingTemplateIds, true)) {
                continue;
            }

            $question = EventQuestionnaire::create([
                'event_id' => $event->event_id,
                'question_template_id' => $template->question_template_id,
                'question_label_snapshot' => $template->question_label,
                'question_help_text_snapshot' => $template->question_help_text,
                'question_type_snapshot' => $template->question_type,
                'is_required' => (bool) $template->is_required_default,
                'sort_order' => $nextSortOrder,
                'is_active' => true,
            ]);
            $nextSortOrder++;

            if ((string) $template->question_type === 'yes_no') {
                $this->ensureYesNoOptions($question);
            } elseif (in_array((string) $template->question_type, ['single_select', 'multi_select'], true)) {
                foreach ($template->options as $option) {
                    EventQuestionOption::create([
                        'event_questionnaire_id' => $question->event_questionnaire_id,
                        'option_label' => $option->option_label,
                        'option_value' => $option->option_value,
                        'sort_order' => (int) $option->sort_order,
                        'is_active' => (bool) $option->is_active,
                    ]);
                }
            }

            $created[] = $question->event_questionnaire_id;
        }

        $questions = EventQuestionnaire::query()
            ->where('event_id', $event->event_id)
            ->with(['options' => function ($q) {
                $q->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $questions,
            'created_ids' => $created,
        ]);
    }

    public function update(Request $request, Events $event, EventQuestionnaire $question)
    {
        $this->assertAdmin($request);

        if ((string) $question->event_id !== (string) $event->event_id) {
            abort(404);
        }

        $validated = $request->validate([
            'question_label_snapshot' => ['required', 'string', 'max:255'],
            'question_help_text_snapshot' => ['nullable', 'string'],
            'is_required' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        $question->update($validated);

        return response()->json([
            'data' => $question->fresh(['options']),
        ]);
    }

    public function destroy(Request $request, Events $event, EventQuestionnaire $question)
    {
        $this->assertAdmin($request);

        if ((string) $question->event_id !== (string) $event->event_id) {
            abort(404);
        }

        $question->update([
            'is_active' => false,
        ]);

        return response()->json([
            'ok' => true,
        ]);
    }

    public function storeOption(Request $request, Events $event, EventQuestionnaire $question)
    {
        $this->assertAdmin($request);

        if ((string) $question->event_id !== (string) $event->event_id) {
            abort(404);
        }

        if (!in_array((string) $question->question_type_snapshot, ['single_select', 'multi_select'], true)) {
            return response()->json([
                'message' => 'Options are only supported for single select and multi select questions.',
            ], 422);
        }

        $validated = $request->validate([
            'option_label' => ['required', 'string', 'max:255'],
            'option_value' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        $sortOrder = (int) ($validated['sort_order'] ?? 0);
        if ($sortOrder <= 0) {
            $sortOrder = (int) (EventQuestionOption::query()
                ->where('event_questionnaire_id', $question->event_questionnaire_id)
                ->max('sort_order') ?? 0) + 1;
        }

        $option = EventQuestionOption::create([
            'event_questionnaire_id' => $question->event_questionnaire_id,
            'option_label' => $validated['option_label'],
            'option_value' => $validated['option_value'],
            'sort_order' => $sortOrder,
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'data' => $option,
        ]);
    }

    public function updateOption(Request $request, Events $event, EventQuestionnaire $question, EventQuestionOption $option)
    {
        $this->assertAdmin($request);

        if ((string) $question->event_id !== (string) $event->event_id) {
            abort(404);
        }

        if ((string) $option->event_questionnaire_id !== (string) $question->event_questionnaire_id) {
            abort(404);
        }

        $validated = $request->validate([
            'option_label' => ['required', 'string', 'max:255'],
            'option_value' => ['required', 'string', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        $option->update($validated);

        return response()->json([
            'data' => $option->fresh(),
        ]);
    }

    public function destroyOption(Request $request, Events $event, EventQuestionnaire $question, EventQuestionOption $option)
    {
        $this->assertAdmin($request);

        if ((string) $question->event_id !== (string) $event->event_id) {
            abort(404);
        }

        if ((string) $option->event_questionnaire_id !== (string) $question->event_questionnaire_id) {
            abort(404);
        }

        $option->update([
            'is_active' => false,
        ]);

        return response()->json([
            'ok' => true,
        ]);
    }

    private function ensureYesNoOptions(EventQuestionnaire $question): void
    {
        $existingCount = EventQuestionOption::query()
            ->where('event_questionnaire_id', $question->event_questionnaire_id)
            ->count();

        if ($existingCount > 0) {
            return;
        }

        EventQuestionOption::create([
            'event_questionnaire_id' => $question->event_questionnaire_id,
            'option_label' => 'Yes',
            'option_value' => 'yes',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        EventQuestionOption::create([
            'event_questionnaire_id' => $question->event_questionnaire_id,
            'option_label' => 'No',
            'option_value' => 'no',
            'sort_order' => 2,
            'is_active' => true,
        ]);
    }

    private function assertAdmin(Request $request): void
    {
        if ((string) $request->user()->role !== 'admin') {
            abort(403);
        }
    }
}
