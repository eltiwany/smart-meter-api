<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use App\Models\Ticket;
use App\Models\TicketThread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TicketsController extends ResponsesController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $Ticket = $this->fetchTickets()->get();
        $this->saveToLog('Tickets', 'Getting list of Tickets');
        return $this->sendResponse($Ticket, '');
    }

    public function getTicketThread($ticketId)
    {
        $thread = DB::table('ticket_threads as tt')
            ->join('tickets as t', 't.id', '=', 'tt.ticket_id')
            ->join('users as u', 'u.id', '=', 'tt.user_id')
            ->selectRaw('
                u.name as action_by,
                tt.remark,
                tt.created_at
            ')
            ->where('t.id', $ticketId)
            ->get();

        $this->saveToLog('Tickets', 'Getting thread by ticketId');
        return $this->sendResponse($thread, '');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getTickets(Request $request)
    {
        // Datatable search & pagination parameters
        $dt = $this->dtResponse($request);
        $searchValue = $dt->searchValue;

        $userId = $request->get('userId');
        $status = $request->get('status');

        $totalRecords = $this->fetchTickets($userId, $status)->get()->count();

        $totalRecordswithFilter = $this->fetchTickets($userId, $status)
            ->where(function ($query) use ($searchValue) {
                $query->where('t.id', 'like', '%' . $searchValue . '%')
                        ->orWhere('t.ticket_number', 'like', '%' . $searchValue . '%')
                        ->orWhere('t.status', 'like', '%' . $searchValue . '%')
                        ->orWhere('t.description', 'like', '%' . $searchValue . '%')
                        ->orWhere('t.location', 'like', '%' . $searchValue . '%')
                        ->orWhere('u.name', 'like', '%' . $searchValue . '%')
                        ->orWhere('u.email', 'like', '%' . $searchValue . '%')
                        ->orWhere('u.phone_number', 'like', '%' . $searchValue . '%')
                        ->orWhere('t.created_at', 'like', '%' . $searchValue . '%');
            })
            ->get()
            ->count();

        // Fetch records
        $records = $this->fetchTickets($userId, $status)
            ->orderBy($dt->columnName, $dt->columnSortOrder)
            ->where(function ($query) use ($searchValue) {
                $query->where('t.id', 'like', '%' . $searchValue . '%')
                    ->orWhere('t.ticket_number', 'like', '%' . $searchValue . '%')
                    ->orWhere('t.status', 'like', '%' . $searchValue . '%')
                    ->orWhere('t.description', 'like', '%' . $searchValue . '%')
                    ->orWhere('t.location', 'like', '%' . $searchValue . '%')
                    ->orWhere('u.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('u.email', 'like', '%' . $searchValue . '%')
                    ->orWhere('u.phone_number', 'like', '%' . $searchValue . '%')
                    ->orWhere('t.created_at', 'like', '%' . $searchValue . '%');
            })
            ->skip($dt->start)
            ->take($dt->rowPerPage)
            ->get();

        $this->saveToLog('Tickets', 'Getting list of Tickets');
        return $this->sendDTResponse($records, $totalRecords, $totalRecordswithFilter, $dt->draw);
    }


    public function fetchTickets($userId = null, $status = null)
    {
        $tickets = DB::table('tickets as t')
            ->join('users as u', 'u.id', '=', 't.reported_by')
            ->selectRaw('
                t.id,
                t.ticket_number,
                u.name as reported_by,
                t.description,
                case when t.location is null or t.location = \'\' then \'N/A\' else t.location end as location,
                t.status,
                concat(u.email, \', \', (case when u.phone_number is null then \'N/A\' else u.phone_number end)) as contact,
                t.created_at

            ');

        if ($userId)
            $tickets = $tickets->where('u.id', $userId);

        if ($status) {
            $statuses = explode(',', $status);
            $tickets = $tickets->whereIn('t.status', $statuses);
        }

        return $tickets;
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
            'description' => 'required',
            'status' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $ticket_number = random_int(100000, 999999);
        while(Ticket::where('ticket_number', $ticket_number)->exists())
            $ticket_number = random_int(100000, 999999);

        $ticket = new Ticket;
        $ticket->ticket_number = $ticket_number;
        $ticket->reported_by = auth()->user()->id;
        $ticket->status = $request->get('status');
        $ticket->location = auth()->user()->region . ' ' . auth()->user()->district . ' ' . auth()->user()->house_number;
        $ticket->description = $request->get('description');
        $ticket->save();

        $ticket_thread = new TicketThread;
        $ticket_thread->ticket_id = $ticket->id;
        $ticket_thread->user_id = auth()->user()->id;
        $ticket_thread->remark = 'New ticket created';
        $ticket_thread->save();

        $this->saveToLog('Tickets', 'Ticket created');
        return $this->sendResponse([], 'Ticket created!');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'remark' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $ticket = Ticket::find($id);
        $ticket->status = $request->get('status');
        $ticket->save();

        $ticket_thread = new TicketThread;
        $ticket_thread->ticket_id = $id;
        $ticket_thread->user_id = auth()->user()->id;
        $ticket_thread->remark = $request->get('remark');
        $ticket_thread->save();

        $this->saveToLog('Tickets', 'Ticket updated');
        return $this->sendResponse([], 'Ticket updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Ticket::destroy($id);
        $this->saveToLog('Tickets', 'Deleted Ticket with id: ' . $id);
        return $this->sendResponse([], 'Ticket has been deleted!');
    }

}
