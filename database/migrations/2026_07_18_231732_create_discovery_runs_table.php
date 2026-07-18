<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('ran_at');
            $table->string('driver');
            $table->unsignedInteger('candidates_found')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_runs');
    }
};
