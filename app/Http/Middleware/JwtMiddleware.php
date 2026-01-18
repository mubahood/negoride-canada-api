<?php

namespace App\Http\Middleware;

use App\Models\Utils;
use Closure;
use Dflydev\DotAccessData\Util;
use JWTAuth;
use Exception;
use Tymon\JWTAuth\Facades\JWTAuth as FacadesJWTAuth;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Illuminate\Support\Str;

class JwtMiddleware extends BaseMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    protected $except = [
        'login',
        'register',
        'api/otp-verify',
        'min/login',
    ];

    public function handle($request, Closure $next)
    { 
        if (!$request->expectsJson()) {
            return $next($request);
        } 

        //check if request is login or register

        if (
            Str::contains($_SERVER['REQUEST_URI'], 'login') ||
            Str::contains($_SERVER['REQUEST_URI'], 'otp') ||
            Str::contains($_SERVER['REQUEST_URI'], 'otp-verify') ||
            Str::contains($_SERVER['REQUEST_URI'], 'register')
        ) {
            return $next($request);
        }

        // If request starts with api then we will check for token
        if (!$request->is('api/*')) {
            return $next($request);
        }

        //$request->headers->set('Authorization', $headers['authorization']);// set header in request
        try {
            //$headers = apache_request_headers(); //get header
            $headers = getallheaders(); //get header

            header('Content-Type: application/json');

            $Authorization = "";
            $needsBearerPrefix = false;
            
            // Check for Authorization header with Bearer prefix
            if (isset($headers['Authorization']) && $headers['Authorization'] != "") {
                $Authorization = $headers['Authorization'];
            } else if (isset($headers['authorization']) && $headers['authorization'] != "") {
                $Authorization = $headers['authorization'];
            } else if (isset($headers['Authorizations']) && $headers['Authorizations'] != "") {
                $Authorization = $headers['Authorizations'];
            } else if (isset($headers['authorizations']) && $headers['authorizations'] != "") {
                $Authorization = $headers['authorizations'];
            } 
            // Check for raw token headers (without Bearer prefix)
            else if (isset($headers['token']) && $headers['token'] != "") {
                $Authorization = $headers['token'];
                $needsBearerPrefix = true;
            } else if (isset($headers['Token']) && $headers['Token'] != "") {
                $Authorization = $headers['Token'];
                $needsBearerPrefix = true;
            } else if (isset($headers['Tok']) && $headers['Tok'] != "") {
                $Authorization = $headers['Tok'];
                $needsBearerPrefix = true;
            } else if (isset($headers['tok']) && $headers['tok'] != "") {
                $Authorization = $headers['tok'];
                $needsBearerPrefix = true;
            }

            // If token doesn't start with Bearer and needs prefix, add it
            if ($needsBearerPrefix && !str_starts_with($Authorization, 'Bearer ')) {
                $Authorization = 'Bearer ' . $Authorization;
            }

            \Illuminate\Support\Facades\Log::info('JwtMiddleware: Token received', [
                'authorization_header' => substr($Authorization, 0, 50) . '...',
                'length' => strlen($Authorization),
            ]);

            $request->headers->set('Authorization', $Authorization); // set header in request
            $request->headers->set('authorization', $Authorization); // set header in request

            // Get payload to see what user ID is in the token
            try {
                $payload = FacadesJWTAuth::parseToken()->getPayload();
                \Illuminate\Support\Facades\Log::info('JwtMiddleware: Token payload', [
                    'sub' => $payload->get('sub'),
                    'payload' => $payload->toArray(),
                ]);
            } catch (\Exception $payloadEx) {
                \Illuminate\Support\Facades\Log::error('JwtMiddleware: Failed to get payload', [
                    'error' => $payloadEx->getMessage(),
                ]);
            }

            $user = FacadesJWTAuth::parseToken()->authenticate();
            
            \Illuminate\Support\Facades\Log::info('JwtMiddleware: User authenticated', [
                'user_id' => $user ? $user->id : null,
                'user_class' => $user ? get_class($user) : null,
                'user_table' => $user ? $user->getTable() : null
            ]);
            
            // Store user in request for controllers to access
            if ($user) {
                $request->merge(['auth_user' => $user]);
                $request->setUserResolver(function () use ($user) {
                    return $user;
                });
            }
            
        } catch (Exception $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
                return response()->json(['status' => 'Token is Invalid']);
            } else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
                return response()->json(['status' => 'Token is Expired']);
            } else {
                return Utils::error($e->getMessage());
            }
        }
        return $next($request);
    }
}
