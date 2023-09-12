<?php

namespace App\Http\Controllers\API\Billing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentsController extends ResponsesController
{

    public function index(Request $request)
    {
        $allRecords = $request->get('allRecords');

        return $this->sendResponse($this->fetchPayments($allRecords)->get(), '');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPayments(Request $request)
    {
        // Datatable search & pagination parameters
        $dt = $this->dtResponse($request);
        $searchValue = $dt->searchValue;
        $allRecords = $request->get('allRecords') ?? false;

        $totalRecords = count($this->fetchPayments($allRecords)->get());

        $totalRecordswithFilter =
            count(
                $this->fetchPayments($allRecords)
                ->where(function ($query) use ($searchValue) {
                    $query
                        ->where('u.name', 'like', '%' . $searchValue . '%')
                        ->orWhere('u.email', 'like', '%' . $searchValue . '%')
                        ->orWhere('p.amount', 'like', '%' . $searchValue . '%')
                        ->orWhere('p.description', 'like', '%' . $searchValue . '%')
                        ->orWhere('p.transaction_date', 'like', '%' . $searchValue . '%');
                })->get()
            );

        // Fetch records
        $records = $this->fetchPayments($allRecords)
                ->where(function ($query) use ($searchValue) {
                    $query
                        ->where('u.name', 'like', '%' . $searchValue . '%')
                        ->orWhere('u.email', 'like', '%' . $searchValue . '%')
                        ->orWhere('p.amount', 'like', '%' . $searchValue . '%')
                        ->orWhere('p.description', 'like', '%' . $searchValue . '%')
                        ->orWhere('p.transaction_date', 'like', '%' . $searchValue . '%');
                })
                ->skip($dt->start)
                ->take($dt->rowPerPage)
                ->get();

        $this->saveToLog('Documents', 'Getting list of documents');
        return $this->sendDTResponse($records, $totalRecords, $totalRecordswithFilter, $dt->draw);
    }

    public function fetchPayments($allRecords = false)
    {
        $records = DB::table('payments as p')
                    ->join('users as u', 'u.id', '=', 'p.user_id')
                    ->selectRaw('
                        u.name as full_name,
                        u.email as email,
                        p.*
                    ');

        if (!$allRecords)
            $records = $records->where('p.user_id', auth()->user()->id);

        return $records;
    }

    public function store(Request $request)
    {

        $data = [];
        if (!$request->get('paymentData'))
            return $this->sendError('Payment data on a file could be read.');

        $payments = $request->get('paymentData');

        try {

            $chunks = array_chunk($payments, 50);
            foreach ($chunks as $chunk) {

                foreach ($chunk as $payment) {

                    $user = User::where('email', $payment['EMAIL']);
                    if (!$user->exists())
                        continue;

                    array_push($data, [
                        'user_id' => $user->first()->id,
                        'transaction_id' => $payment['TRANSACTION_ID'],
                        'transaction_date' => $payment['TRANSACTION_DATE'],
                        'amount' => $payment['AMOUNT'],
                        'description' => $payment['DESCRIPTION'],
                        'created_at' => Carbon::now('GMT+3'),
                        'updated_at' => Carbon::now('GMT+3')
                    ]);

                }

                DB::table('payments')->insert($data);
                $data = [];
            }


            return $this->sendResponse([], 'Successfully imported payment data');
        } catch (Throwable $e) {
            return $this->sendError($e->getMessage(), [], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Payment::destroy($id);
        $this->saveToLog('Payments', 'Deleted Payment: ' . $id);
        return $this->sendResponse([], 'Payment has been deleted!');
    }

}
