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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->enum('type',['depot','retrait','transfert']);
            $table->decimal('amount',15,2);
            $table->decimal('solde_avant',15,2);
            $table->decimal('solde_apres',15,2);
            $table->text('description')->nullable();
            $table->enum('status',['en_attente','validee','echouee'])->default('en_attente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
