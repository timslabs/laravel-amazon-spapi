<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tims\AmazonSpApi\Enums\Region;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spapi_credentials', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('selling_partner_id')->unique();
            $table->enum('region', Region::values());
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('refresh_token');
            $table->foreignId('seller_id')->constrained('spapi_sellers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spapi_credentials');
    }
};
