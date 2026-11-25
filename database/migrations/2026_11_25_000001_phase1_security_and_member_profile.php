<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('must_change_password')->default(false)->after('is_active');
            });
        }

        Schema::table('members', function (Blueprint $table): void {
            if (! Schema::hasColumn('members', 'member_type')) {
                $table->string('member_type', 20)->default('student')->after('group');
            }

            if (! Schema::hasColumn('members', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('member_type');
            }
        });

        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 80)->index();
                $table->string('subject_type', 120)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('description', 255);
                $table->json('properties')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent()->index();

                $table->index(['subject_type', 'subject_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');

        Schema::table('members', function (Blueprint $table): void {
            if (Schema::hasColumn('members', 'birth_date')) {
                $table->dropColumn('birth_date');
            }
            if (Schema::hasColumn('members', 'member_type')) {
                $table->dropColumn('member_type');
            }
        });

        if (Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('must_change_password');
            });
        }
    }
};
