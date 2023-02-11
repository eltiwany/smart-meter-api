<?php

namespace App\Http\Controllers\API\Reports;

use App\Http\Controllers\API\Sensors\UserSensorsController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use App\Models\UserBoard;
use App\Models\UserSensor;
use App\Models\UserSensorValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmartReportsController extends ResponsesController
{
    public function getUserBriefStats()
    {
        return $this->sendResponse([
            [
                'name' => 'Smart Appliances',
                'value' => UserSensor::where(['user_id' => auth()->user()->id, 'auto_added' => true])->count()
            ],
            [
                'name' => 'Average Power',
                'si' => 'W',
                'value' => $this->getAvgPower(date('Y-m-d',strtotime("-1 days")), null, auth()->user()->id)
            ],
            [
                'name' => 'Energy Used',
                'si' => 'Wh',
                'value' => $this->getAvgEnergy(date('Y-m-d',strtotime("-1 days")), null, auth()->user()->id)
            ],
            [
                'name' => 'Earthing Losses',
                'si' => 'A',
                'value' => $this->getLosses(date('Y-m-d',strtotime("-1 days")), null, auth()->user()->id)[1]->average ?? 0
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
        ], []);
    }

    public function getHealthStatus()
    {
        $statuses = [];
        $userSensors = UserSensorsController::fetchAllUserSensors()->where('auto_added', true)->where('b.name', 'not like', '%loss sensor%')->get();
        $yesterday = date('Y-m-d',strtotime("-1 days"));
        $last_week = date('Y-m-d',strtotime("-7 days"));
        $last_two_weeks = date('Y-m-d',strtotime("-14 days"));
        $last_month = date('Y-m-d',strtotime("-30 days"));

        foreach ($userSensors as $sensor) {
            $av = $this->getAvgPower($yesterday, $sensor->id, auth()->user()->id);
            $power1 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            $av = $this->getAvgPower($last_week, $sensor->id, auth()->user()->id);
            $power2 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            $av = $this->getAvgPower($last_two_weeks, $sensor->id, auth()->user()->id);
            $power3 = sizeof($av) > 0 ? $av[0]->average * $av[1]->average : 0;

            $av = $this->getAvgPower($last_month, $sensor->id, auth()->user()->id);
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
}
