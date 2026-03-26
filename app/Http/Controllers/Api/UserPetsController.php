<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserPetsController extends Controller
{
    private function toResponseRow(UserPets $pet): array
    {
        $image = $pet->pet_image;
        $imageUrl = null;
        if (!empty($image)) {
            if (
                str_starts_with($image, '/storage/')
                || str_starts_with($image, 'http://')
                || str_starts_with($image, 'https://')
                || str_starts_with($image, 'file://')
            ) {
                $imageUrl = $image;
            } else {
                $imageUrl = Storage::url($image);
            }
        }

        return [
            'id' => $pet->id,
            'user_id' => $pet->user_id,
            'name' => $pet->pet_name,
            'type' => $pet->pet_type,
            'breed' => $pet->pet_breed,
            'birthDate' => $pet->pet_birth_date,
            'medical_notes' => $pet->medical_notes,
            'allergy_notes' => $pet->allergy_notes,
            'image_url' => $imageUrl,
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $rows = UserPets::query()
            ->where('user_id', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $rows->map(fn($p) => $this->toResponseRow($p))->values(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $pet = UserPets::query()
            ->where('user_id', $user->user_id)
            ->where('id', $id)
            ->first();

        if (!$pet) {
            return response()->json(['message' => 'Pet not found.'], 404);
        }

        return response()->json([
            'data' => $this->toResponseRow($pet),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'breed' => ['nullable', 'string', 'max:150'],
            'birthDate' => ['nullable', 'date'],
            'medical_notes' => ['nullable', 'string'],
            'allergy_notes' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $pet = UserPets::create([
            'user_id' => $user->user_id,
            'pet_name' => $validated['name'],
            'pet_type' => $validated['type'] ?? null,
            'pet_breed' => $validated['breed'] ?? null,
            'pet_birth_date' => $validated['birthDate'] ?? null,
            'medical_notes' => $validated['medical_notes'] ?? null,
            'allergy_notes' => $validated['allergy_notes'] ?? null,
            'pet_image' => $validated['image_url'] ?? null,
        ]);

        return response()->json([
            'data' => $this->toResponseRow($pet),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'breed' => ['nullable', 'string', 'max:150'],
            'birthDate' => ['nullable', 'date'],
            'medical_notes' => ['nullable', 'string'],
            'allergy_notes' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $pet = UserPets::query()
            ->where('user_id', $user->user_id)
            ->where('id', $id)
            ->first();

        if (!$pet) {
            return response()->json(['message' => 'Pet not found.'], 404);
        }

        $updates = [];

        if (array_key_exists('name', $validated)) {
            $updates['pet_name'] = $validated['name'];
        }
        if (array_key_exists('type', $validated)) {
            $updates['pet_type'] = $validated['type'];
        }
        if (array_key_exists('breed', $validated)) {
            $updates['pet_breed'] = $validated['breed'];
        }
        if (array_key_exists('birthDate', $validated)) {
            $updates['pet_birth_date'] = $validated['birthDate'];
        }
        if (array_key_exists('medical_notes', $validated)) {
            $updates['medical_notes'] = $validated['medical_notes'];
        }
        if (array_key_exists('allergy_notes', $validated)) {
            $updates['allergy_notes'] = $validated['allergy_notes'];
        }
        if (array_key_exists('image_url', $validated)) {
            $updates['pet_image'] = $validated['image_url'];
        }

        if (!empty($updates)) {
            $pet->update($updates);
        }

        return response()->json([
            'data' => $this->toResponseRow($pet->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        $pet = UserPets::query()
            ->where('user_id', $user->user_id)
            ->where('id', $id)
            ->first();

        if (!$pet) {
            return response()->json(['message' => 'Pet not found.'], 404);
        }

        $pet->delete();

        return response()->json([
            'message' => 'Pet deleted.',
        ]);
    }
}
