<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Session-based authentication for the admin SPA. Sanctum's stateful
 * middleware turns the session cookie into an authenticated API request,
 * so no token is ever exposed to JavaScript.
 */
class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function login(LoginRequest $request): UserResource
    {
        $this->assertStateful($request);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                    'minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60),
                ]),
            ]);
        }

        if (! Auth::attempt($request->credentials(), $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return UserResource::make($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $this->assertStateful($request);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    /**
     * Sanctum only starts a session for requests whose Origin or Referer is a
     * configured stateful domain. Without one there is nothing to log into, so
     * fail with a message that names the setting to fix rather than a bare
     * "session store not set".
     */
    private function assertStateful(Request $request): void
    {
        abort_if(
            ! $request->hasSession(),
            JsonResponse::HTTP_MISDIRECTED_REQUEST,
            'This request did not arrive from a stateful frontend. Check SANCTUM_STATEFUL_DOMAINS '
            .'and make sure the browser sends an Origin header for it.',
        );
    }

    private function throttleKey(LoginRequest $request): string
    {
        return 'login:'.mb_strtolower($request->string('email')).'|'.$request->ip();
    }
}
