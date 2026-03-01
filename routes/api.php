<?php

use App\Http\Controllers\ApiAuthController;
use App\Http\Controllers\ApiBookingController;
use App\Http\Controllers\ApiChatController;
use App\Http\Controllers\ApiImportantContactsController;
use App\Http\Controllers\ApiNegotiationController;
use App\Http\Controllers\ApiResurceController;
use App\Http\Controllers\Api\ApiPaymentController;
use App\Http\Controllers\Api\ApiWebhookController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\PayoutAccountController;
use App\Http\Controllers\Api\PayoutRequestController;
use App\Http\Middleware\EnsureTokenIsValid;
use App\Http\Middleware\JwtMiddleware;
use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::POST("users/login", [ApiAuthController::class, "login"]);
Route::POST("users/register", [ApiAuthController::class, "register"]);
// OTP routes disabled for password-based authentication
// Route::POST("otp-verify", [ApiResurceController::class, "otp_verify"]);
// Route::POST("otp-request", [ApiResurceController::class, "otp_request"]);
// Route::get("otp-request", [ApiResurceController::class, "otp_request"]);

// Stripe webhooks (must be outside auth middleware)
Route::post('webhooks/stripe', [ApiChatController::class, 'stripe_webhook']);

// Payout account completion routes (no auth required - Stripe redirects here)
Route::get('payout-complete', [PayoutAccountController::class, 'payoutComplete']);
Route::get('payout-refresh', [PayoutAccountController::class, 'payoutRefresh']);

