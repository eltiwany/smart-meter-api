<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\ResponsesController;
use App\Models\SmartScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SmartSchedulerController extends ResponsesController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $scheduler = $this->fetchSchedulers()->get();
        $this->saveToLog('Smart Schedulers', 'Getting list of Schedulers');
        return $this->sendResponse($scheduler, '');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSchedulers(Request $request)
    {
        // Datatable search & pagination parameters
        $dt = $this->dtResponse($request);
        $searchValue = $dt->searchValue;

        $totalRecords = $this->fetchSchedulers()->get()->count();

        $totalRecordswithFilter = $this->fetchSchedulers()
            ->where(function ($query) use ($searchValue) {
                $query->where('sd.id', 'like', '%' . $searchValue . '%')
                        ->orWhere('us.name', 'like', '%' . $searchValue . '%')
                        ->orWhere('sd.from_time', 'like', '%' . $searchValue . '%')
                        ->orWhere('sd.to_time', 'like', '%' . $searchValue . '%')
                        ->orWhere('sd.is_switched_on', 'like', '%' . $searchValue . '%');
            })
            ->get()
            ->count();

        // Fetch records
        $records = $this->fetchSchedulers()
            ->orderBy($dt->columnName, $dt->columnSortOrder)
            ->where(function ($query) use ($searchValue) {
                $query->where('sd.id', 'like', '%' . $searchValue . '%')
                        ->orWhere('us.name', 'like', '%' . $searchValue . '%')
                        ->orWhere('sd.from_time', 'like', '%' . $searchValue . '%')
                        ->orWhere('sd.to_time', 'like', '%' . $searchValue . '%')
                        ->orWhere('sd.is_switched_on', 'like', '%' . $searchValue . '%');
            })
            ->skip($dt->start)
            ->take($dt->rowPerPage)
            ->get();

        $this->saveToLog('Smart Schedulers', 'Getting list of Schedulers');
        return $this->sendDTResponse($records, $totalRecords, $totalRecordswithFilter, $dt->draw);
    }


    public function fetchSchedulers()
    {
        return DB::table('smart_schedulers as sd')
            ->join('user_sensors as us', 'us.id', '=', 'sd.user_sensor_id')
            ->selectRaw('
                            sd.id,
                            us.id as sensor_id,
                            us.name as sensor_name,
                            sd.from_time,
                            sd.to_time,
                            sd.is_switched_on
                        ')
            ->where('us.user_id', auth()->user()->id);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userSensorId' => 'required',
            'fromTime' => 'required',
            'toTime' => 'required',
            'isSwitchedOn' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $Scheduler = new SmartScheduler;
        $Scheduler->user_sensor_id = $request->get('userSensorId');
        $Scheduler->from_time = $request->get('fromTime');
        $Scheduler->to_time = $request->get('toTime');
        $Scheduler->is_switched_on = $request->get('isSwitchedOn');
        $Scheduler->save();

        $this->saveToLog('Smart Schedulers', 'Created Scheduler: ' . $request->get('userSensorId'));
        return $this->sendResponse([], 'Scheduler sequence has been created!');
    }

   /**
     * Update a resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'userSensorId' => 'required',
            'fromTime' => 'required',
            'toTime' => 'required',
            'isSwitchedOn' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $Scheduler = SmartScheduler::find($id);
        $Scheduler->user_sensor_id = $request->get('userSensorId');
        $Scheduler->from_time = $request->get('fromTime');
        $Scheduler->to_time = $request->get('toTime');
        $Scheduler->is_switched_on = $request->get('isSwitchedOn');
        $Scheduler->save();

        $this->saveToLog('Smart Schedulers', 'Created Scheduler: ' . $id);
        return $this->sendResponse([], 'Scheduler sequence has been updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        SmartScheduler::destroy($id);
        $this->saveToLog('Schedulers', 'Deleted Scheduler with id: ' . $id);
        return $this->sendResponse([], 'Scheduler sequence has been deleted!');
    }

    static function triggerScheduler($userSensorId, $columnId)
    {
        // $schedulers = DB::table('smart_schedulers as a')
        //     ->join('user_sensors as us', 'us.id', '=', 'a.user_sensor_id')
        //     ->join('sensor_columns as sc', 'sc.id', '=', 'a.sensor_column_id')
        //     ->selectRaw('a.*')
        //     ->whereRaw('us.id = ? and sc.id = ?', [$userSensorId, $columnId])
        //     ->get();

        // foreach($schedulers as $scheduler) {
        //     $userSensorValue = UserSensorValue::where(['user_sensor_id' => $userSensorId, 'sensor_column_id' => $columnId])->orderBy('created_at', 'desc')->first()->value;

        //     if ($scheduler->comparison_operation == "E" && $userSensorValue == $scheduler->value) {
        //         $userActuator = UserActuator::find($scheduler->user_actuator_id);
        //         $userActuator->is_switched_on = $scheduler->is_switched_on;
        //         $userActuator->save();
        //     }
        // }
    }
}
