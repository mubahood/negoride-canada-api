<?php

namespace App\Traits;

trait ApiResponser
{
    protected function success($data = [], $message = "", $statusCode = 200)
    {
        // Ensure status code is a valid HTTP status code (100-599)
        // If invalid (like 1 or 0), default to 200 for success
        if (!is_int($statusCode) || $statusCode < 100 || $statusCode > 599) {
            $statusCode = 200;
        }
        
        return response()->json([
            'code' => 1,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    protected function error($message = "", $statusCode = 400)
    {
        // Ensure status code is a valid HTTP status code (100-599)
        // If invalid, default to 400 for errors
        if (!is_int($statusCode) || $statusCode < 100 || $statusCode > 599) {
            $statusCode = 400;
        }
        
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => null
        ], $statusCode);
    }
}
 