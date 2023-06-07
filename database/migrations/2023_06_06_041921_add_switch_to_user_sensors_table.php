<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSwitchToUserSensorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_sensors', function (Blueprint $table) {
            $table->boolean('is_switched_on')->default(false);
            $table->boolean('is_active_low')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_sensors', function (Blueprint $table) {
            $table->dropColumn('is_switched_on');
            $table->dropColumn('is_active_low');
        });
    }
}
