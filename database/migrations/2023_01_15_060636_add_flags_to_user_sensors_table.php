<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFlagsToUserSensorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_sensors', function (Blueprint $table) {
            $table->float('threshold')->nullable();
            $table->float('threshold_percentage')->nullable();
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
            $table->removeColumn('threshold');
            $table->removeColumn('threshold_percentage');
        });
    }
}
