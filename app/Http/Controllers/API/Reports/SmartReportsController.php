<?php

namespace App\Http\Controllers\API\Reports;

use App\Http\Controllers\API\Sensors\UserSensorsController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use App\Models\SensorColumn;
use App\Models\User;
use App\Models\UserBoard;
use App\Models\UserSensor;
use App\Models\UserSensorValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SmartReportsController extends ResponsesController
{
    public function getUserBriefStats(Request $request)
    {
        $userId = auth()->user()->id;
        if ($request->has("userId"))
            $userId = $request->get("userId");

        return $this->sendResponse([
            [
                'name' => 'Energy Used',
                'si' => 'Wh',
                'value' => $this->getAvgEnergy(date('Y-m-d',strtotime("-1 days")), null, $userId)
            ],
            [
                'name' => 'Smart Appliances',
                'value' => UserSensor::where(['user_id' => $userId, 'auto_added' => true])->count()
            ],
            [
                'name' => 'Available Units',
                'value' => auth()->user()->available_units
            ],
            [
                'name' => 'Average Power',
                'si' => 'W',
                'value' => $this->getAvgPower(date('Y-m-d',strtotime("-1 days")), null, $userId)
            ],
            [
                'name' => 'Average Power Losses',
                'si' => 'W',
                'value' => $this->getAvgPowerLosses(date('Y-m-d',strtotime("-1 days")), null, $userId)
            ],
            [
                'name' => 'Earthing Fault Current',
                'si' => 'A',
                'value' => $this->getLosses(date('Y-m-d',strtotime("-1 days")), null, $userId, "loss sensor")[1]->average ?? 0
            ],
            [
                'name' => 'Earthing Resistance',
                'si' => 'Ω',
                'value' => $this->getLosses(date('Y-m-d',strtotime("-1 days")), null, $userId, "loss resistance sensor")[0]?->average ?? 0
            ],
        ], []);
    }

    public function getMapUserSummary()
    {
        $users = User::where('coordinates', '!=', null)
        ->join('user_boards as ub', 'users.id', '=', 'ub.user_id')
        ->selectRaw('users.*, ub.token')
        ->get();
        //->where('district', '!=', null)->where('region', '!=', null)->where('house_number', '!=', null)->get();
        $users_with_power = [];
        foreach($users as $user) {
            $energy = $this->getAvgEnergy(date('Y-m-d',strtotime("-1 days")), null, $user->id);
            $power = $this->getAvgPower(date('Y-m-d',strtotime("-1 days")), null, $user->id);
            $losses = $this->getLosses(date('Y-m-d',strtotime("-1 days")), null, $user->id)[1]->average ?? 0;
            $losses_resistance = $this->getLosses(date('Y-m-d',strtotime("-1 days")), null, $user->id, "loss resistance sensor")[0]->average ?? 0;
            $powerlosses = $this->getAvgPowerLosses(date('Y-m-d',strtotime("-1 days")), null, $user->id);
            $users_with_power[] = [
                'user' => $user,
                'energy' => $energy,
                'power' => $power,
                'losses' => $losses,
                'losses_resistance' => $losses_resistance,
                'powerlosses' => $powerlosses,
            ];
        }

        return $this->sendResponse($users_with_power, 'Success');
    }

    public function getBriefStats()
    {
        return $this->sendResponse([
            [
                'name' => 'Smart Meters',
                'value' => DB::table('boards as b')
                            ->join('user_boards as ub', 'ub.board_id', '=', 'b.id')
                            ->where('b.name', 'like', '%smart meter%')
                            ->count()
            ],
            [
                'name' => 'Smart Appliances',
                'value' => UserSensor::where('auto_added', true)->count()
            ],
            [
                'name' => 'Average Power',
                'si' => 'W',
                'value' => $this->getAvgPower(date('Y-m-d',strtotime("-1 days")))
            ],
            [
                'name' => 'Total Power',
                'si' => 'W',
                'value' => $this->getTotalPower(date('Y-m-d',strtotime("-1 days")))
            ],
            [
                'name' => 'Energy Used',
                'si' => 'Wh',
                'value' => $this->getAvgEnergy(date('Y-m-d',strtotime("-1 days")))
            ],
            [
                'name' => 'Ground Current',
                'si' => 'A',
                'value' => $this->getLosses(date('Y-m-d',strtotime("-1 days")), null, null, "loss sensor")[1]->average ?? 0
            ],
            [
                'name' => 'Average Power Losses',
                'si' => 'W',
                'value' => $this->getAvgPowerLosses(date('Y-m-d',strtotime("-1 days")), null, null)
            ],
            [
                'name' => 'Ground Resistance',
                'si' => 'Ω',
                'value' => $this->getLosses(date('Y-m-d',strtotime("-1 days")), null, null, "loss resistance sensor")[0]->average ?? 0
            ],
            [
                'name' => 'Actual Power',
                'si' => 'W',
                'value' => (sizeof($this->getTotalPower(date('Y-m-d',strtotime("-1 days")))) > 0 ? ($this->getTotalPower(date('Y-m-d',strtotime("-1 days")))[0]->average * $this->getTotalPower(date('Y-m-d',strtotime("-1 days")))[1]->average) : 0)
                            - ($this->getAvgPowerLosses(date('Y-m-d',strtotime("-1 days")), null, null)[0]['average'] * $this->getAvgPowerLosses(date('Y-m-d',strtotime("-1 days")), null, null)[1]['average'])
                            - (230 * ($this->getLosses(date('Y-m-d',strtotime("-1 days")), null, null, "loss sensor")[1]->average ?? 0))
            ],
        ], []);
    }

    public function getHealthStatus(Request $request)
    {
        $userId = auth()->user()->id;
        if ($request->has("userId"))
            $userId = $request->get("userId");

        $statuses = [];
        $userSensors = UserSensorsController::fetchAllUserSensors()->where('auto_added', true)
        ->where('b.name', 'not like', '%loss sensor%')
        ->where('b.name', 'not like', '%loss resistance sensor%')
        ->get();
        $yesterday = date('Y-m-d',strtotime("-1 days"));
        $last_week = date('Y-m-d',strtotime("-7 days"));
        $last_two_weeks = date('Y-m-d',strtotime("-14 days"));
        $last_month = date('Y-m-d',strtotime("-30 days"));
        $last_year = date('Y-m-d',strtotime("-260 days"));

        foreach ($userSensors as $sensor) {
            $av = $this->getAvgPower($yesterday, $sensor->id, $userId);
            $power1 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            $av = $this->getAvgPower($last_week, $sensor->id, $userId);
            $power2 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            $av = $this->getAvgPower($last_two_weeks, $sensor->id, $userId);
            $power3 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            $av = $this->getAvgPower($last_month, $sensor->id, $userId);
            $power4 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            $av = $this->getAvgPower($last_year, $sensor->id, $userId);
            $power5 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            array_push($statuses, [
                'sensor' => $sensor,
                'si' => 'W',
                'statuses' => [
                    $power5,
                    $power4,
                    $power3,
                    $power2,
                    $power1,
                ]
            ]);
        }

        $lossSensor = UserSensorsController::fetchAllUserSensors()->where('auto_added', true)
        ->where('b.name', 'like', '%loss sensor%')
        ->first();

        if ($lossSensor)
            $lossArr = [
                $this->getAvgPower($last_year, $lossSensor->id, $userId)[1]->average ?? 0,
                $this->getAvgPower($last_month, $lossSensor->id, $userId)[1]->average ?? 0,
                $this->getAvgPower($last_two_weeks, $lossSensor->id, $userId)[1]->average ?? 0,
                $this->getAvgPower($last_week, $lossSensor->id, $userId)[1]->average ?? 0,
                $this->getAvgPower($yesterday, $lossSensor->id, $userId)[1]->average ?? 0,
            ];
        else
            $lossArr = [0,0,0,0,0];

        array_push($statuses, [
            'sensor' => $lossSensor,
            'si' => 'A',
            'statuses' => $lossArr
        ]);

        $lossSensor = UserSensorsController::fetchAllUserSensors()->where('auto_added', true)
        ->where('b.name', 'like', '%loss resistance sensor%')
        ->first();

        if ($lossSensor)
            $lossArr = [
                $this->getAvgPower($last_year, $lossSensor->id, $userId)[0]->average ?? 0,
                $this->getAvgPower($last_month, $lossSensor->id, $userId)[0]->average ?? 0,
                $this->getAvgPower($last_two_weeks, $lossSensor->id, $userId)[0]->average ?? 0,
                $this->getAvgPower($last_week, $lossSensor->id, $userId)[0]->average ?? 0,
                $this->getAvgPower($yesterday, $lossSensor->id, $userId)[0]->average ?? 0,
            ];
        else
            $lossArr = [0,0,0,0,0];

        // return ($this->getAvgPower($last_month, $lossSensor->id, $userId)[0]);

        array_push($statuses, [
            'sensor' => $lossSensor,
            'si' => 'Ω',
            'statuses' => $lossArr
        ]);

        return $this->sendResponse($statuses, "");
    }


    public function getAvgPowerLosses($date, $userSensorId = null, $userId = null)
    {

        $data = DB::table('user_sensor_values as usv')
            ->leftJoin('user_sensors as us', 'usv.user_sensor_id', '=', 'us.id')
            ->leftJoin('sensor_columns as sc', 'usv.sensor_column_id', '=', 'sc.id')
            ->selectRaw('
                -- ABS(usv.value - LAG(usv.value) OVER (ORDER BY usv.created_at)) AS average,
                usv.value as average,
                sc.column as name
            ')
            ->whereRaw('us.auto_added = ? and date(usv.created_at) >= ?', [true, $date])
            ->orderByDesc('usv.id')
            ->limit(20000);

            if ($userSensorId)
                $data = $data->where('us.id', $userSensorId);

            if ($userId)
                $data = $data->where('us.user_id', $userId);

            $data = $data->groupBy('usv.sensor_column_id')
            ->get()
            ->toArray();

            $v = 0;
            $a = 0;

            $vArray = array_values(array_filter($data, function($datum) { return $datum->name == 'V'; }));
            $cArray = array_values(array_filter($data, function($datum) { return $datum->name == 'A'; }));

            for ($index = 0; $index < sizeof($vArray); $index++) {
                if (sizeof($vArray) == ($index + 1))
                    break;

                $v = abs($vArray[$index]->average);
                $a = abs($cArray[$index + 1]->average - $cArray[$index]->average);
            }

            $data = [
                [
                    'average' => $v,
                    'name' => 'V'
                ],
                [
                    'average' => $a,
                    'name' => 'A'
                ],
            ];

        return $data;

    }

    public function getAvgPower($date, $userSensorId = null, $userId = null)
    {
        $data = DB::table('user_sensor_values as usv')
            ->leftJoin('user_sensors as us', 'usv.user_sensor_id', '=', 'us.id')
            ->leftJoin('sensor_columns as sc', 'usv.sensor_column_id', '=', 'sc.id')
            ->selectRaw('avg(value) as average, sc.column as name')
            ->whereRaw('us.auto_added = ? and date(usv.created_at) >= ?', [true, $date]);

            if ($userSensorId)
                $data = $data->where('us.id', $userSensorId);

            if ($userId)
                $data = $data->where('us.user_id', $userId);

            $data = $data->groupBy('usv.sensor_column_id')
            ->get();

        return $data;
    }

    public function getTotalPower($date, $userSensorId = null, $userId = null)
    {
        $data = DB::table('user_sensor_values as usv')
            ->leftJoin('user_sensors as us', 'usv.user_sensor_id', '=', 'us.id')
            ->leftJoin('sensor_columns as sc', 'usv.sensor_column_id', '=', 'sc.id')
            ->selectRaw('sum(value) as average, sc.column as name')
            ->whereRaw('us.auto_added = ? and date(usv.created_at) >= ?', [true, $date]);

            if ($userSensorId)
                $data = $data->where('us.id', $userSensorId);

            if ($userId)
                $data = $data->where('us.user_id', $userId);

            $data = $data->groupBy('usv.sensor_column_id')
            ->get();

        return $data;
    }

    public function getAvgEnergy($date, $userSensorId = null, $userId = null)
    {

        $data = DB::table('user_sensor_values as usv')
            ->leftJoin('user_sensors as us', 'usv.user_sensor_id', '=', 'us.id')
            ->leftJoin('sensor_columns as sc', 'usv.sensor_column_id', '=', 'sc.id')
            ->selectRaw('avg(value) as average, sc.column as name, TIMESTAMPDIFF(HOUR, min(usv.created_at), max(usv.created_at)) AS runtime')
            ->whereRaw('us.auto_added = ? and date(usv.created_at) >= ?', [true, $date]);

            if ($userSensorId)
                $data = $data->where('us.id', $userSensorId);

            if ($userId)
                $data = $data->where('us.user_id', $userId);

            $data = $data->groupBy('usv.sensor_column_id')
            ->get();

        return $data;
    }

    public function getLosses($date, $userSensorId = null, $userId = null, $lossCol = 'Loss')
    {
        $data = DB::table('user_sensor_values as usv')
            ->leftJoin('user_sensors as us', 'usv.user_sensor_id', '=', 'us.id')
            ->leftJoin('sensors as s', 'us.sensor_id', '=', 's.id')
            ->leftJoin('sensor_columns as sc', 'usv.sensor_column_id', '=', 'sc.id')
            ->selectRaw('avg(value) as average, sc.column as name')
            ->whereRaw('us.auto_added = ? and date(usv.created_at) >= ? and s.name like ?', [true, $date, "%$lossCol%"]);

            if ($userSensorId)
                $data = $data->where('us.id', $userSensorId);

            if ($userId)
                $data = $data->where('us.user_id', $userId);

            $data = $data->groupBy('usv.sensor_column_id')
            ->get();

        return $data;
    }

    public function getUserTotalLosses()
    {
        $data = [];

        $columns = SensorColumn::whereHas('sensor', function($query) {
            $query->where('name', 'like', '%Smart Plug%');
        })
        ->groupBy('column')
        ->get();

        $sensorColumnValues = [];
        $sensorColumnLossValues = [];
        foreach($columns as $column) {
            array_push($sensorColumnValues, [
                "name" => $column->column,
                "data" => UserSensorValue::where(['sensor_column_id' => $column->id])->selectRaw('avg(value) as avgValue')->groupByRaw('hour(created_at)')->orderByRaw("hour('created_at') asc")->pluck('avgValue')->toArray(),
                "time" => UserSensorValue::where(['sensor_column_id' => $column->id])->selectRaw('hour(created_at) as diff')->whereRaw('created_at = ?', [ date_format(Carbon::now('GMT+3'), 'Y-m-d H:i:s') ])->groupByRaw('hour(created_at)')->orderByRaw("hour('created_at') asc")->pluck('diff')->toArray(),
                "sum" => UserSensorValue::where(['sensor_column_id' => $column->id])->groupByRaw('hour(created_at)')->orderByRaw("hour('created_at') asc")->sum('value'),
            ]);
        }
        array_push($data, [
            'sensor' => [
                "sensor_id" => $column->sensor->id,
                "name" => $column->sensor->name
            ],
            'columns' => $sensorColumnValues
        ]);
        $sensorColumnValues = [];

        return $this->sendResponse($data, []);
    }

    public function getSumPowerLosses($date = null)
    {

        $columns = SensorColumn::whereHas('sensor', function($query) {
            $query->where('name', 'like', '%Smart Plug%');
        })
        ->groupBy('column')
        ->get();

        $va = [];

        foreach($columns as $column) {

            $data = DB::table('user_sensor_values as usv')
            ->selectRaw('
                usv.value as diff,
                usv.created_at as created_at
            ')
            ->where(['sensor_column_id' => $column->id]);

            if (!is_null($date))
                        $data = $data
                        ->whereRaw('date(usv.created_at) >= ?', [$date]);

            array_push($va,
                array_sum(
                    $data
                    ->pluck('diff')
                    ->toArray())
            );
        }

        return sizeof($columns) === 2 ? $va[0] * $va[1] : 0;
    }
}