Route::middleware([JwtMiddleware::class])->group(function () {
    Route::get('route-stages', [ApiResurceController::class, 'route_stages']);

    Route::get("drivers", [ApiResurceController::class, "drivers"]);
    Route::get("saccos", [ApiResurceController::class, "saccos"]);
    Route::post("sacco-join-request", [ApiResurceController::class, "sacco_join_request"]);
    Route::get('chat-heads', [ApiChatController::class, 'chat_heads']); //==>1 
    Route::get('chat-messages', [ApiChatController::class, 'chat_messages']); //==>2 
    Route::post('chat-send', [ApiChatController::class, 'chat_send']); //==>2 
    Route::post('chat-heads-create', [ApiChatController::class, 'chat_heads_create']); //==>2 
    Route::post('negotiations', [ApiChatController::class, 'negotiation_create']); //==>2 
    Route::post('negotiations-records', [ApiChatController::class, 'negotiations_records_create']); //==>2 
    Route::post('negotiations-accept', [ApiChatController::class, 'negotiations_accept']); //==>2 
    Route::post('negotiations-complete', [ApiChatController::class, 'negotiations_complete']); //==>2 
    Route::get('negotiations', [ApiChatController::class, 'negotiations']); //==>2 
    Route::get('negotiations-records', [ApiChatController::class, 'negotiations_records']); //==>2 

    // Enhanced negotiation endpoints
    Route::post('negotiations-create', [ApiNegotiationController::class, 'create']);
    Route::post('negotiation-updates', [ApiNegotiationController::class, 'updates']);
    Route::post('negotiations-cancel', [ApiNegotiationController::class, 'cancel']);
    Route::get('negotiations-list', [ApiNegotiationController::class, 'index']);
    Route::get('negotiations-test', [ApiNegotiationController::class, 'test']);
    Route::post('negotiations-debug', [ApiNegotiationController::class, 'debugTest']);
    Route::get('negotiations/{id}/with-payment', [ApiNegotiationController::class, 'getNegotiationWithPayment']);
    Route::post('negotiations/{id}/set-agreed-price', [ApiNegotiationController::class, 'setAgreedPrice']);
    Route::post("trips-drivers", [ApiAuthController::class, "trips_drivers"]);
    Route::get("users/me", [ApiAuthController::class, "me"]);
    Route::get("users", [ApiAuthController::class, "users"]);
    Route::POST("become-driver", [ApiAuthController::class, "become_driver"]);
    
    // Important Contacts API routes
    Route::get("important-contacts", [ApiImportantContactsController::class, "getImportantContacts"]);
    Route::post("update-location", [ApiImportantContactsController::class, "updateLocation"]);
    Route::get("contacts-statistics", [ApiImportantContactsController::class, "getStatistics"]);
    
    // Important Contacts API endpoints
    Route::post('important-contacts', [App\Http\Controllers\ApiImportantContactsController::class, 'getImportantContacts']);
    Route::post('important-contacts/update-location', [App\Http\Controllers\ApiImportantContactsController::class, 'updateLocation']);
    Route::get('important-contacts/statistics', [App\Http\Controllers\ApiImportantContactsController::class, 'getStatistics']);
    
    // Payment endpoint (simple Stripe Payment Links)
    Route::post('negotiations-refresh-payment', [ApiChatController::class, 'negotiations_refresh_payment']);
    Route::post('negotiations-check-payment', [ApiChatController::class, 'negotiations_check_payment']);
    
    // Wallet API endpoints
    Route::get('wallet', [WalletController::class, 'getWallet']);
    Route::get('wallet/transactions', [WalletController::class, 'getTransactions']);
    Route::get('wallet/summary', [WalletController::class, 'getWalletSummary']);
    Route::get('wallet/earnings', [WalletController::class, 'getEarningsStats']);
    
    // Payout Account API endpoints
    Route::get('payout-account', [PayoutAccountController::class, 'getAccount']);
    Route::post('payout-account/create-stripe', [PayoutAccountController::class, 'createStripeAccount']);
    Route::post('payout-account/onboarding-link', [PayoutAccountController::class, 'getOnboardingLink']);
    Route::get('payout-account/dashboard-link', [PayoutAccountController::class, 'getDashboardLink']);
    Route::post('payout-account/sync', [PayoutAccountController::class, 'syncAccount']);
    Route::post('payout-account/preferences', [PayoutAccountController::class, 'updatePreferences']);
    Route::post('payout-account/deactivate', [PayoutAccountController::class, 'deactivate']);
    Route::post('payout-account/reactivate', [PayoutAccountController::class, 'reactivate']);
    
    // Profile & Account Management API endpoints
    Route::get('profile', [App\Http\Controllers\Api\ProfileController::class, 'getProfile']);
    Route::post('profile/update', [App\Http\Controllers\Api\ProfileController::class, 'updateProfile']);
    Route::post('profile/avatar', [App\Http\Controllers\Api\ProfileController::class, 'updateAvatar']);
    Route::post('profile/change-password', [App\Http\Controllers\Api\ProfileController::class, 'changePassword']);
    Route::post('profile/update-email', [App\Http\Controllers\Api\ProfileController::class, 'updateEmail']);
    Route::post('profile/update-phone', [App\Http\Controllers\Api\ProfileController::class, 'updatePhone']);
    Route::post('profile/delete-account', [App\Http\Controllers\Api\ProfileController::class, 'deleteAccount']);
    
    // Payout Request API endpoints
    Route::get('payout-requests', [PayoutRequestController::class, 'index']);
    Route::get('payout-requests/statistics', [PayoutRequestController::class, 'statistics']);
    Route::post('payout-requests', [PayoutRequestController::class, 'store']);
    Route::get('payout-requests/{id}', [PayoutRequestController::class, 'show']);
    Route::post('payout-requests/{id}/cancel', [PayoutRequestController::class, 'cancel']);
    
    // Admin only routes (TODO: Add admin middleware)
    Route::get('admin/payout-requests', [PayoutRequestController::class, 'adminIndex']);
    Route::post('admin/payout-requests/{id}/process', [PayoutRequestController::class, 'process']);
    
    Route::get('api/{model}', [ApiResurceController::class, 'index']);
    Route::get('trips', [ApiResurceController::class, 'trips']);
    Route::POST('get-available-trips', [ApiResurceController::class, 'get_available_trips']);
    Route::get('trips-bookings', [ApiResurceController::class, 'trips_bookings']);
    Route::POST("trips-create", [ApiAuthController::class, "trips_create"]);
    Route::POST("trips-bookings-create", [ApiAuthController::class, "trips_bookings_create"]);
    Route::POST("trips-bookings-update", [ApiAuthController::class, "trips_bookings_update"]);
    Route::POST("trips-update", [ApiAuthController::class, "trips_update"]);
    Route::POST("trips-update-detailed", [ApiAuthController::class, "trips_update_detailed"]);
    Route::get("trips-driver-bookings", [ApiAuthController::class, "trips_driver_bookings"]);
    Route::POST("trips-booking-status-update", [ApiAuthController::class, "trips_booking_status_update"]);
    Route::get("trips-my-driver-trips", [ApiAuthController::class, "trips_my_driver_trips"]);
    
    // Trip notes endpoints
    Route::get("trip-notes", [ApiAuthController::class, "trip_notes_get"]);
    Route::POST("trip-notes-add", [ApiAuthController::class, "trip_notes_add"]);
    
    // Rideshare payment endpoints
    Route::POST("rideshare-refresh-payment", [ApiAuthController::class, "rideshare_refresh_payment"]);
    Route::POST("rideshare-check-payment", [ApiAuthController::class, "rideshare_check_payment"]);
    
    Route::POST("trips-initiate", [ApiAuthController::class, "trips_initiate"]);
    Route::POST("go-on-off", [ApiAuthController::class, "go_on_off"]);
    Route::POST("update-online-status", [ApiAuthController::class, "update_online_status"]); // Flexible online/offline status update
    Route::POST("negotiation-updates", [ApiAuthController::class, "negotiation_updates"]);

    Route::POST("refresh-status", [ApiAuthController::class, "refresh_status"]);

    // ── Scheduled Bookings ──────────────────────────────────────────────
    Route::get('bookings',                               [ApiBookingController::class, 'index']);
    Route::post('bookings',                              [ApiBookingController::class, 'create']);
    Route::get('bookings/{id}',                          [ApiBookingController::class, 'show']);
    Route::post('bookings/{id}/cancel',                  [ApiBookingController::class, 'cancel']);
    Route::post('bookings/{id}/propose-price',           [ApiBookingController::class, 'proposePrice']);
    Route::post('bookings/{id}/accept-price',            [ApiBookingController::class, 'acceptPrice']);
    Route::post('bookings/{id}/accept-original-price',   [ApiBookingController::class, 'acceptOriginalPrice']);
    Route::post('bookings/{id}/assign-driver',           [ApiBookingController::class, 'assignDriver']);
    Route::post('bookings/{id}/start',                   [ApiBookingController::class, 'startTrip']);
    Route::post('bookings/{id}/complete',                [ApiBookingController::class, 'completeTrip']);
    Route::post('bookings/{id}/refresh-payment',         [ApiBookingController::class, 'refreshPayment']);
    Route::post('bookings/{id}/check-payment',           [ApiBookingController::class, 'checkPayment']);
    Route::post('bookings/{id}/mark-paid',               [ApiBookingController::class, 'markPaid']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/user', function (Request $request) {
    $user = $request->user();
    if ($user == null) {
        return 'No user';
    }
    return $user;
});
Route::get('/users', function (Request $request) {
    $conditions = [];
    if ($request->has('q')) {
        $conditions[] = ['name', 'like', '%' . $request->q . '%'];
    }
    $districts = Administrator::where($conditions)->get();
    $data = [];
    foreach ($districts as $district) {
        $data[] = [
            'id' => $district->id,
            'text' => $district->name . " - " . $district->phone_number
        ];
    }
    return response()->json([
        'data' => $data
    ]);
});

Route::get('/select-distcists', function (Request $request) {
    $conditions = [];
    if ($request->has('q')) {
        $conditions[] = ['name', 'like', '%' . $request->q . '%'];
    }
    $districts = \App\Models\DistrictModel::where($conditions)->get();
    $data = [];
    foreach ($districts as $district) {
        $data[] = [
            'id' => $district->id,
            'text' => $district->name
        ];
    }
    return response()->json([
        'data' => $data
    ]);
});


Route::get('/select-subcounties', function (Request $request) {
    $conditions = [];
    if ($request->has('q')) {
        if ($request->has('by_id')) {
            $conditions['district_id'] = ((int)($request->q));
        } else {
            $conditions[] = ['name', 'like', '%' . $request->q . '%'];
        }
    }
    $districts = \App\Models\SubcountyModel::where($conditions)->get();
    $data = [];
    foreach ($districts as $district) {
        $data[] = [
            'id' => $district->id,
            'text' => $district->name_text
        ];
    }
    return response()->json([
        'data' => $data
    ]);
});

Route::get('ajax', function (Request $r) {

    $_model = trim($r->get('model'));
    $conditions = [];
    foreach ($_GET as $key => $v) {
        if (substr($key, 0, 6) != 'query_') {
            continue;
        }
        $_key = str_replace('query_', "", $key);
        $conditions[$_key] = $v;
    }

    if (strlen($_model) < 2) {
        return [
            'data' => []
        ];
    }

    $model = "App\Models\\" . $_model;
    $search_by_1 = trim($r->get('search_by_1'));
    $search_by_2 = trim($r->get('search_by_2'));

    $q = trim($r->get('q'));

    $res_1 = $model::where(
        $search_by_1,
        'like',
        "%$q%"
    )
        ->where($conditions)
        ->limit(20)->get();
    $res_2 = [];

    if ((count($res_1) < 20) && (strlen($search_by_2) > 1)) {
        $res_2 = $model::where(
            $search_by_2,
            'like',
            "%$q%"
        )
            ->where($conditions)
            ->limit(20)->get();
    }

    $data = [];
    foreach ($res_1 as $key => $v) {
        $name = "";
        if (isset($v->name)) {
            $name = " - " . $v->name;
        }
        $data[] = [
            'id' => $v->id,
            'text' => "#$v->id" . $name
        ];
    }
    foreach ($res_2 as $key => $v) {
        $name = "";
        if (isset($v->name)) {
            $name = " - " . $v->name;
        }
        $data[] = [
            'id' => $v->id,
            'text' => "#$v->id" . $name
        ];
    }

    return [
        'data' => $data
    ];
});
