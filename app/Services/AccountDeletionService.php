<?php

namespace App\Services;

use App\Models\AuthVerificationToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountDeletionService
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $email = $user->email;
            $user->tokens()->delete();
            AuthVerificationToken::where('user_id', $user->id)
                ->when($email, fn ($query) => $query->orWhere('destination', $email))
                ->delete();
            if ($email) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            }
            // Keep foreign-key history, but release BOTH unique login identifiers.
            // A new registration receives a new ID and never inherits old data.
            $user->forceFill([
                // users_contact_check requires one contact even on deleted rows.
                'email' => 'deleted-'.$user->id.'-'.Str::uuid().'@deleted.invalid',
                'phone' => null, 'password' => Str::random(64),
                'google_id' => null,
                'remember_token' => null, 'email_verified_at' => null, 'phone_verified_at' => null,
                'status' => 'blocked', 'is_active' => false,
            ])->save();
            $user->delete();
        });
    }
}
