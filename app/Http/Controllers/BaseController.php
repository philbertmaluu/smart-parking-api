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
        ])->header('Access-Control-Allow-Origin', '*')
          ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
          ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Camera-IP, X-Camera-Username, X-Camera-Password');
    }

    public function sendError($error, $errorMessages = [], $code = 404){
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Camera-IP, X-Camera-Username, X-Camera-Password');
    }


    public function sendRedirectWithMessage($url, $message){
        return redirect($url)->with('message', $message);
    }

}

