<?php

namespace App\Http\Controllers\API\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use App\Models\UserActuator;
use App\Models\UserBoard;
use App\Models\UserSensor;
use Illuminate\Http\Request;

class BasicReportsController extends ResponsesController
{
    public function getStats()
    {
        return $this->sendResponse([
            [
                'name' => 'Boards',
                'value' => UserBoard::where('user_id', auth()->user()->id)->count()
            ],
            [
                'name' => 'Sensors',
                'value' => UserSensor::where('user_id', auth()->user()->id)->count()
            ],
            [
                'name' => 'Actuators',
                'value' => UserActuator::where('user_id', auth()->user()->id)->count()
            ],
        ], []);
    }
}
