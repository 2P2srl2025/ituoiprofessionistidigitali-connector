<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            // Null while the proposal is a draft, a new one at every sending
            $table->uuid('uuid')->nullable()->unique();
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('assignment_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained();
            $table->string('status')->default('open');
            $table->unsignedInteger('minutes')->nullable();
            $table->timestamps();
        });
    }
};
