<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Support\Facades\DB;

class UserWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Create user_wallets records for all existing users
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creating user wallet records for existing users...');

        // Get all users that don't have a wallet yet
        $usersWithoutWallet = User::whereDoesntHave('wallet')->get();

        $count = 0;
        foreach ($usersWithoutWallet as $user) {
            UserWallet::create([
                'user_id' => $user->id,
                'wallet_balance' => 0.00,
                'total_earnings' => 0.00,
                'stripe_customer_id' => null,
                'stripe_account_id' => null,
            ]);
            $count++;
        }

        $this->command->info("Created {$count} user wallet records.");
        
        // Also create wallets for any existing users (359 total)
        $allUsers = User::all();
        $this->command->info("Total users in system: {$allUsers->count()}");
        
        $walletsCount = UserWallet::count();
        $this->command->info("Total wallets in system: {$walletsCount}");
    }
}
