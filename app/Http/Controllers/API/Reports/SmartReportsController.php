<?php

namespace App\Http\Controllers\API\Reports;

use App\Http\Controllers\API\Sensors\UserSensorsController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use App\Models\SensorColumn;
use App\Models\UserBoard;
use App\Models\UserSensor;
use App\Models\UserSensorValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmartReportsController extends ResponsesController
{
    public function getUserBriefStats(Request $request)
    {
        $userId = auth()->user()->id;
        if ($request->has("userId"))
            $userId = $request->get("userId");

        return $this->sendResponse([
            [
                'name' => 'Smart Appliances',
                'value' => UserSensor::where(['user_id' => $userId, 'auto_added' => true])->count()
            ],
            [
                'name' => 'Average Power',
                'si' => 'W',
                'value' => $this->getAvgPower(date('Y-m-d',strtotime("-1 days")), null, $userId)
            ],
            // [
            //     'name' => 'Average Power Losses',
            //     'si' => 'W',
            //     'value' => $this->getAvgPowerLosses(date('Y-m-d',strtotime("-1 days")), null, $userId)
            // ],
            [
                'name' => 'Energy Used',
                'si' => 'Wh',
                'value' => $this->getAvgEnergy(date('Y-m-d',strtotime("-1 days")), null, $userId)
            ],
            [
                'name' => 'Earthing Losses',
                'si' => 'A',
                'value' => $this->getLosses(date('Y-m-d',strtotime("-1 days")), null, $userId)[1]->average ?? 0
            ],
        ], []);
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
                'name' => 'Earthing Losses',
                'si' => 'A',
                'value' => $this->getLosses(date('Y-m-d',strtotime("-1 days")))[1]->average ?? 0
            ],
            [
                'name' => 'Total Power Losses (Past 24hrs)',
                'si' => 'W',
                'value' => $this->getSumPowerLosses(date('Y-m-d',strtotime("-1 days")))
            ],
            [
                'name' => 'Total Power Losses (Since Beginning)',
                'si' => 'W',
                'value' => $this->getSumPowerLosses()
            ],
            [
                'name' => 'Actual Power',
                'si' => 'W',
                'value' => (sizeof($this->getTotalPower(date('Y-m-d',strtotime("-1 days")))) > 0 ? ($this->getTotalPower(date('Y-m-d',strtotime("-1 days")))[0]->average * $this->getTotalPower(date('Y-m-d',strtotime("-1 days")))[1]->average) : 0)
                            - $this->getSumPowerLosses(date('Y-m-d',strtotime("-1 days")))
                            - (230 * ($this->getLosses(date('Y-m-d',strtotime("-1 days")))[1]->average ?? 0))
            ],
        ], []);
    }

    public function getHealthStatus(Request $request)
    {
        $userId = auth()->user()->id;
        if ($request->has("userId"))
            $userId = $request->get("userId");

        $statuses = [];
        $userSensors = UserSensorsController::fetchAllUserSensors()->where('auto_added', true)->where('b.name', 'not like', '%loss sensor%')->get();
        $yesterday = date('Y-m-d',strtotime("-1 days"));
        $last_week = date('Y-m-d',strtotime("-7 days"));
        $last_two_weeks = date('Y-m-d',strtotime("-14 days"));
        $last_month = date('Y-m-d',strtotime("-30 days"));

        foreach ($userSensors as $sensor) {
            $av = $this->getAvgPower($yesterday, $sensor->id, $userId);
            $power1 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            $av = $this->getAvgPower($last_week, $sensor->id, $userId);
            $power2 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            $av = $this->getAvgPower($last_two_weeks, $sensor->id, $userId);
            $power3 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            $av = $this->getAvgPower($last_month, $sensor->id, $userId);
            $power4 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            array_push($statuses, [
                'sensor' => $sensor,
                'statuses' => [
                    $power4,
                    $power3,
                    $power2,
                    $power1,
                ]
            ]);
        }

        return $this->sendResponse($statuses, "");
    }


    public function getAvgPowerLosses($date, $userSensorId = null, $userId = null)
    {
        // $data = DB::table('user_sensor_values as usv')
        //     ->leftJoin('user_sensors as us', 'usv.user_sensor_id', '=', 'us.id')
        //     ->leftJoin('sensor_columns as sc', 'usv.sensor_column_id', '=', 'sc.id')
        //     ->selectRaw('avg(value) as average, sc.column as name')
        //     ->whereRaw('us.auto_added = ? and date(usv.created_at) >= ?', [true, $date]);

        $data = DB::table('user_sensor_values as usv')
            ->leftJoin('user_sensors as us', 'usv.user_sensor_id', '=', 'us.id')
            ->leftJoin('sensor_columns as sc', 'usv.sensor_column_id', '=', 'sc.id')
            ->selectRaw('
                    (
                        case when
                        (case when row_number() over (order by t) >= 12
                            then avg(sales) over (order by t range between 12 preceding and current row
                        end) is null then 0 else
                        (case when row_number() over (order by t) >= 12
                            then avg(sales) over (order by t range between 12 preceding and current row
                        end) end
                    ) as average
                    , sc.column as name
            ')
            ->whereRaw('us.auto_added = ? and date(usv.created_at) >= ?', [true, $date]);


        if ($userSensorId)
            $data = $data->where('us.id', $userSensorId);

        if ($userId)
            $data = $data->where('us.user_id', $userId);

        $data = $data->groupBy('usv.sensor_column_id')
        ->get();

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

    public function getLosses($date, $userSensorId = null, $userId = null)
    {
        $data = DB::table('user_sensor_values as usv')
            ->leftJoin('user_sensors as us', 'usv.user_sensor_id', '=', 'us.id')
            ->leftJoin('sensors as s', 'us.sensor_id', '=', 's.id')
            ->leftJoin('sensor_columns as sc', 'usv.sensor_column_id', '=', 'sc.id')
            ->selectRaw('sum(value) as average, sc.column as name')
            ->whereRaw('us.auto_added = ? and date(usv.created_at) >= ? and s.name like ?', [true, $date, '%Loss%']);

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
                "data" => UserSensorValue::where(['sensor_column_id' => $column->id])->orderBy('created_at', 'desc')->limit(50)->pluck('value')->toArray(),
                "sum" => UserSensorValue::where(['sensor_column_id' => $column->id])->orderBy('created_at', 'desc')->sum('value'),
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
                    // ->groupBy('usv.user_sensor_id')
                    ->limit(50)
                    ->pluck('diff')
                    ->toArray(),
                "sum" => array_sum(
                    DB::table('user_sensor_values as usv')
                    ->selectRaw('
                            (
                                case when
                                abs(usv.value - lag(usv.value) over (partition by usv.sensor_column_id order by usv.created_at desc)) is null then 0 else
                                abs(usv.value - lag(usv.value) over (partition by usv.sensor_column_id order by usv.created_at desc)) end
                            ) as diff
                    ')
                    ->where(['sensor_column_id' => $column->id])
                    ->pluck('diff')
                    ->toArray())
            ]);
        }
        array_push($data, [
            'sensor' => [
                "sensor_id" => $column->sensor->id,
                "name" => $column->sensor->name
            ],
            'columns' => $sensorColumnValues,
            'loss_columns' => $sensorColumnLossValues,
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
                    (
                        case when
                        abs(usv.value - lag(usv.value) over (partition by usv.sensor_column_id order by usv.created_at desc)) is null then 0 else
                        abs(usv.value - lag(usv.value) over (partition by usv.sensor_column_id order by usv.created_at desc)) end
                    ) as diff,
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
