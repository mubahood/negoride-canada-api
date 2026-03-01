<?php

namespace App\Admin\Controllers;

use App\Models\ScheduledBooking;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Auth\Database\Administrator;

class ScheduledBookingAdminController extends AdminController
{
    protected $title = 'Scheduled Bookings';

    protected function grid()
    {
        $grid = new Grid(new ScheduledBooking());
        $grid->disableBatchActions();
        $grid->model()->orderBy('id', 'desc');

        $grid->column('id', 'ID')->sortable();
        $grid->column('scheduled_at', 'Scheduled')->display(function ($val) {
            return $val ? date('d M Y g:i A', strtotime($val)) : '—';
        })->sortable();
        $grid->column('service_type', 'Service')->sortable();
        $grid->column('customer_id', 'Customer')->display(function () {
            return $this->customer ? $this->customer->name : "#{$this->customer_id}";
        });
        $grid->column('driver_id', 'Driver')->display(function () {
            return $this->driver ? $this->driver->name : 'Unassigned';
        });
        $grid->column('status', 'Status')->label([
            'pending'           => 'default',
            'driver_assigned'   => 'info',
            'price_negotiating' => 'warning',
            'price_accepted'    => 'primary',
            'payment_pending'   => 'warning',
            'payment_completed' => 'info',
            'confirmed'         => 'success',
            'in_progress'       => 'success',
            'completed'         => 'success',
            'cancelled'         => 'danger',
        ])->filter([
            'pending'           => 'Pending',
            'driver_assigned'   => 'Driver Assigned',
            'price_negotiating' => 'Price Negotiating',
            'price_accepted'    => 'Price Accepted',
            'payment_pending'   => 'Payment Pending',
            'payment_completed' => 'Payment Completed',
            'confirmed'         => 'Confirmed',
            'in_progress'       => 'In Progress',
            'completed'         => 'Completed',
            'cancelled'         => 'Cancelled',
        ])->sortable();
        $grid->column('customer_proposed_price', 'Customer Price (cents)')->sortable();
        $grid->column('agreed_price', 'Agreed Price (cents)')->sortable();
        $grid->column('stripe_paid', 'Paid')->display(function ($val) {
            return $val ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>';
        })->html();
        $grid->column('pickup_address', 'Pickup')->limit(30);
        $grid->column('destination_address', 'Destination')->limit(30);
        $grid->column('created_at', 'Created')->sortable();

        // Date range filter
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->equal('status', 'Status')->select([
                'pending'           => 'Pending',
                'driver_assigned'   => 'Driver Assigned',
                'price_negotiating' => 'Price Negotiating',
                'confirmed'         => 'Confirmed',
                'in_progress'       => 'In Progress',
                'completed'         => 'Completed',
                'cancelled'         => 'Cancelled',
            ]);
            $filter->between('scheduled_at', 'Scheduled Date')->datetime();
        });

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(ScheduledBooking::findOrFail($id));

        $show->field('id', 'ID');
        $show->field('status', 'Status');
        $show->field('service_type', 'Service');
        $show->field('automobile_type', 'Automobile');
        $show->field('customer_id', 'Customer ID');
        $show->field('driver_id', 'Driver ID');
        $show->field('scheduled_at', 'Scheduled At');
        $show->field('pickup_address', 'Pickup');
        $show->field('destination_address', 'Destination');
        $show->field('passengers', 'Passengers');
        $show->field('luggage', 'Luggage');
        $show->field('message', 'Message');
        $show->field('customer_proposed_price', 'Customer Price (cents)');
        $show->field('driver_proposed_price', 'Driver Price (cents)');
        $show->field('agreed_price', 'Agreed Price (cents)');
        $show->field('payment_status', 'Payment Status');
        $show->field('stripe_paid', 'Stripe Paid');
        $show->field('stripe_url', 'Stripe URL');
        $show->field('cancellation_reason', 'Cancellation Reason');
        $show->field('driver_notes', 'Driver Notes');
        $show->field('admin_notes', 'Admin Notes');
        $show->field('assigned_at', 'Assigned At');
        $show->field('confirmed_at', 'Confirmed At');
        $show->field('started_at', 'Started At');
        $show->field('completed_at', 'Completed At');
        $show->field('cancelled_at', 'Cancelled At');
        $show->field('created_at', 'Created At');

        return $show;
    }

    protected function form()
    {
        $form = new Form(new ScheduledBooking());

        // Driver assignment (primary admin action)
        $drivers = Administrator::whereNotNull('phone_number')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $form->select('driver_id', 'Assign Driver')
            ->options($drivers)
            ->help('Assigning a driver will change status to driver_assigned.');

        $form->select('status', 'Status')->options([
            'pending'           => 'Pending',
            'driver_assigned'   => 'Driver Assigned',
            'price_negotiating' => 'Price Negotiating',
            'price_accepted'    => 'Price Accepted',
            'payment_pending'   => 'Payment Pending',
            'payment_completed' => 'Payment Completed',
            'confirmed'         => 'Confirmed',
            'in_progress'       => 'In Progress',
            'completed'         => 'Completed',
            'cancelled'         => 'Cancelled',
        ]);

        $form->textarea('admin_notes', 'Admin Notes');
        $form->textarea('cancellation_reason', 'Cancellation Reason');

        // When driver is saved via admin, update assigned_at and status
        $form->saving(function (Form $form) {
            if ($form->driver_id && $form->model()->driver_id != $form->driver_id) {
                $form->model()->assigned_at = now();
                if ($form->model()->status === 'pending') {
                    $form->status = 'driver_assigned';
                }
            }
        });

        return $form;
    }
}
