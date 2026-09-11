<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedTinyInteger('max_guests');
            $table->unsignedBigInteger('price');
            $table->char('currency', 3);
            $table->unsignedInteger('available_units');
            $table->dateTime('expires_at');
            $table->dateTime('sent_at');
            $table->timestamps();

            $table->unique(['supplier_id', 'external_id']);
            $table->index(['check_in', 'check_out', 'property_id', 'price'], 'offers_search_idx');
            $table->index(['property_id', 'check_in', 'check_out', 'price'], 'offers_property_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
