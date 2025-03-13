<?php

if (!function_exists('success_res')) {
    function success_res( $status_code = 200 , $message = 'Success' , $data = [])
    {
        return response()->json([
            'success' => true,
            'status_code' => $status_code,
            'message' => $message,
            'data' => $data
        ], $status_code);
    }
}

if (!function_exists('error_res')) {
    function error_res($status_code = 400 , $message = 'Error', $errors = [])
    {
        return response()->json([
            'success' => false,
            'status_code' => $status_code,
            'message' => $message,
            'errors' => $errors
        ], $status_code);
    }
}
