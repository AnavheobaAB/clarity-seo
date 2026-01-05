<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // facebook, google, bing, yelp
            $table->string('external_id')->nullable(); // Platform's ID for this listing
            $table->string('status')->default('pending'); // pending, active, synced, error
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->json('categories')->nullable();
            $table->json('business_hours')->nullable();
            $table->text('description')->nullable();
            $table->json('attributes')->nullable(); // Platform-specific attributes
            $table->json('photos')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('discrepancies')->nullable(); // Mismatches between local and platform data
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_published_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'platform']);
            $table->index(['platform', 'status']);
            $table->index('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
