<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function addCredits(User $user, int $amount, string $type, $reference = null, string $transaction_description = '')
    {
        return DB::transaction(function () use ($user, $amount, $type, $reference, $transaction_description) {
            // 1. Create Ledger Entry
            $user->creditTransactions()->create([
                'credit_amount' => $amount,
                'transaction_type' => $type,
                'reference_id' => $reference?->id,
                'reference_type' => $reference ? get_class($reference) : null,
                'transaction_description' => $transaction_description,
            ]);

            // 2. Update Cached Balance
            $user->increment('credit_balance', $amount);
        });
    }

    public function deductCredits(User $user, int $amount, string $type, $reference = null, string $transaction_description = '')
    {
        if ($user->credit_balance < $amount) {
            throw new \Exception("Insufficient credit balance.");
        }

        return DB::transaction(function () use ($user, $amount, $type, $reference, $transaction_description) {
            $user->creditTransactions()->create([
                'credit_amount' => -$amount,
                'transaction_type' => $type,
                'reference_id' => $reference?->id,
                'reference_type' => $reference ? get_class($reference) : null,
                'transaction_description' => $transaction_description,
            ]);

            $user->decrement('credit_balance', $amount);
        });
    }
}
