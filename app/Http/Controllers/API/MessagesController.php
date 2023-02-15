<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use App\Models\Message;
use Illuminate\Http\Request;

class MessagesController extends ResponsesController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $messages = Message::all();
        $this->saveToLog('Messages', 'Getting list of messages');
        return $this->sendResponse($messages, '');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getMessages(Request $request)
    {
        // Datatable search & pagination parameters
        $dt = $this->dtResponse($request);
        $searchValue = $dt->searchValue;

        $totalRecords = Message::count();

        $totalRecordswithFilter = Message::where(function ($query) use ($searchValue) {
                    $query
                        ->where('name', 'like', '%' . $searchValue . '%')
                        ->orWhere('phone_number', 'like', '%' . $searchValue . '%')
                        ->orWhere('message', 'like', '%' . $searchValue . '%');
                })->count();

        // Fetch records
        $records = Message::where(function ($query) use ($searchValue) {
                $query
                    ->where('name', 'like', '%' . $searchValue . '%')
                    ->orWhere('phone_number', 'like', '%' . $searchValue . '%')
                    ->orWhere('message', 'like', '%' . $searchValue . '%');
                })
                ->skip($dt->start)
                ->take($dt->rowPerPage)
                ->get();

        $this->saveToLog('Messages', 'Getting list of messages');
        return $this->sendDTResponse($records, $totalRecords, $totalRecordswithFilter, $dt->draw);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
