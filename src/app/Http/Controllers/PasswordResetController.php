<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordReset\ResetPasswordRequest;
use App\Http\Requests\PasswordReset\SendPasswordResetEmailRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    //
    public function showPasswordResetRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetEmail(SendPasswordResetEmailRequest $request)
    {
        $email = $request->validated('email');

        $status = Password::sendResetLink(['email' => $email]);

        return back()->with('status', 'If an account with this email exists, we will send a password reset link.');
    }

    public function showResetPasswordForm(#[\SensitiveParameter] string $token, Request $request)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $validated = $request->validated();

        $status = Password::reset($validated, function (User $user, #[\SensitiveParameter] string $password) {
            $user->password = Hash::make($password);
            $user->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        logger()->debug('Password reset failed', ['status' => $status, 'email' => $validated['email'] ?? null]);

        return back()->withInput($request->only('email'))->withErrors(['email' => 'Password reset failed.']);
    }
}
