<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_types', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_types', 'transaction_amount') && !Schema::hasColumn('transaction_types', 'credit_amount')) {
                $table->renameColumn('transaction_amount', 'credit_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaction_types', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_types', 'credit_amount') && !Schema::hasColumn('transaction_types', 'transaction_amount')) {
                $table->renameColumn('credit_amount', 'transaction_amount');
            }
        });
    }
};

