<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 60);
            $table->string('description', 500);
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('event');
            $table->index('created_at');
        });

        // Tabel notifikasi standar Laravel (database channel).
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('old_purchase_price')->nullable();
            $table->unsignedBigInteger('new_purchase_price')->nullable();
            $table->unsignedBigInteger('old_selling_price')->nullable();
            $table->unsignedBigInteger('new_selling_price')->nullable();
            $table->string('source', 30)->default('manual');
            $table->timestamps();
        });

        // Temuan anomali: hanya penanda untuk ditinjau manusia, tidak pernah mengubah transaksi.
        Schema::create('anomaly_flags', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 120)->unique();
            $table->string('type', 40);
            $table->string('severity', 10)->default('medium');
            $table->string('title');
            $table->text('detail');
            $table->nullableMorphs('subject');
            $table->json('context')->nullable();
            $table->string('status', 20)->default('open'); // open|reviewed|dismissed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomaly_flags');
        Schema::dropIfExists('price_histories');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
    }
};
