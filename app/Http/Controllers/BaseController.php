<?php

namespace App\Http\Controllers;


class BaseController extends Controller
{

    public function sendResponse($data, $messages){
        return response()->json([
            'success' => true,
            'data' => $data,
            'messages' => $messages,
            'status' => 200,
        ]);
    }

    public function sendError($error, $errorMessages = [], $code = 404){
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }


    public function sendRedirectWithMessage($url, $message){
        return redirect($url)->with('message', $message);
    }

}

