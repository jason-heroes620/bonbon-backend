<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_pricing_rules', function (Blueprint $table) {
            $table->uuid('event_pricing_rule_id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('membership_type_id')->nullable()->index();
            $table->enum('pricing_rule_type', ['discount_percent', 'discount_fixed', 'final_price'])->index();
            $table->decimal('pricing_value', 10, 2);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['event_id', 'membership_type_id', 'is_active'], 'idx_epr_event_membership_active');
            $table->index(['event_id', 'is_active'], 'idx_epr_event_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_pricing_rules');
    }
};

