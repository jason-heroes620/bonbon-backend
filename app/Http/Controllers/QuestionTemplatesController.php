<?php

namespace App\Http\Controllers;

use App\Models\EventQuestionOption;
use App\Models\QuestionTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QuestionTemplatesController extends Controller
{
    public function index(Request $request)
    {
        $this->assertAdmin($request);

        return Inertia::render('question-templates/question-templates');
    }

    public function showAll(Request $request)
    {
        $this->assertAdmin($request);

        $query = QuestionTemplate::query();
        $query->with(['options' => function ($q) {
            $q->orderBy('sort_order');
        }]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('question_label', 'like', "%{$search}%")
                    ->orWhere('question_type', 'like', "%{$search}%");
            });
        }

        if ($request->has('filters')) {
            foreach ($request->filters as $column => $value) {
                if ($value !== null && $value !== '') {
                    $query->where($column, $value);
                }
            }
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('question_templates.created_at', 'desc');
        }

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

    public function list(Request $request)
    {
        $this->assertAdmin($request);

        $items = QuestionTemplate::query()
            ->where('is_active', true)
            ->with(['options' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }])
            ->orderBy('question_label', 'asc')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function create(Request $request)
    {
        $this->assertAdmin($request);

        return Inertia::render('question-templates/create');
    }

    public function store(Request $request)
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'question_label' => ['required', 'string', 'max:255'],
            'question_help_text' => ['nullable', 'string'],
            'question_type' => ['required', 'in:short_text,long_text,single_select,multi_select,yes_no'],
            'is_required_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.*.option_label' => ['required_with:options', 'string', 'max:255'],
            'options.*.option_value' => ['required_with:options', 'string', 'max:100'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'options.*.is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $template = QuestionTemplate::create([
                'question_label' => $validated['question_label'],
                'question_help_text' => $validated['question_help_text'] ?? null,
                'question_type' => $validated['question_type'],
                'is_required_default' => $validated['is_required_default'],
                'is_active' => $validated['is_active'],
                'created_by_user_id' => $request->user()->user_id,
            ]);

            $this->syncOptions($template, $validated);
        });

        return redirect()->route('question_templates.index')->with([
            'success' => 'Question template created successfully',
        ]);
    }

    public function edit(Request $request, QuestionTemplate $questionTemplate)
    {
        $this->assertAdmin($request);

        return Inertia::render('question-templates/edit', [
            'questionTemplate' => $questionTemplate->load(['options' => function ($q) {
                $q->orderBy('sort_order');
            }]),
        ]);
    }

    public function update(Request $request, QuestionTemplate $questionTemplate)
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'question_label' => ['required', 'string', 'max:255'],
            'question_help_text' => ['nullable', 'string'],
            'question_type' => ['required', 'in:short_text,long_text,single_select,multi_select,yes_no'],
            'is_required_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.*.option_label' => ['required_with:options', 'string', 'max:255'],
            'options.*.option_value' => ['required_with:options', 'string', 'max:100'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'options.*.is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($questionTemplate, $validated) {
            $questionTemplate->update([
                'question_label' => $validated['question_label'],
                'question_help_text' => $validated['question_help_text'] ?? null,
                'question_type' => $validated['question_type'],
                'is_required_default' => $validated['is_required_default'],
                'is_active' => $validated['is_active'],
            ]);

            $this->syncOptions($questionTemplate, $validated);
        });

        return redirect()->route('question_templates.index')->with([
            'success' => 'Question template updated successfully',
        ]);
    }

    public function destroy(Request $request, QuestionTemplate $questionTemplate)
    {
        $this->assertAdmin($request);

        $questionTemplate->update([
            'is_active' => false,
        ]);

        return redirect()->route('question_templates.index')->with([
            'success' => 'Question template deactivated successfully',
        ]);
    }

    private function assertAdmin(Request $request): void
    {
        if ((string) $request->user()->role !== 'admin') {
            abort(403);
        }
    }

    private function syncOptions(QuestionTemplate $template, array $validated): void
    {
        $supportsOptions = in_array(
            (string) $validated['question_type'],
            ['single_select', 'multi_select'],
            true
        );

        $submittedOptions = collect($validated['options'] ?? [])
            ->map(function (array $option, int $index) {
                return [
                    'option_label' => trim((string) ($option['option_label'] ?? '')),
                    'option_value' => trim((string) ($option['option_value'] ?? '')),
                    'sort_order' => (int) ($option['sort_order'] ?? ($index + 1)),
                    'is_active' => array_key_exists('is_active', $option)
                        ? (bool) $option['is_active']
                        : true,
                ];
            })
            ->filter(function (array $option) {
                return $option['option_label'] !== '' && $option['option_value'] !== '';
            })
            ->values();

        if ($supportsOptions && $submittedOptions->isEmpty()) {
            abort(422, 'At least one option is required for select questions.');
        }

        EventQuestionOption::query()
            ->where('question_template_id', $template->question_template_id)
            ->delete();

        if (!$supportsOptions) {
            return;
        }

        foreach ($submittedOptions as $option) {
            EventQuestionOption::create([
                'event_questionnaire_id' => null,
                'question_template_id' => $template->question_template_id,
                'option_label' => $option['option_label'],
                'option_value' => $option['option_value'],
                'sort_order' => $option['sort_order'] > 0 ? $option['sort_order'] : 1,
                'is_active' => $option['is_active'],
            ]);
        }
    }
}
