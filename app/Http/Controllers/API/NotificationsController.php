<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\ResponsesController;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class NotificationsController extends ResponsesController
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendNotifications(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required',
            'region' => 'required',
            'district' => 'required'
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $users = User::where([
            'region' => $request->get('region'),
            'district' => $request->get('district'),
        ])->get();

        if (sizeof($users) <= 0)
            return $this->sendError('Users were not found on select region/district.', $validator->errors(), 404);

        $phone_numbers = [];

        $index = 1;
        foreach ($users as $user) {
            if ($user->phone_number)
                array_push($phone_numbers, [
                    "recipient_id" => $index,
                    "dest_addr" => $user->phone_number,
                ]);
        }

        $requestBeem = [
            "source_addr" => 'NafuuSMS',
            "schedule_time" => '',
            "encoding" => 0,
            "message" => $request->get("message"),
            "recipients" => $phone_numbers,
        ];

        $headersContent = [
            "Content-Type" => "application/json",
            "Authorization" => "Basic " . base64_encode(
                config("sms.api-key")
                . ':' .
                config("sms.secret")
            ),
        ];

        $response = Http::withHeaders($headersContent)->post(config("sms.api"), $requestBeem);

        if ($response->ok()) {
            foreach ($users as $user) {
                if ($user->phone_number) {
                    $message = new Message;
                    $message->name = $user->name;
                    $message->phone_number = $user->phone_number;
                    $message->message = $request->get("message");
                    $message->save();
                }
            }

            return $this->sendResponse([], 'Messages have been sent!');
        }

        else
            return $this->sendError('Unknown error while sending messages.', [], 400);
    }


}
