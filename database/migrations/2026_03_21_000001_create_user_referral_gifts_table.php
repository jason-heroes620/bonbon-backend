<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_referral_gifts', function (Blueprint $table) {
            $table->uuid('user_referral_gift_id')->primary();
            $table->uuid('user_id')->index();
            $table->dateTime('earned_at');
            $table->dateTime('claimed_at')->nullable();
            $table->timestamps();
        });

        if (!Schema::hasColumn('users', 'referral_gifts_earned') || !Schema::hasColumn('users', 'referral_gifts_claimed')) {
            return;
        }

        $now = now();
        DB::table('users')
            ->select(['user_id', 'referral_gifts_earned', 'referral_gifts_claimed'])
            ->orderBy('user_id')
            ->chunk(200, function ($users) use ($now) {
                $rows = [];

                foreach ($users as $user) {
                    $earned = (int) ($user->referral_gifts_earned ?? 0);
                    $claimed = (int) ($user->referral_gifts_claimed ?? 0);
                    if ($earned <= 0 && $claimed <= 0) {
                        continue;
                    }

                    $claimed = min($claimed, $earned);
                    $unclaimed = max(0, $earned - $claimed);

                    for ($i = 0; $i < $claimed; $i++) {
                        $rows[] = [
                            'user_referral_gift_id' => (string) Str::uuid(),
                            'user_id' => $user->user_id,
                            'earned_at' => $now,
                            'claimed_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    for ($i = 0; $i < $unclaimed; $i++) {
                        $rows[] = [
                            'user_referral_gift_id' => (string) Str::uuid(),
                            'user_id' => $user->user_id,
                            'earned_at' => $now,
                            'claimed_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (!empty($rows)) {
                    DB::table('user_referral_gifts')->insert($rows);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'referral_gifts_earned')) {
                $table->dropColumn('referral_gifts_earned');
            }

            if (Schema::hasColumn('users', 'referral_gifts_claimed')) {
                $table->dropColumn('referral_gifts_claimed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'referral_gifts_earned')) {
                $table->unsignedTinyInteger('referral_gifts_earned')->default(0);
            }
            if (!Schema::hasColumn('users', 'referral_gifts_claimed')) {
                $table->unsignedTinyInteger('referral_gifts_claimed')->default(0);
            }
        });

        if (Schema::hasTable('user_referral_gifts')) {
            $earnedByUser = DB::table('user_referral_gifts')
                ->selectRaw('user_id, COUNT(*) as earned_count')
                ->groupBy('user_id')
                ->pluck('earned_count', 'user_id')
                ->all();

            $claimedByUser = DB::table('user_referral_gifts')
                ->whereNotNull('claimed_at')
                ->selectRaw('user_id, COUNT(*) as claimed_count')
                ->groupBy('user_id')
                ->pluck('claimed_count', 'user_id')
                ->all();

            foreach ($earnedByUser as $userId => $earnedCount) {
                DB::table('users')
                    ->where('user_id', $userId)
                    ->update([
                        'referral_gifts_earned' => (int) $earnedCount,
                        'referral_gifts_claimed' => (int) ($claimedByUser[$userId] ?? 0),
                    ]);
            }

            Schema::dropIfExists('user_referral_gifts');
        }
    }
};
