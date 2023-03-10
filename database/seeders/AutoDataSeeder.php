<?php

namespace Database\Seeders;

use App\Models\Sensor;
use App\Models\SensorColumn;
use App\Models\UserBoard;
use App\Models\UserSensor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AutoDataSeeder extends Seeder
{
    // Find a randomDate between $start_date and $end_date
    function randomDate($start_date, $end_date)
    {
        // Convert to timetamps
        $min = strtotime($start_date);
        $max = strtotime($end_date);

        // Generate random number using above bounds
        $val = rand($min, $max);

        // Convert back to desired date format
        return date('Y-m-d H:i:s', $val);
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user_board_token = "J7UbqPLTdeTXXc3T";
        $random_values_per_sensor = 1000;

        $user_board = UserBoard::where('token', $user_board_token)->first();

        if ($user_board) {
            $data = [];

            $sensor_id = Sensor::where('name', 'like', '%Smart Plug%')->first()->id;

            $sensor_columns = SensorColumn::where('sensor_id', $sensor_id)->pluck('id')->toArray();
            $user_sensors = UserSensor::where('name', 'like', '%Smart Plug%')->get();

            foreach ($user_sensors as $user_sensor) {
                for ($ran=0; $ran < $random_values_per_sensor; $ran++) {
                    $date = $this->randomDate('2023-01-25', '2023-02-15');

                    // Voltage
                    array_push($data, [
                        'user_sensor_id' => $user_sensor->id,
                        'sensor_column_id' => $sensor_columns[0],
                        'value' => rand(199, 230),
                        'created_at' => $date,
                        'updated_at' => $date
                    ]);

                    // Current
                    array_push($data, [
                        'user_sensor_id' => $user_sensor->id,
                        'sensor_column_id' => $sensor_columns[1],
                        'value' => rand(2, 25),
                        'created_at' => $date,
                        'updated_at' => $date
                    ]);
                }
            }

            DB::table('user_sensor_values')->insert($data);
        }
    }
}
