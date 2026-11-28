<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_consumables')) {
            Schema::create('procurement_consumables', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('procurement_id')->constrained('procurements')->cascadeOnDelete();
                $table->foreignId('consumable_id')->constrained('consumables')->restrictOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->unsignedBigInteger('unit_price')->nullable();
                $table->string('notes', 255)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('consumable_transactions')) {
            Schema::table('consumable_transactions', function (Blueprint $table): void {
                if (! Schema::hasColumn('consumable_transactions', 'procurement_id')) {
                    $table->foreignId('procurement_id')
                        ->nullable()
                        ->after('consumable_id')
                        ->constrained('procurements')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('consumable_transactions') && Schema::hasColumn('consumable_transactions', 'procurement_id')) {
            Schema::table('consumable_transactions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('procurement_id');
            });
        }

        Schema::dropIfExists('procurement_consumables');
    }
};
