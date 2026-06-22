<?php

namespace App\Http\Controllers;

use App\Models\Compartments;
use App\Models\Racks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompartmentController extends Controller
{
    public function edit(Racks $rack)
    {
        $compartments = Compartments::query()
            ->where('rack_id', $rack->rack_id)
            ->where('is_active', true)
            ->orderBy('row_index', 'asc')
            ->orderBy('column_index', 'asc')
            ->get([
                'compartment_id',
                'rack_id',
                'label',
                'row_index',
                'column_index',
                'size_dimensions',
                'min_price',
                'min_month',
                'compartment_status',
                'is_active',
            ]);

        return Inertia::render('racks-tenders/compartments/edit', [
            'rack' => $rack,
            'compartments' => $compartments,
        ]);
    }

    public function update(Request $request, Racks $rack)
    {
        $validated = $request->validate([
            'compartments' => ['required', 'array'],
            'compartments.*.compartment_id' => ['required', 'uuid', 'exists:compartments,compartment_id'],
            'compartments.*.min_price' => ['required', 'numeric', 'min:0'],
            'compartments.*.min_month' => ['required', 'integer', 'min:1'],
            'compartments.*.size_dimensions' => ['nullable', 'string', 'max:255'],
            'compartments.*.allocated' => ['nullable', 'boolean'],
        ]);

        $items = $validated['compartments'];
        $ids = collect($items)->pluck('compartment_id')->filter()->unique()->values()->all();

        $validForRack = Compartments::query()
            ->where('rack_id', $rack->rack_id)
            ->whereIn('compartment_id', $ids)
            ->count();

        if ($validForRack !== count($ids)) {
            return back()->with([
                'error' => 'Invalid compartments payload.',
            ]);
        }

        DB::transaction(function () use ($items, $rack) {
            $existingStatuses = Compartments::query()
                ->where('rack_id', $rack->rack_id)
                ->whereIn('compartment_id', collect($items)->pluck('compartment_id')->all())
                ->pluck('compartment_status', 'compartment_id');

            foreach ($items as $item) {
                $compartmentId = (string) $item['compartment_id'];
                $currentStatus = (string) ($existingStatuses[$compartmentId] ?? '');
                $allocated = array_key_exists('allocated', $item) ? (bool) $item['allocated'] : null;

                $nextStatus = $currentStatus;
                if ($allocated === true) {
                    $nextStatus = 'allocated';
                } elseif ($allocated === false && $currentStatus === 'allocated') {
                    $nextStatus = 'open';
                }

                Compartments::query()
                    ->where('rack_id', $rack->rack_id)
                    ->where('compartment_id', $compartmentId)
                    ->update([
                        'min_price' => $item['min_price'],
                        'min_month' => $item['min_month'],
                        'size_dimensions' => $item['size_dimensions'] ?? null,
                        'compartment_status' => $nextStatus,
                    ]);
            }
        });

        return redirect()->route('racks.compartments.edit', $rack->rack_id)->with([
            'success' => 'Compartments updated successfully',
        ]);
    }
}
