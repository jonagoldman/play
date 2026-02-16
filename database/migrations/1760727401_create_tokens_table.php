<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignUlid('user_id')->index()->constrained();
            $table->string('name')->nullable();
            $table->string('type');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }
};
