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
        Schema::create('excel_audio_logs', function (Blueprint $table) {
            $table->id();
            $table->date('accounting_day')->format('m.d.Y');
            $table->integer('order_no');
            $table->dateTime('time')->format('m.d.Y H:i:s');
            $table->time('precek');
            $table->time('closed');
            $table->string('waiter');
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excel_audio_logs');
    }
};
