<?php

namespace App\Http\Controllers\API\Sensors;

use App\Http\Controllers\API\AutomationsController;
use App\Http\Controllers\ResponsesController;
use App\Models\Automation;
use App\Models\SensorColumn;
use App\Models\SensorPin;
use App\Models\UserSensor;
use App\Models\UserSensorConnection;
use App\Models\UserSensorValue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserSensorsController extends ResponsesController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userSensors = $this->fetchAllUserSensors()->where('auto_added', false)->get();
        $userSensorsWithPins = $this->fetchPinNumbers($userSensors);
        $this->saveToLog('User Sensors', 'Getting list of user sensors');
        return $this->sendResponse($userSensorsWithPins, '');
    }

    public function switchSmartActuator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userSensorId' => 'required',
            'isSwitchedOn' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $userSensorId = $request->get('userSensorId');
        $isSwitchedOn = $request->get('isSwitchedOn');

        // Save userSensor
        $userSensor = UserSensor::find($userSensorId);
        $name = $userSensor->sensor->name;
        $userSensor->is_switched_on = $isSwitchedOn;
        $userSensor->save();

        $this->saveToLog('Control Smart Appliance', 'Smart Appliance: ' . $name . ' has been turned ' . ($isSwitchedOn ? 'on' : 'off'));
        return $this->sendResponse([], 'Smart Appliance: ' . $name . ' has been turned ' . ($isSwitchedOn ? 'on' : 'off'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAutoAddedUserSensors()
    {
        $userSensors = $this->fetchAllUserSensors()->where('auto_added', true)->get();
        $userSensorsWithPins = $this->fetchPinNumbers($userSensors);
        $this->saveToLog('User Sensors', 'Getting list of auto added user sensors');
        return $this->sendResponse($userSensorsWithPins, '');
    }

    public function getUserSensorPinTypes()
    {
        return $this->sendResponse($this->fetchUserSensorPinTypes(), '');
    }

    public function getUserSensorValues()
    {
        $data = [];
        $userSensors = UserSensor::where('user_id', auth()->user()->id)->get();
        foreach ($userSensors as $userSensor) {
            $sensorColumnValues = [];
            foreach($userSensor->sensor->columns as $column) {
                array_push($sensorColumnValues, [
                    "name" => $column->column,
                    "data" => UserSensorValue::where(['sensor_column_id' => $column->id])->orderBy('created_at', 'desc')->limit(50)->pluck('value')->toArray()
                ]);
            }
            array_push($data, [
                'sensor' => [
                    "id" => $userSensor->id,
                    "sensor_id" => $userSensor->sensor_id,
                    "user_defined_name" => $userSensor->name,
                    "name" => $userSensor->sensor->name
                ],
                'columns' => $sensorColumnValues
            ]);
            $sensorColumnValues = [];
        }
        return $this->sendResponse($data, []);
    }

    public function filterArea(Request $request, UserSensor $userSensor) {
        if ($district = $request->get('district'))
            $userSensor = $userSensor->whereHas('users', function ($query) use ($district) {
                $query->where('district', $district);
            })->get();

        if ($region = $request->get('region'))
            $userSensor = $userSensor->whereHas('users', function ($query) use ($region) {
                $query->where('region', $region);
            })->get();

        if ($city = $request->get('city'))
            $userSensor = $userSensor->whereHas('users', function ($query) use ($city) {
                $query->where('city', $city);
            })->get();
    }

    public function getUserSensorValuesByArea(Request $request)
    {
        $data = [];

        $district = $request->get('district');
        $region = $request->get('region');
        $city = $request->get('city');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');

        // $userSensor = UserSensor::where();
        $columns = SensorColumn::whereHas('sensor', function($query) {
            $query->where('name', 'like', '%Smart Plug%');
        })
        ->groupBy('column')
        ->get();

        $userId = null;
        if (!$district || !$region || !$city)
            $userId = auth()->user()->id;

        $sensorColumnValues = [];
        $sensorColumnLossValues = [];
        foreach($columns as $column) {
            array_push($sensorColumnValues, [
                "name" => $column->column,
                "data" => UserSensorValue::where(['sensor_column_id' => $column->id])
                            ->whereHas('user_sensor', function($query) use ($district, $region, $city, $userId) {
                                $query->whereHas('user', function ($query2) use ($district, $region, $city, $userId) {
                                    $q = $query2->whereRaw('1 = 1');
                                    if ($district)
                                        $q = $q->where('district', $district);
                                    if ($region)
                                        $q = $q->where('region', $region);
                                    if ($city)
                                        $q = $q->where('city', $city);
                                    if (!is_null($userId))
                                        $q = $q->where('id', $userId);
                                    $query2 = $q;
                                });
                            })
                            ->whereDate('created_at', '>=', $startDate)
                            ->whereDate('created_at', '<=', $endDate)
                            ->orderBy('created_at', 'desc')
                            ->pluck('value')
                            ->toArray(),
                "time" => UserSensorValue::where(['sensor_column_id' => $column->id])
                            ->whereHas('user_sensor', function($query) use ($district, $region, $city, $userId) {
                                $query->whereHas('user', function ($query2) use ($district, $region, $city, $userId) {
                                    $q = $query2->whereRaw('1 = 1');
                                    if ($district)
                                        $q = $q->where('district', $district);
                                    if ($region)
                                        $q = $q->where('region', $region);
                                    if ($city)
                                        $q = $q->where('city', $city);
                                    if (!is_null($userId))
                                        $q = $q->where('id', $userId);
                                    $query2 = $q;
                                });
                            })
                            ->whereDate('created_at', '>=', $startDate)
                            ->whereDate('created_at', '<=', $endDate)
                            ->selectRaw('created_at as diff')
                            ->orderByRaw("created_at desc")
                            ->pluck('diff')
                            ->toArray(),
            ]);

            array_push($sensorColumnLossValues, [
                "name" => $column->column,
                "data" => UserSensorValue::where(['sensor_column_id' => $column->id])
                    ->selectRaw('
                            (
                                case when
                                abs(value - lag(value) over (partition by sensor_column_id order by created_at desc)) is null then 0 else
                                abs(value - lag(value) over (partition by sensor_column_id order by created_at desc)) end
                            ) as diff
                    ')
                    ->whereHas('user_sensor', function($query) use ($district, $region, $city, $userId) {
                        $query->whereHas('user', function ($query2) use ($district, $region, $city, $userId) {
                            $q = $query2->whereRaw('1 = 1');
                            if ($district)
                                $q = $q->where('district', $district);
                            if ($region)
                                $q = $q->where('region', $region);
                            if ($city)
                                $q = $q->where('city', $city);
                            if (!is_null($userId))
                                    $q = $q->where('id', $userId);
                            $query2 = $q;
                        });
                    })
                    ->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate)
                    // ->groupBy('usv.user_sensor_id')
                    ->pluck('diff')
                    ->toArray(),
            ]);
        }

        $earthing_column = SensorColumn::whereHas('sensor', function($query) {
            $query->where('name', 'like', '%Loss Sensor%');
        })
        ->where('column', '=', 'A')
        ->first();

        $sensorErthingLossValues = [];
        array_push($sensorErthingLossValues, [
            "name" => $earthing_column->column,
            "data" => UserSensorValue::where(['sensor_column_id' => $earthing_column->id])
                        ->selectRaw('
                            (
                                case when
                                abs(value - lag(value) over (partition by sensor_column_id order by created_at desc)) is null then 0 else
                                abs(value - lag(value) over (partition by sensor_column_id order by created_at desc)) end
                            ) as diff
                        ')
                        ->whereHas('user_sensor', function($query) use ($district, $region, $city) {
                            $query->whereHas('user', function ($query2) use ($district, $region, $city) {
                                $q = $query2->where('district', '!=', null);
                                if (!is_null($district))
                                    $q = $q->where('district', $district);
                                if (!is_null($region))
                                    $q = $q->where('region', $region);
                                if (!is_null($city))
                                    $q = $q->where('city', $city);
                                $query2 = $q;
                            });
                        })
                        ->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate)
                        ->orderBy('created_at', 'desc')
                        ->pluck('diff')
                        ->toArray(),
        ]);

        array_push($data, [
            'sensor' => [
                "sensor_id" => $column->sensor->id,
                "name" => $column->sensor->name
            ],
            'columns' => $sensorColumnValues,
            'loss_columns' => $sensorColumnLossValues,
            'earthing_columns' => $sensorErthingLossValues
        ]);
        $sensorColumnValues = [];

        return $this->sendResponse($data, []);

    }

    public function getUserSensorValuesById($id)
    {
        $data = [];
        $userSensor = UserSensor::find($id);

        $sensorColumnValues = [];
        $sensorColumnLossValues = [];
        foreach($userSensor->sensor->columns as $column) {
            array_push($sensorColumnValues, [
                "name" => $column->column,
                "data" => UserSensorValue::where(['sensor_column_id' => $column->id])
                            // ->whereDate('created_at', Carbon::now('GMT+3'))
                            ->orderBy('created_at', 'desc')
                            ->limit(50)
                            ->pluck('value')
                            ->toArray(),
                "time" => UserSensorValue::where(['sensor_column_id' => $column->id])
                            // ->whereDate('created_at', Carbon::now('GMT+3'))
                            ->selectRaw('created_at as diff')
                            ->orderByRaw("created_at desc")
                            ->limit(50)
                            ->pluck('diff')
                            ->toArray(),
            ]);

            array_push($sensorColumnLossValues, [
                "name" => $column->column,

                "data" => DB::table('user_sensor_values as usv')
                    ->selectRaw('
                            (
                                case when
                                abs(usv.value - lag(usv.value) over (partition by usv.sensor_column_id order by usv.created_at desc)) is null then 0 else
                                abs(usv.value - lag(usv.value) over (partition by usv.sensor_column_id order by usv.created_at desc)) end
                            ) as diff
                    ')
                    ->where(['sensor_column_id' => $column->id])
                    ->limit(50)->pluck('diff')->toArray(),

                "time" => DB::table('user_sensor_values as usv')
                        ->selectRaw('
                                created_at as diff
                        ')
                        ->where(['sensor_column_id' => $column->id])
                        // ->groupBy('usv.user_sensor_id')
                        ->limit(50)
                        ->orderByRaw("created_at desc")
                        ->pluck('diff')
                        ->toArray(),
            ]);
        }
        array_push($data, [
            'sensor' => [
                "id" => $userSensor->id,
                "sensor_id" => $userSensor->sensor_id,
                "user_defined_name" => $userSensor->name,
                "name" => $userSensor->sensor->name
            ],
            'columns' => $sensorColumnValues,
            'loss_columns' => $sensorColumnLossValues,
        ]);
        $sensorColumnValues = [];

        return $this->sendResponse($data, []);
    }

    public function setSensorData(Request $request)
    {
        $userSensorId = $request->get('user_sensor_id');
        $column = $request->get('column');
        $sensorId = UserSensor::find($userSensorId)->sensor_id;
        $columnId = SensorColumn::where(['sensor_id' => $sensorId, 'column' => $column])->first()->id;
        $value = $request->get('value');

        $userData = new UserSensorValue;
        $userData->sensor_column_id = $columnId;
        $userData->user_sensor_id = $userSensorId;
        $userData->value = $value;

        if (!$userData->save()) {
            return $this->sendError([], "", 401);
        }

        // Automate everytime data was sent
        AutomationsController::triggerAutomation($userSensorId, $columnId);

        return $this->sendResponse([], "Data saved");
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getUserSensors(Request $request)
    {
        // Datatable search & pagination parameters
        $dt = $this->dtResponse($request);
        $searchValue = $dt->searchValue;

        $totalRecords =
        count(
            $this->fetchPinNumbers(
                $this->fetchAllUserSensors(true)
                ->get()
            )
        );

        $totalRecordswithFilter =
        count(
            $this->fetchPinNumbers(
                $this->fetchAllUserSensors(true)
                ->where(function ($query) use ($searchValue) {
                    $query
                        ->where('ub.id', 'like', '%' . $searchValue . '%')
                        ->orWhere('u.name', 'like', '%' . $searchValue . '%')
                        ->orWhere('u.email', 'like', '%' . $searchValue . '%')
                        ->orWhere('u.city', 'like', '%' . $searchValue . '%')
                        ->orWhere('u.region', 'like', '%' . $searchValue . '%')
                        ->orWhere('u.district', 'like', '%' . $searchValue . '%')
                        ->orWhere('b.id', 'like', '%' . $searchValue . '%')
                        ->orWhere('b.name', 'like', '%' . $searchValue . '%')
                        ->orWhere('ub.name', 'like', '%' . $searchValue . '%')
                        ->orWhere('ub.threshold', 'like', '%' . $searchValue . '%')
                        ->orWhere('ub.is_switched_on', 'like', '%' . $searchValue . '%')
                        ->orWhere('ub.threshold_percentage', 'like', '%' . $searchValue . '%');
                })->get()
            )
        );

        // Fetch records
        $records = $this->fetchAllUserSensors(true)
            ->orderBy($dt->columnName, $dt->columnSortOrder)
            ->where(function ($query) use ($searchValue) {
                $query
                    ->where('ub.id', 'like', '%' . $searchValue . '%')
                    ->orWhere('u.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('u.email', 'like', '%' . $searchValue . '%')
                    ->orWhere('u.city', 'like', '%' . $searchValue . '%')
                    ->orWhere('u.region', 'like', '%' . $searchValue . '%')
                    ->orWhere('u.district', 'like', '%' . $searchValue . '%')
                    ->orWhere('b.id', 'like', '%' . $searchValue . '%')
                    ->orWhere('b.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('ub.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('ub.threshold', 'like', '%' . $searchValue . '%')
                    ->orWhere('ub.is_switched_on', 'like', '%' . $searchValue . '%')
                    ->orWhere('ub.threshold_percentage', 'like', '%' . $searchValue . '%');
            })
            ->skip($dt->start)
            ->take($dt->rowPerPage)
            ->get();

        $records = $this->fetchPinNumbers($records);

        $this->saveToLog('User Sensors', 'Getting list of user sensors');
        return $this->sendDTResponse($records, $totalRecords, $totalRecordswithFilter, $dt->draw);
    }

    public static function fetchAllUserSensors($allUsers = false)
    {
        $records = DB::table('user_sensors as ub')
            ->join('users as u', 'u.id', '=', 'ub.user_id')
            ->join('sensors as b', 'b.id', '=', 'ub.sensor_id')
            ->leftJoin('sensor_pins as bp', 'b.id', '=', 'bp.sensor_id')
            ->leftJoin('pin_types as pt', 'pt.id', '=', 'bp.pin_type_id')
            ->selectRaw('
                            ub.id,
                            u.name as full_name,
                            u.phone_number,
                            u.email,
                            u.city,
                            u.region,
                            u.district,
                            b.id as sensor_id,
                            b.name,
                            ub.name as user_defined_name,
                            ub.threshold as threshold,
                            ub.is_switched_on,
                            ub.is_active_low,
                            ub.threshold_percentage as threshold_percentage,
                            b.description,
                            b.image_url,
                            ub.interval
            ');

            if (!$allUsers)
                $records = $records->whereRaw('ub.user_id = ?', [ auth()->user()->id ]);

            return $records->groupBy('ub.id');
    }

    public function fetchSensorPinTypes($sensorId = false)
    {
        $sensorPins = SensorPin::selectRaw('distinct pin_type_id, count(pin_type_id) as pin_count');
        // If specific sensor
        if ($sensorId)
            $sensorPins = $sensorPins->where('sensor_id', $sensorId);
        $sensorPins = $sensorPins
        ->groupBy('pin_type_id')
        ->get();

        return $sensorPins->map(function (SensorPin $pin) {
            return [
                'pin_type_id' => $pin->pin_type_id,
                'pin_type' => $pin->pin_type->type,
                'pin_count' => $pin->pin_count,
            ];
        });

    }

    public function fetchPinNumbers($sensors)
    {
        $sensorsWithPins = [];
        foreach ($sensors as $sensor) {
            // Pins
            $pins = SensorPin::where('sensor_id', $sensor->sensor_id)
            ->orderBy('pin_type_id', 'asc')
            ->orderBy('pin_number', 'asc')
            ->get();
            $filteredPins = $pins->map(function(SensorPin $pin) {
                return [
                    'pin_type_id' => $pin->pin_type_id,
                    'pin_type' => $pin->pin_type->type,
                    'pin_number' => (int) $pin->pin_number,
                    'remarks' => $pin->remarks,
                    'id' => $pin->id,
                ];
            });

            // Columns
            $sensorColumns = SensorColumn::where('sensor_id', $sensor->sensor_id)
            ->orderBy('column', 'asc')
            ->get();
            $filteredSensorColumns = $sensorColumns->map(function(SensorColumn $column) {
                return [
                    'column' => $column->column,
                    'id' => $column->id,
                ];
            });

            // Connections
            $sensorConnections = UserSensorConnection::where('user_sensor_id', $sensor->id)
            ->get();
            $filteredUserSensorConnetions = $sensorConnections->map(function(UserSensorConnection $connection) {
                return [
                    'id' => $connection->id,
                    'sensor_pin' => $connection->sensor_pin->pin_type->type,
                    'board_pin' => $connection->board_pin->pin_type->type,
                    'board_pin_number' => $connection->board_pin->pin_number,
                ];
            });

            array_push($sensorsWithPins, [
                "sensor" => $sensor,
                "pinTypes" => $this->fetchSensorPinTypes($sensor->sensor_id),
                "pins" => $filteredPins,
                "columns" => $filteredSensorColumns,
                "connections" => $filteredUserSensorConnetions
            ]);
        }
        return $sensorsWithPins;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Update userSensor through post
        if ($request->has('id'))
            return $this->update($request, $request->get('id'));

        $validator = Validator::make($request->all(), [
            'sensorId' => 'required',
            'interval' => 'required',
            'connections' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $sensorId = $request->get('sensorId');
        $name = $request->get('name');
        $userBoardId = $request->get('userBoardId');
        $interval = $request->get('interval');
        $connections = $request->get('connections');

        // Save userSensor
        $userSensor = new UserSensor;
        $userSensor->user_id = auth()->user()->id;
        $userSensor->sensor_id = $sensorId;
        $userSensor->name = $name;
        $userSensor->user_board_id = $userBoardId;
        $userSensor->interval = $interval;
        $userSensor->save();

        // Save board pins
        $userSensorId = UserSensor::orderBy('created_at', 'desc')->first()->id;
        foreach ($connections as $connection) {
            $sensorConnection = new UserSensorConnection();
            $sensorConnection->user_sensor_id = $userSensorId;
            $sensorConnection->board_pin_id =  $connection['boardPinId'];
            $sensorConnection->sensor_pin_id = $connection['sensorPinId'];
            $sensorConnection->save();
        }

        $this->saveToLog('Sensors', 'Linked Sensor with sensorId: ' . $sensorId);
        return $this->sendResponse([], 'Sensor has been linked to your account!');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'interval' => 'required',
            'threshold' => 'required',
            'threshold_percentage' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $name = $request->get('name');
        $threshold = $request->get('threshold');
        $threshold_percentage = $request->get('threshold_percentage');
        $interval = $request->get('interval');

        // Save userSensor
        $userSensor = UserSensor::find($id);
        $userSensor->name = $name;
        $userSensor->interval = $interval;
        $userSensor->threshold = $threshold;
        $userSensor->threshold_percentage = $threshold_percentage;
        $userSensor->save();

        $this->saveToLog('Sensors', 'Updated Linked Sensor with user sensor id: ' . $id);
        return $this->sendResponse([], 'Device updated.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $userSensor = UserSensor::find($id);
        $sensorName = $userSensor->sensor->name;
        $userSensor->delete();
        $this->saveToLog('User Sensors', 'Unlink sensor: ' . $sensorName);
        return $this->sendResponse([], 'Sensor has been unlinked from your account!');
    }
}
