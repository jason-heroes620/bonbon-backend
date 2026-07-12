<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('compartment_stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('compartment_stocks', 'confirmed_received_at')) {
                $table->dateTime('confirmed_received_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('compartment_stocks', 'confirmed_received_by_user_id')) {
                $table->uuid('confirmed_received_by_user_id')->nullable()->after('confirmed_received_at');
                $table->index('confirmed_received_by_user_id', 'idx_compartment_stocks_confirmed_by');
            }

            if (!Schema::hasColumn('compartment_stocks', 'confirmation_source')) {
                $table->enum('confirmation_source', ['merchant_qr'])->nullable()->after('confirmed_received_by_user_id');
            }
        });

        if (!Schema::hasTable('compartment_stock_qr_sessions')) {
            Schema::create('compartment_stock_qr_sessions', function (Blueprint $table) {
                $table->uuid('compartment_stock_qr_session_id')->primary();
                $table->uuid('compartment_stock_id');
                $table->uuid('compartment_stock_product_id');
                $table->uuid('vendor_id');
                $table->uuid('rack_owner_vendor_id');
                $table->uuid('generated_by_user_id');
                $table->uuid('nonce')->unique('uq_qr_sessions_nonce');
                $table->json('payload_json');
                $table->string('signature_hash', 64)->unique('uq_qr_sessions_signature_hash');
                $table->dateTime('issued_at');
                $table->dateTime('expires_at');
                $table->dateTime('scanned_at')->nullable();
                $table->uuid('scanned_by_user_id')->nullable();
                $table->dateTime('consumed_at')->nullable();
                $table->uuid('consumed_by_user_id')->nullable();
                $table->enum('status', ['active', 'expired', 'consumed', 'revoked'])->default('active');
                $table->timestamps();

                $table->index(
                    ['compartment_stock_product_id', 'status'],
                    'idx_qr_sessions_stock_product_status'
                );
                $table->index(
                    ['vendor_id', 'status', 'expires_at'],
                    'idx_qr_sessions_vendor_status_expiry'
                );
                $table->index(
                    ['rack_owner_vendor_id', 'status', 'expires_at'],
                    'idx_qr_sessions_owner_status_expiry'
                );
                $table->index('expires_at', 'idx_qr_sessions_expires_at');
                $table->index('consumed_at', 'idx_qr_sessions_consumed_at');
                $table->index(
                    ['scanned_by_user_id', 'scanned_at'],
                    'idx_qr_sessions_scanned_by'
                );
            });
        }

        Schema::table('compartment_stock_product_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('compartment_stock_product_transactions', 'compartment_stock_qr_session_id')) {
                $table->uuid('compartment_stock_qr_session_id')->nullable()->after('compartment_stock_product_transaction_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'compartment_stock_id')) {
                $table->uuid('compartment_stock_id')->nullable()->after('compartment_stock_qr_session_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'compartment_stock_product_id')) {
                $table->uuid('compartment_stock_product_id')->nullable()->after('compartment_stock_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'vendor_id')) {
                $table->uuid('vendor_id')->nullable()->after('compartment_stock_product_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'rack_owner_vendor_id')) {
                $table->uuid('rack_owner_vendor_id')->nullable()->after('vendor_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'generated_by_user_id')) {
                $table->uuid('generated_by_user_id')->nullable()->after('rack_owner_vendor_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'received_by_user_id')) {
                $table->uuid('received_by_user_id')->nullable()->after('generated_by_user_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'transaction_type')) {
                $table->enum('transaction_type', ['receive_confirmation'])
                    ->default('receive_confirmation')
                    ->after('received_by_user_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'transaction_status')) {
                $table->enum('transaction_status', ['confirmed', 'rejected', 'expired'])
                    ->default('confirmed')
                    ->after('transaction_type');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'prepared_quantity')) {
                $table->integer('prepared_quantity')->nullable()->after('transaction_status');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'received_quantity')) {
                $table->integer('received_quantity')->nullable()->after('prepared_quantity');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'confirmed_at')) {
                $table->dateTime('confirmed_at')->nullable()->after('description');
            }
        });

        Schema::table('compartment_stock_product_transactions', function (Blueprint $table) {
            $table->unique('compartment_stock_qr_session_id', 'uq_cspt_qr_session');
            $table->index(
                ['compartment_stock_product_id', 'transaction_status'],
                'idx_cspt_stock_product_status'
            );
            $table->index(['vendor_id', 'confirmed_at'], 'idx_cspt_vendor_confirmed_at');
            $table->index(
                ['rack_owner_vendor_id', 'confirmed_at'],
                'idx_cspt_owner_confirmed_at'
            );
            $table->index(
                ['received_by_user_id', 'confirmed_at'],
                'idx_cspt_received_by_confirmed_at'
            );
            $table->index(
                ['transaction_type', 'transaction_status', 'confirmed_at'],
                'idx_cspt_type_status_confirmed_at'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compartment_stock_product_transactions', function (Blueprint $table) {
            $table->dropUnique('uq_cspt_qr_session');
            $table->dropIndex('idx_cspt_stock_product_status');
            $table->dropIndex('idx_cspt_vendor_confirmed_at');
            $table->dropIndex('idx_cspt_owner_confirmed_at');
            $table->dropIndex('idx_cspt_received_by_confirmed_at');
            $table->dropIndex('idx_cspt_type_status_confirmed_at');
        });

        Schema::table('compartment_stock_product_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'compartment_stock_qr_session_id',
                'compartment_stock_id',
                'compartment_stock_product_id',
                'vendor_id',
                'rack_owner_vendor_id',
                'generated_by_user_id',
                'received_by_user_id',
                'transaction_type',
                'transaction_status',
                'prepared_quantity',
                'received_quantity',
                'confirmed_at',
            ]);
        });

        if (Schema::hasTable('compartment_stock_qr_sessions')) {
            Schema::dropIfExists('compartment_stock_qr_sessions');
        }

        Schema::table('compartment_stocks', function (Blueprint $table) {
            $table->dropIndex('idx_compartment_stocks_confirmed_by');
            $table->dropColumn([
                'confirmed_received_at',
                'confirmed_received_by_user_id',
                'confirmation_source',
            ]);
        });
    }
};
