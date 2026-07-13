<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spapi_sellers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spapi_sellers');
    }
};
