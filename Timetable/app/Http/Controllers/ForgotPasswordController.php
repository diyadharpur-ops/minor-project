<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Faculty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    private function getUser(string $role, string $identifier): Admin|Faculty|User|null
    {
        if ($role === 'admin') {
            return Admin::where('email', $identifier)->first();
        } elseif ($role === 'faculty') {
            return Faculty::where('email', $identifier)
                ->orWhere('id', $identifier)
                ->first();
        } elseif ($role === 'student') {
            return User::where('enrollment_number', $identifier)
                ->orWhere('email', $identifier)
                ->first();
        }

        return null;
    }

    public function verifyIdentifier(Request $request)
    {
        $request->validate([
            'role' => 'required|in:admin,faculty,student',
            'identifier' => 'required|string',
        ]);

        $role = $request->role;
        $identifier = $request->identifier;

        $key = 'verify-identifier-'.$role.'-'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['error' => 'Too Many Attempts. Please try again later.'], 429);
        }
        RateLimiter::hit($key, 60);

        $user = $this->getUser($role, $identifier);

        if (! $user) {
            return response()->json(['error' => 'No account found with this Email Address or Enrollment Number.'], 404);
        }

        return response()->json(['success' => 'Account verified.', 'identifier' => $identifier]);
    }

    public function resetPasswordDirect(Request $request)
    {
        $request->validate([
            'role' => 'required|in:admin,faculty,student',
            'identifier' => 'required|string',
            'password' => [
                'required',
                'string',
                'min:8',
                'max:64',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
            ],
        ], [
            'password.regex' => 'Password does not meet security requirements.',
            'password.min' => 'Password does not meet security requirements.',
            'password.max' => 'Password does not meet security requirements.',
            'password.confirmed' => 'Confirm Password does not match.',
        ]);

        $role = $request->role;
        $identifier = $request->identifier;

        $user = $this->getUser($role, $identifier);
        if (! $user) {
            return response()->json(['error' => 'No account found with this Email Address or Enrollment Number.'], 404);
        }

        // Check current password matches new password
        if (Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Please choose a different password.'], 400);
        }

        // Update password - User model auto-hashes via cast, Admin/Faculty do not
        if ($role === 'student') {
            // User model has 'hashed' cast, assign plain password
            $user->password = $request->password;
        } else {
            // Admin and Faculty models do NOT auto-hash, use Hash::make
            $user->password = Hash::make($request->password);
        }

        $user->password_changed_at = Carbon::now();

        // Regenerate remember_token for Admin and User (Faculty table may not have this column)
        if ($role !== 'faculty') {
            $user->remember_token = Str::random(60);
        }

        $user->save();

        Log::info('Password reset (direct)', ['role' => $role, 'identifier' => $identifier, 'ip' => $request->ip()]);

        // Log Activity
        try {
            ActivityLog::create([
                'user_id' => $user->id,
                'role' => $role,
                'action' => 'Direct Password Reset',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Non-fatal - log but don't fail the reset
            Log::warning('ActivityLog failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['success' => 'Your password has been changed successfully.']);
    }
}
