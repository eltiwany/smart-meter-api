<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmartSchedulersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('smart_schedulers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_sensor_id')->constrained('user_sensors')->onUpdate('cascade')->onDelete('cascade');
            $table->time('from_time');
            $table->time('to_time');
            $table->boolean('is_switched_on')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('smart_schedulers');
    }
}
