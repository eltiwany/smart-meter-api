<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdditionalInformationsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number', 15)->nullable();
            $table->string('city', 200)->nullable();
            $table->string('region', 200)->nullable();
            $table->string('district', 200)->nullable();
            $table->string('house_number', 50)->nullable();
            $table->string('residence_id', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_number');
            $table->dropColumn('city');
            $table->dropColumn('region');
            $table->dropColumn('district');
            $table->dropColumn('house_number');
            $table->dropColumn('residence_id');
        });
    }
}
