<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Negotiation;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class PaymentTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates realistic payment test data with various scenarios
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creating test payment data...');

        // Get sample users for testing
        $customers = User::limit(5)->get();
        $drivers = User::offset(5)->limit(5)->get();

        if ($customers->isEmpty() || $drivers->isEmpty()) {
            $this->command->error('Not enough users in database. Please ensure at least 10 users exist.');
            return;
        }

        $paymentCount = 0;
        $negotiationCount = 0;

        // Scenario 1: Completed successful payments
        for ($i = 0; $i < 3; $i++) {
            $customer = $customers[$i % $customers->count()];
            $driver = $drivers[$i % $drivers->count()];
            
            $negotiation = Negotiation::create([
                'user_id' => $customer->id,
                'driver_id' => $driver->id,
                'pickup_location' => 'Downtown Toronto, ON',
                'dropoff_location' => 'Pearson Airport, ON',
                'pickup_latitude' => 43.6532,
                'pickup_longitude' => -79.3832,
                'dropoff_latitude' => 43.6777,
                'dropoff_longitude' => -79.6248,
                'status' => 'COMPLETED',
                'agreed_price' => 45.00 + ($i * 10),
                'payment_status' => 'completed',
                'payment_completed_at' => now()->subDays($i),
            ]);
            $negotiationCount++;

            $amount = $negotiation->agreed_price;
            $serviceFee = round($amount * 0.10, 2);
            $driverAmount = $amount - $serviceFee;

            $payment = Payment::create([
                'negotiation_id' => $negotiation->id,
                'customer_id' => $customer->id,
                'driver_id' => $driver->id,
                'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
                'stripe_customer_id' => 'cus_test_' . $customer->id,
                'amount' => $amount,
                'service_fee' => $serviceFee,
                'driver_amount' => $driverAmount,
                'status' => 'succeeded',
                'currency' => 'cad',
                'description' => 'Payment for ride service',
                'paid_at' => now()->subDays($i),
            ]);
            $paymentCount++;

            // Mark as paid to create transactions
            $payment->markAsPaid();
        }

        // Scenario 2: Pending payments
        for ($i = 0; $i < 2; $i++) {
            $customer = $customers[($i + 1) % $customers->count()];
            $driver = $drivers[($i + 1) % $drivers->count()];
            
            $negotiation = Negotiation::create([
                'user_id' => $customer->id,
                'driver_id' => $driver->id,
                'pickup_location' => 'Yorkville, Toronto',
                'dropoff_location' => 'Union Station, Toronto',
                'pickup_latitude' => 43.6708,
                'pickup_longitude' => -79.3950,
                'dropoff_latitude' => 43.6452,
                'dropoff_longitude' => -79.3806,
                'status' => 'ACCEPTED',
                'agreed_price' => 25.00 + ($i * 5),
                'payment_status' => 'pending',
            ]);
            $negotiationCount++;

            $amount = $negotiation->agreed_price;
            $serviceFee = round($amount * 0.10, 2);
            $driverAmount = $amount - $serviceFee;

            Payment::create([
                'negotiation_id' => $negotiation->id,
                'customer_id' => $customer->id,
                'driver_id' => $driver->id,
                'stripe_payment_intent_id' => 'pi_pending_' . uniqid(),
                'stripe_customer_id' => 'cus_test_' . $customer->id,
                'amount' => $amount,
                'service_fee' => $serviceFee,
                'driver_amount' => $driverAmount,
                'status' => 'pending',
                'currency' => 'cad',
                'description' => 'Payment for ride service',
            ]);
            $paymentCount++;
        }

        // Scenario 3: Failed payments
        for ($i = 0; $i < 2; $i++) {
            $customer = $customers[($i + 2) % $customers->count()];
            $driver = $drivers[($i + 2) % $drivers->count()];
            
            $negotiation = Negotiation::create([
                'user_id' => $customer->id,
                'driver_id' => $driver->id,
                'pickup_location' => 'Scarborough Town Centre',
                'dropoff_location' => 'Vaughan Mills',
                'pickup_latitude' => 43.7764,
                'pickup_longitude' => -79.2584,
                'dropoff_latitude' => 43.8255,
                'dropoff_longitude' => -79.5380,
                'status' => 'CANCELLED',
                'agreed_price' => 50.00,
                'payment_status' => 'failed',
            ]);
            $negotiationCount++;

            $amount = $negotiation->agreed_price;
            $serviceFee = round($amount * 0.10, 2);
            $driverAmount = $amount - $serviceFee;

            $payment = Payment::create([
                'negotiation_id' => $negotiation->id,
                'customer_id' => $customer->id,
                'driver_id' => $driver->id,
                'stripe_payment_intent_id' => 'pi_failed_' . uniqid(),
                'stripe_customer_id' => 'cus_test_' . $customer->id,
                'amount' => $amount,
                'service_fee' => $serviceFee,
                'driver_amount' => $driverAmount,
                'status' => 'failed',
                'currency' => 'cad',
                'description' => 'Payment for ride service',
                'failure_reason' => $i === 0 ? 'insufficient_funds' : 'card_declined',
                'failed_at' => now()->subHours($i + 1),
            ]);
            $paymentCount++;

            $payment->markAsFailed($payment->failure_reason);
        }

        // Scenario 4: Processing payment
        $customer = $customers[3 % $customers->count()];
        $driver = $drivers[3 % $drivers->count()];
        
        $negotiation = Negotiation::create([
            'user_id' => $customer->id,
            'driver_id' => $driver->id,
            'pickup_location' => 'Mississauga City Centre',
            'dropoff_location' => 'Brampton GO Station',
            'pickup_latitude' => 43.5933,
            'pickup_longitude' => -79.6441,
            'dropoff_latitude' => 43.6833,
            'dropoff_longitude' => -79.7624,
            'status' => 'ACCEPTED',
            'agreed_price' => 35.00,
            'payment_status' => 'processing',
        ]);
        $negotiationCount++;

        $amount = $negotiation->agreed_price;
        $serviceFee = round($amount * 0.10, 2);
        $driverAmount = $amount - $serviceFee;

        Payment::create([
            'negotiation_id' => $negotiation->id,
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'stripe_payment_intent_id' => 'pi_processing_' . uniqid(),
            'stripe_customer_id' => 'cus_test_' . $customer->id,
            'amount' => $amount,
            'service_fee' => $serviceFee,
            'driver_amount' => $driverAmount,
            'status' => 'processing',
            'currency' => 'cad',
            'description' => 'Payment for ride service',
        ]);
        $paymentCount++;

        $this->command->info("Created {$negotiationCount} negotiations");
        $this->command->info("Created {$paymentCount} payments");
        $this->command->info("Transactions created: " . Transaction::count());
        $this->command->info('Test payment data seeding completed!');
    }
}
