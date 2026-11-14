<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('status', 32)->default('new');
            $table->timestamps();
            $table->timestamp('resolved_at')->nullable();

            $table->index(['community_id', 'status']);
            $table->index(['community_id', 'created_at']);
            $table->index(['community_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
