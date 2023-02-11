<?php

namespace Database\Seeders;

use App\Models\Sensor;
use App\Models\UserBoard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AutoSensorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user_board_token = "J7UbqPLTdeTXXc3T";
        $user_board = UserBoard::where('token', $user_board_token)->first();

        if ($user_board) {
            $data = [];

            for ($sensors=0; $sensors < 6; $sensors++) {
                $user_id = $user_board->user_id;
                $user_board_id = $user_board->id;
                $sensor_id = Sensor::where('name', 'like', '%Smart Plug%')->first()->id;
                $interval = 15;
                $name = "Smart Plug " . rand(1000, 9999);
                $auto_added = true;

                array_push($data, [
                    "user_id" => $user_id,
                    "sensor_id" => $sensor_id,
                    "user_board_id" => $user_board_id,
                    "interval" => $interval,
                    "name" => $name,
                    "auto_added" => $auto_added
                ]);
            }

            DB::table('user_sensors')->insert($data);
        }
    }
}
