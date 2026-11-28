<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_items')) {
            Schema::table('asset_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('asset_items', 'qr_printed_at')) {
                    $table->timestamp('qr_printed_at')->nullable()->after('installed_note')->index();
                }

                if (! Schema::hasColumn('asset_items', 'qr_print_count')) {
                    $table->unsignedInteger('qr_print_count')->default(0)->after('qr_printed_at');
                }
            });
        }

        if (Schema::hasTable('bulk_stocks')) {
            Schema::table('bulk_stocks', function (Blueprint $table): void {
                if (! Schema::hasColumn('bulk_stocks', 'qr_printed_at')) {
                    $table->timestamp('qr_printed_at')->nullable()->after('notes')->index();
                }

                if (! Schema::hasColumn('bulk_stocks', 'qr_print_count')) {
                    $table->unsignedInteger('qr_print_count')->default(0)->after('qr_printed_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('asset_items')) {
            Schema::table('asset_items', function (Blueprint $table): void {
                if (Schema::hasColumn('asset_items', 'qr_print_count')) {
                    $table->dropColumn('qr_print_count');
                }

                if (Schema::hasColumn('asset_items', 'qr_printed_at')) {
                    $table->dropIndex(['qr_printed_at']);
                    $table->dropColumn('qr_printed_at');
                }
            });
        }

        if (Schema::hasTable('bulk_stocks')) {
            Schema::table('bulk_stocks', function (Blueprint $table): void {
                if (Schema::hasColumn('bulk_stocks', 'qr_print_count')) {
                    $table->dropColumn('qr_print_count');
                }

                if (Schema::hasColumn('bulk_stocks', 'qr_printed_at')) {
                    $table->dropIndex(['qr_printed_at']);
                    $table->dropColumn('qr_printed_at');
                }
            });
        }
    }
};
