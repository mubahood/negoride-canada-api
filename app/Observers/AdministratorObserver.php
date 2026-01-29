<?php

namespace App\Observers;

use Encore\Admin\Auth\Database\Administrator;

class AdministratorObserver
{
    /**
     * Handle the Administrator "updating" event.
     * Auto-approve driver when any service is approved
     */
    public function updating(Administrator $administrator)
    {
        // Check if any service is being approved
        $carApproved = $administrator->is_car_approved == 'Yes';
        $deliveryApproved = $administrator->is_delivery_approved == 'Yes';
        
        // If any service is approved, auto-approve the driver
        if ($carApproved || $deliveryApproved) {
            // Only update if they're still pending
            if ($administrator->user_type == 'Pending Driver' || $administrator->status == '2') {
                $administrator->user_type = 'Driver';
                $administrator->status = '1';
            }
        }
    }
}
