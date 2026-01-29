<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProfileController extends Controller
{
    use ApiResponser;

    /**
     * Get the authenticated user from JWT
     */
    protected function getAuthUser()
    {
        $user = request()->user();
        if ($user) return $user;
        
        $user = request()->get('auth_user');
        if ($user) return $user;
        
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if ($user) return $user;
        } catch (\Exception $e) {
            // Ignore and try next method
        }
        
        return null;
    }

    /**
     * Get user profile details
     */
    public function getProfile()
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return $this->error('Unauthorized. Please login again.', 401);
            }

            return $this->success([
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'avatar' => $user->avatar,
                'sex' => $user->sex,
                'date_of_birth' => $user->date_of_birth,
                'home_address' => $user->home_address,
                'current_address' => $user->current_address,
                'is_driver' => $user->is_car_approved === 'Yes' || 
                               $user->is_boda_approved === 'Yes' ||
                               $user->is_delivery_approved === 'Yes',
                'created_at' => $user->created_at,
            ], 'Profile retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Get profile error: ' . $e->getMessage());
            return $this->error('Failed to get profile: ' . $e->getMessage());
        }
    }

    /**
     * Update user profile (name, avatar, address, etc.)
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return $this->error('Unauthorized. Please login again.', 401);
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'sex' => 'sometimes|string|in:Male,Female,Other',
                'date_of_birth' => 'sometimes|date|before:today',
                'current_address' => 'sometimes|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            // Update fields if provided
            if ($request->has('first_name')) {
                $user->first_name = $request->first_name;
            }
            if ($request->has('last_name')) {
                $user->last_name = $request->last_name;
            }
            if ($request->has('sex')) {
                $user->sex = $request->sex;
            }
            if ($request->has('date_of_birth')) {
                $user->date_of_birth = $request->date_of_birth;
            }
            if ($request->has('current_address')) {
                $user->current_address = $request->current_address;
            }

            // Update full name if first or last name changed
            if ($request->has('first_name') || $request->has('last_name')) {
                $user->name = trim($user->first_name . ' ' . $user->last_name);
            }

            $user->save();

            Log::info('Profile updated for user: ' . $user->id);

            return $this->success($user, 'Profile updated successfully', 1);
        } catch (\Exception $e) {
            Log::error('Update profile error: ' . $e->getMessage());
            return $this->error('Failed to update profile: ' . $e->getMessage());
        }
    }

    /**
     * Update user avatar/profile photo
     */
    public function updateAvatar(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return $this->error('Unauthorized. Please login again.', 401);
            }

            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            // Delete old avatar if exists
            if ($user->avatar) {
                $oldFilename = basename($user->avatar);
                $oldPath = public_path('storage/images/' . $oldFilename);
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            // Store new avatar directly to public/storage/images/
            $file = $request->file('avatar');
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Ensure directory exists
            $destinationPath = public_path('storage/images');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            
            // Move file to public/storage/images/
            $file->move($destinationPath, $filename);

            // Update user avatar URL
            $user->avatar = url('storage/images/' . $filename);
            $user->save();

            Log::info('Avatar updated for user: ' . $user->id);

            return $this->success([
                'avatar' => $user->avatar
            ], 'Avatar updated successfully', 1);
        } catch (\Exception $e) {
            Log::error('Update avatar error: ' . $e->getMessage());
            return $this->error('Failed to update avatar: ' . $e->getMessage());
        }
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return $this->error('Unauthorized. Please login again.', 401);
            }

            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:4|confirmed', // requires new_password_confirmation
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return $this->error('Current password is incorrect.');
            }

            // Check new password is different from current
            if (Hash::check($request->new_password, $user->password)) {
                return $this->error('New password must be different from current password.');
            }

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            Log::info('Password changed for user: ' . $user->id);

            return $this->success(null, 'Password changed successfully', 1);
        } catch (\Exception $e) {
            Log::error('Change password error: ' . $e->getMessage());
            return $this->error('Failed to change password: ' . $e->getMessage());
        }
    }

    /**
     * Update user email
     */
    public function updateEmail(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return $this->error('Unauthorized. Please login again.', 401);
            }

            $validator = Validator::make($request->all(), [
                'email' => 'required|email|unique:admin_users,email,' . $user->id,
                'password' => 'required|string', // Require password for security
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                return $this->error('Password is incorrect.');
            }

            $oldEmail = $user->email;
            $user->email = $request->email;
            $user->save();

            Log::info("Email updated for user {$user->id}: {$oldEmail} -> {$request->email}");

            return $this->success([
                'email' => $user->email
            ], 'Email updated successfully', 1);
        } catch (\Exception $e) {
            Log::error('Update email error: ' . $e->getMessage());
            return $this->error('Failed to update email: ' . $e->getMessage());
        }
    }

    /**
     * Update user phone number
     */
    public function updatePhone(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return $this->error('Unauthorized. Please login again.', 401);
            }

            $validator = Validator::make($request->all(), [
                'phone_number' => 'required|string|min:10|max:20|unique:admin_users,phone_number,' . $user->id,
                'password' => 'required|string', // Require password for security
                'country_code' => 'sometimes|string|max:5',
                'country_name' => 'sometimes|string|max:50',
                'country_short_name' => 'sometimes|string|max:3',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                return $this->error('Password is incorrect.');
            }

            $oldPhone = $user->phone_number;
            $user->phone_number = $request->phone_number;
            
            // Update country fields if provided
            if ($request->has('country_code')) {
                $user->country_code = $request->country_code;
            }
            if ($request->has('country_name')) {
                $user->country_name = $request->country_name;
            }
            if ($request->has('country_short_name')) {
                $user->country_short_name = $request->country_short_name;
            }
            
            $user->save();

            Log::info("Phone updated for user {$user->id}: {$oldPhone} -> {$request->phone_number}");

            return $this->success([
                'phone_number' => $user->phone_number,
                'country_code' => $user->country_code,
                'country_name' => $user->country_name,
                'country_short_name' => $user->country_short_name,
            ], 'Phone number updated successfully', 1);
        } catch (\Exception $e) {
            Log::error('Update phone error: ' . $e->getMessage());
            return $this->error('Failed to update phone number: ' . $e->getMessage());
        }
    }

    /**
     * Delete user account (soft delete - deactivate)
     */
    public function deleteAccount(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return $this->error('Unauthorized. Please login again.', 401);
            }

            $validator = Validator::make($request->all(), [
                'password' => 'required|string',
                'reason' => 'sometimes|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                return $this->error('Password is incorrect.');
            }

            // Soft delete - deactivate account
            $user->status = 'Deleted';
            $user->deleted_at = now();
            $user->save();

            Log::info("Account deactivated for user: {$user->id}, reason: " . ($request->reason ?? 'Not provided'));

            // Invalidate token
            try {
                JWTAuth::invalidate(JWTAuth::getToken());
            } catch (\Exception $e) {
                // Token might already be invalid
            }

            return $this->success(null, 'Account deleted successfully', 1);
        } catch (\Exception $e) {
            Log::error('Delete account error: ' . $e->getMessage());
            return $this->error('Failed to delete account: ' . $e->getMessage());
        }
    }
}
