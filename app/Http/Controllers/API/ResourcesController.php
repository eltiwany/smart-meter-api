<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResourcesController extends ResponsesController
{
    /**
     * Get user resources
     */
    public function getUserResources()
    {
        return $this->sendResponse(
            [
                "regions" => User::all(['region'])->unique('region')->pluck('region')->toArray(),
                "districts" => User::all(['district'])->unique('district')->pluck('district')->toArray(),
                "cities" => User::all(['city'])->unique('city')->pluck('city')->toArray(),
            ],
            ""
        );
    }

    /**
     * Get regions registered on system users
     */
    public function getRegions() {
        return DB::table('users')->select('region')->groupByRaw('region')->get();
    }

    /**
     * Get districts registered on system users
     */
    public function getDistricts() {
        return DB::table('users')->select('district')->groupByRaw('district')->get();
    }

    /**
     * Get cities registered on system users
     */
    public function getCities() {
        return DB::table('users')->select('city')->groupByRaw('city')->get();
    }
}
