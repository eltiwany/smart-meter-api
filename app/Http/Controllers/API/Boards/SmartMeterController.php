<?php

namespace App\Http\Controllers\API\Boards;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use App\Models\Sensor;
use App\Models\SensorColumn;
use App\Models\UserBoard;
use App\Models\UserSensor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SmartMeterController extends ResponsesController
{
    /**
     *
     */
    function storePowerData(Request $request) {
        $validator = Validator::make($request->all(), [
            'voltage' => 'required',
            'current' => 'required',
            'timestamp' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $token = $request->get('token');
        $identification_number = 'smart_meter';

        $user_board = UserBoard::where('token', $token)->first();
        $data = [];
        if (!UserSensor::where('user_board_id', $user_board->id)->where('identification_number', $identification_number)->exists()) {
            $user_id = $user_board->user_id;
            $user_board_id = $user_board->id;
            $sensor_id = Sensor::where('name', 'like', '%Smart Plug%')->first()->id;
            $interval = 60;
            $name = "Smart Meter " . mt_rand(1000, 9999);
            $auto_added = true;

            array_push($data, [
                "user_id" => $user_id,
                "sensor_id" => $sensor_id,
                "user_board_id" => $user_board_id,
                "interval" => $interval,
                "name" => $name,
                "auto_added" => $auto_added,
                "identification_number" => $identification_number
            ]);

            DB::table('user_sensors')->insert($data);
        }

        $user_sensor = UserSensor::where('user_board_id', $user_board->id)->where('identification_number', $identification_number)->first();
        $sensor_columns = SensorColumn::where('sensor_id', $user_sensor->sensor_id)->pluck('id')->toArray();

        $data = [];
        // Voltage
        array_push($data, [
            'user_sensor_id' => $user_sensor->id,
            'sensor_column_id' => $sensor_columns[0],
            'value' => $request->get('voltage'),
            'created_at' => $request->get('timestamp'),
            'updated_at' => $request->get('timestamp')
        ]);

        // Current
        array_push($data, [
            'user_sensor_id' => $user_sensor->id,
            'sensor_column_id' => $sensor_columns[1],
            'value' => $request->get('current'),
            'created_at' => $request->get('timestamp'),
            'updated_at' => $request->get('timestamp')
        ]);

        DB::table('user_sensor_values')->insert($data);


        return $this->sendResponse($request->all(), "Data has been recorded.");

    }

    /**
     *
     */
    function storePlugData(Request $request) {
        $validator = Validator::make($request->all(), [
            'plug_id' => 'required',
            'voltage' => 'required',
            'current' => 'required',
            'timestamp' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $token = $request->get('token');
        $identification_number = $request->get('plug_id');

        $user_board = UserBoard::where('token', $token)->first();
        $data = [];
        if (!UserSensor::where('user_board_id', $user_board->id)->where('identification_number', $identification_number)->exists()) {
            $user_id = $user_board->user_id;
            $user_board_id = $user_board->id;
            $sensor_id = Sensor::where('name', 'like', '%Smart Plug%')->first()->id;
            $interval = 60;
            $name = "Smart Plug " . mt_rand(1000, 9999);
            $auto_added = true;

            array_push($data, [
                "user_id" => $user_id,
                "sensor_id" => $sensor_id,
                "user_board_id" => $user_board_id,
                "interval" => $interval,
                "name" => $name,
                "auto_added" => $auto_added,
                "identification_number" => $identification_number
            ]);

            DB::table('user_sensors')->insert($data);
        }

        $user_sensor = UserSensor::where('user_board_id', $user_board->id)->where('identification_number', $identification_number)->first();
        $sensor_columns = SensorColumn::where('sensor_id', $user_sensor->sensor_id)->pluck('id')->toArray();

        $data = [];
        // Voltage
        array_push($data, [
            'user_sensor_id' => $user_sensor->id,
            'sensor_column_id' => $sensor_columns[0],
            'value' => $request->get('voltage'),
            'created_at' => $request->get('timestamp'),
            'updated_at' => $request->get('timestamp')
        ]);

        // Current
        array_push($data, [
            'user_sensor_id' => $user_sensor->id,
            'sensor_column_id' => $sensor_columns[1],
            'value' => $request->get('current'),
            'created_at' => $request->get('timestamp'),
            'updated_at' => $request->get('timestamp')
        ]);

        DB::table('user_sensor_values')->insert($data);


        return $this->sendResponse($request->all(), "Data has been recorded.");

    }

    /**
     *
     */
    function storeCurrentLossData(Request $request) {
        $validator = Validator::make($request->all(), [
            'current' => 'required',
            'timestamp' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $token = $request->get('token');
        $identification_number = 'earthing_current';

        $user_board = UserBoard::where('token', $token)->first();
        $data = [];
        if (!UserSensor::where('user_board_id', $user_board->id)->where('identification_number', $identification_number)->exists()) {
            $user_id = $user_board->user_id;
            $user_board_id = $user_board->id;
            $sensor_id = Sensor::where('name', 'like', '%Loss Sensor%')->first()->id;
            $interval = 60;
            $name = "Earthing Current Sensor " . mt_rand(1000, 9999);
            $auto_added = true;

            array_push($data, [
                "user_id" => $user_id,
                "sensor_id" => $sensor_id,
                "user_board_id" => $user_board_id,
                "interval" => $interval,
                "name" => $name,
                "auto_added" => $auto_added,
                "identification_number" => $identification_number
            ]);

            // return $this->sendResponse($data, "");
            DB::table('user_sensors')->insert($data);
        }

        $user_sensor = UserSensor::where('user_board_id', $user_board->id)->where('identification_number', $identification_number)->first();
        $sensor_columns = SensorColumn::where('sensor_id', $user_sensor->sensor_id)->pluck('id')->toArray();

        $data = [];

        array_push($data, [
            'user_sensor_id' => $user_sensor->id,
            'sensor_column_id' => $sensor_columns[1],
            'value' => $request->get('current'),
            'created_at' => $request->get('timestamp'),
            'updated_at' => $request->get('timestamp')
        ]);

        DB::table('user_sensor_values')->insert($data);


        return $this->sendResponse($request->all(), "Data has been recorded.");

    }

    /**
     *
     */
    function storeResistanceLossData(Request $request) {
        $validator = Validator::make($request->all(), [
            'resistance' => 'required',
            'timestamp' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $token = $request->get('token');
        $identification_number = 'earthing_resistance';

        $user_board = UserBoard::where('token', $token)->first();
        $data = [];
        if (!UserSensor::where('user_board_id', $user_board->id)->where('identification_number', $identification_number)->exists()) {
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
                "auto_added" => $auto_added,
                "identification_number" => $identification_number
            ]);

            DB::table('user_sensors')->insert($data);
        }

        $user_sensor = UserSensor::where('user_board_id', $user_board->id)->where('identification_number', $identification_number)->first();
        $sensor_columns = SensorColumn::where('sensor_id', $user_sensor->sensor_id)->pluck('id')->toArray();

        $data = [];

        array_push($data, [
            'user_sensor_id' => $user_sensor->id,
            'sensor_column_id' => $sensor_columns[0],
            'value' => $request->get('resistance'),
            'created_at' => $request->get('timestamp'),
            'updated_at' => $request->get('timestamp')
        ]);

        DB::table('user_sensor_values')->insert($data);


        return $this->sendResponse($request->all(), "Data has been recorded.");

    }





}
