<?php

namespace App\Traits;

trait ApiResponser
{
    protected function success($data = [], $message = "", $statusCode = 200)
    {
        return response()->json([
            'code' => 1,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    protected function error($message = "", $statusCode = 400)
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => null
        ], $statusCode);
    }
}
 