<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->enum('account', ['fk', 'ai_preneur'])->default('fk'); // ACCOUNT
            $table->string('coaching')->nullable();                        // COACHING
            $table->string('speaker')->nullable();                         // SPEAKER
            $table->string('endorse');                                     // ENDORSE
            $table->enum('progress', ['editing', 'progress', 'done'])->default('progress'); // PROGRESS
            $table->date('tanggal_posting')->nullable();                   // TANGGAL POSTING
            $table->date('tanggal_payment')->nullable();                   // TANGGAL PAYMENT
            $table->enum('payment_status', ['belum', 'dp', 'lunas'])->default('belum'); // SUDAH/BELUM PAYMENT
            $table->decimal('amount_idr', 15, 2)->nullable();              // JUMLAH PAYMENT IDR
            $table->decimal('amount_usd', 12, 2)->nullable();              // JUMLAH PAYMENT USD
            $table->text('notes')->nullable();                             // NOTES
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipelines');
    }
};
