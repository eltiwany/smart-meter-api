<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use App\Models\Sensor;
use App\Models\SensorColumn;
use App\Models\UserBoard;
use App\Models\UserSensor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SeedController extends ResponsesController
{
    private $start_date;
    private $end_date;
    private $minVoltage = 219;
    private $maxVoltage = 222;
    private $minCurrent = 2;
    private $maxCurrent = 4;

    public function smartMeter(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $token = $request->get('token');
        $this->end_date = Carbon::now('GMT+3');
        $this->start_date = Carbon::now('GMT+3')->addDays(-30);

        $user_board = UserBoard::where('token', $token)->first();

        if ($user_board) {
            try {
                $this->generateSensors($user_board);
                // $this->generateSensorData($user_board);
                $this->generateSensorLosses($user_board);

                return $this->sendResponse([], "Successfully generated smart appliances and loss sensors for meter token: $token");
            } catch (Throwable $e) {
                return $this->sendError($e->getMessage(), [], 500);
            }
        }

        return $this->sendError('Smart meter with specified token was not found.', []);
    }

    public function generateSensors($user_board, $number_of_sensors = 6)
    {
        $data = [];
        if (!UserSensor::where('user_board_id', $user_board->id)->where('name', 'like', '%Smart Plug%')->exists()) {
            for ($sensors=0; $sensors < $number_of_sensors; $sensors++) {
                $user_id = $user_board->user_id;
                $user_board_id = $user_board->id;
                $sensor_id = Sensor::where('name', 'like', '%Smart Plug%')->first()->id;
                $interval = 15;
                $name = "Smart Plug " . mt_rand(1000, 9999);
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
        }

        DB::table('user_sensors')->insert($data);
    }

    public function generateSensorData($user_board, $random_values_per_sensor = 1000)
    {
        $data = [];

        $sensor_id = Sensor::where('name', 'like', '%Smart Plug%')->first()->id;

        $sensor_columns = SensorColumn::where('sensor_id', $sensor_id)->pluck('id')->toArray();
        $user_sensors = UserSensor::whereHas('sensor', function($query) {
            $query->where('name', 'like', '%Smart Plug%');
        })->where('user_board_id', $user_board->id)->get();

        foreach ($user_sensors as $user_sensor) {
            for ($ran=0; $ran < $random_values_per_sensor; $ran++) {
                $date = $this->randomDate($this->start_date, $this->end_date);

                // Voltage
                array_push($data, [
                    'user_sensor_id' => $user_sensor->id,
                    'sensor_column_id' => $sensor_columns[0],
                    'value' => mt_rand($this->minVoltage, $this->maxVoltage),
                    'created_at' => $date,
                    'updated_at' => $date
                ]);

                // Current
                array_push($data, [
                    'user_sensor_id' => $user_sensor->id,
                    'sensor_column_id' => $sensor_columns[1],
                    'value' => mt_rand($this->minCurrent, $this->maxCurrent),
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
            }
        }

        DB::table('user_sensor_values')->insert($data);
    }

    public function importSensorData(Request $request)
    {
        $this->end_date = Carbon::now('GMT+3');
        $this->start_date = Carbon::now('GMT+3')->addDays(-7);

        $data = [];
        if (!$request->get('testData'))
            return $this->sendError('Test data on a file could be read.');

        $user_sensors = $request->get('testData');

        try {

            $sequencedDates = $this->generateDateSequence($this->start_date, $this->end_date, sizeof($user_sensors));
            // return $this->sendError($sequencedDates);

            foreach ($user_sensors as $user_sensor) {
                for ($ran=0; $ran < sizeof($user_sensors); $ran++) {
                    // $date = $this->randomDate($this->start_date, $this->end_date);

                    if ($user_sensor['VOLTAGE_COLUMN_ID'] != '-') {
                        // Voltage
                        array_push($data, [
                            'user_sensor_id' => $user_sensor['ID'],
                            'sensor_column_id' => $user_sensor['VOLTAGE_COLUMN_ID'],
                            'value' => $user_sensor['VOLTAGE'],
                            'created_at' => $sequencedDates[$ran],
                            'updated_at' => $sequencedDates[$ran]
                        ]);

                        // Current
                        array_push($data, [
                            'user_sensor_id' => $user_sensor['ID'],
                            'sensor_column_id' => $user_sensor['CURRENT_COLUMN_ID'],
                            'value' => $user_sensor['CURRENT'],
                            'created_at' => $sequencedDates[$ran],
                            'updated_at' => $sequencedDates[$ran]
                        ]);
                    }

                    if ($user_sensor['RESISTANCE_COLUMN_ID'] != '-') {
                        // Resistance
                        array_push($data, [
                            'user_sensor_id' => $user_sensor['ID'],
                            'sensor_column_id' => $user_sensor['RESISTANCE_COLUMN_ID'],
                            'value' => $user_sensor['RESISTANCE'],
                            'created_at' => $sequencedDates[$ran],
                            'updated_at' => $sequencedDates[$ran]
                        ]);
                    }
                }
            }

            DB::table('user_sensor_values')->insert($data);

            return $this->sendResponse([], 'Successfully imported test data');
        } catch (Throwable $e) {
            return $this->sendError($e->getMessage(), [], 500);
        }
    }

    public function generateSensorLosses($user_board, $random_values_per_sensor = 1000)
    {
        $data = [];

        if (!UserSensor::where('user_board_id', $user_board->id)->where('name', 'like', '%Loss Sensor%')->exists()) {
            $user_id = $user_board->user_id;
            $user_board_id = $user_board->id;
            $sensor_id = Sensor::where('name', 'like', '%Loss Sensor%')->first()->id;
            $interval = 60;
            $name = "Earthing Loss Sensor " . mt_rand(1000, 9999);
            $auto_added = true;

            array_push($data, [
                "user_id" => $user_id,
                "sensor_id" => $sensor_id,
                "user_board_id" => $user_board_id,
                "interval" => $interval,
                "name" => $name,
                "auto_added" => $auto_added
            ]);

            DB::table('user_sensors')->insert($data);
        }

        $data = [];
        if (!UserSensor::where('user_board_id', $user_board->id)->where('name', 'like', '%Loss Resistance Sensor%')->exists()) {
            $user_id = $user_board->user_id;
            $user_board_id = $user_board->id;
            $sensor_id = Sensor::where('name', 'like', '%Loss Resistance Sensor%')->first()->id;
            $interval = 60;
            $name = "Earthing Loss Resistance Sensor " . mt_rand(1000, 9999);
            $auto_added = true;

            array_push($data, [
                "user_id" => $user_id,
                "sensor_id" => $sensor_id,
                "user_board_id" => $user_board_id,
                "interval" => $interval,
                "name" => $name,
                "auto_added" => $auto_added
            ]);

            DB::table('user_sensors')->insert($data);
        }

        // $data = [];
        // $sensor_id = Sensor::where('name', 'like', '%Earthing Loss Sensor%')->first()->id;

        // $sensor_columns = SensorColumn::where('sensor_id', $sensor_id)->pluck('id')->toArray();
        // $user_sensors = UserSensor::whereHas('sensor', function($query) {
        //     $query->where('name', 'like', '%Earthing Loss Sensor%');
        // })->where('user_board_id', $user_board->id)->get();

        // foreach ($user_sensors as $user_sensor) {
        //     for ($ran=0; $ran < $random_values_per_sensor; $ran++) {
        //         $date = $this->randomDate($this->start_date, $this->end_date);

        //         // Voltage
        //         array_push($data, [
        //             'user_sensor_id' => $user_sensor->id,
        //             'sensor_column_id' => $sensor_columns[0],
        //             'value' => mt_rand($this->minVoltage, $this->maxVoltage),
        //             'created_at' => $date,
        //             'updated_at' => $date
        //         ]);

        //         // Current
        //         array_push($data, [
        //             'user_sensor_id' => $user_sensor->id,
        //             'sensor_column_id' => $sensor_columns[1],
        //             'value' => mt_rand($this->minCurrent, $this->maxCurrent),
        //             'created_at' => $date,
        //             'updated_at' => $date
        //         ]);
        //     }
        // }

        // DB::table('user_sensor_values')->insert($data);
    }

    // Find a randomDate between $start_date and $end_date
    function randomDate($start_date, $end_date)
    {
        // Convert to timetamps
        $min = strtotime($start_date);
        $max = strtotime($end_date);

        // Generate random number using above bounds
        $val = mt_rand($min, $max);

        // Convert back to desired date format
        return date('Y-m-d H:i:s', $val);
    }

    function generateDateSequence(
        $startDate, $endDate, $numberOfDates
        ) {

        $dates = [];

        // Convert start and end dates to Carbon instances
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Calculate the total seconds between start and end dates
        $totalSeconds = max(1, $start->diffInSeconds($end));

        // Ensure we have at least $numberOfDates dates or the maximum possible dates within the range
        $secondsPerInterval = max(1, floor($totalSeconds / max(1, $numberOfDates - 1)));

        // Generate the sequence of datetime values
        $current = $start->copy();
        while ($current->lte($end) && count($dates) < $numberOfDates) {
            $dates[] = $current->toDateTimeString();
            $current->addSeconds($secondsPerInterval); // Increment by calculated seconds per interval
        }

        // Trim the dates array to exactly $numberOfDates
        $dates = array_slice($dates, 0, $numberOfDates);

        return $dates;
    }
}
