<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\AccountSessionResource;
use App\Shared\Audit\AuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

final class NativeAuthController extends Controller
{
    /**
     * POST /api/v1/auth/register
     * Creates a native account, issues a session (unverified), sends verification.
     */
    public function register(RegisterRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var array{email:string,password:string,display_name?:string|null} $data */
        $data = $request->validated();

        $account = DB::transaction(function () use ($data, $audit): Account {
            $account = Account::create([
                'email' => $data['email'],
                'password' => $data['password'], // hashed by the 'hashed' cast
                'display_name' => $data['display_name'] ?? null,
            ]);

            $account->sendEmailVerificationNotification();
            $audit->record('auth.register', 'account', (string) $account->id);

            return $account;
        });

        Auth::login($account);
        // session() helper (not $request->session()): Sanctum's stateful middleware
        // skips StartSession on requests without an Origin/Referer, so the session
        // store isn't bound to the Request — the helper resolves it from the container.
        session()->regenerate();

        return (new AccountSessionResource($account))->response()->setStatusCode(201);
    }

    /**
     * POST /api/v1/auth/password/login
     * Enumeration-safe: unknown email and wrong password return the SAME 422.
     */
    public function login(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $account = Account::where('email', strtolower($data['email']))->first();

        if ($account === null || $account->password === null
            || ! Hash::check($data['password'], $account->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        Auth::login($account);
        session()->regenerate(); // helper, not $request->session() — see register()
        $audit->record('auth.login', 'account', (string) $account->id);

        return (new AccountSessionResource($account))->response();
    }

    /**
     * POST /api/v1/auth/password/forgot — always 200 (no enumeration).
     */
    public function forgot(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);
        Password::sendResetLink(['email' => strtolower($data['email'])]);
        $audit->record('auth.password_reset_requested', 'account', null);

        return response()->json(['message' => 'If that email exists, a reset link has been sent.']);
    }

    /**
     * POST /api/v1/auth/password/reset
     */
    public function reset(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $status = Password::reset(
            ['email' => strtolower($data['email']), 'password' => $data['password'], 'token' => $data['token']],
            function (Account $account, string $password): void {
                $account->password = $password; // hashed by cast
                $account->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This password reset token is invalid or has expired.']);
        }

        $audit->record('auth.password_reset_completed', 'account', null);

        return response()->json(['message' => 'Your password has been reset.']);
    }

    /**
     * GET /api/v1/auth/email/verify/{id}/{hash}  (signed)
     * Validates the signed link, marks the email verified, redirects to the SPA.
     */
    public function verify(Request $request, string $id, string $hash, AuditLogger $audit): RedirectResponse
    {
        /** @var Account $account */
        $account = Account::findOrFail($id);

        if (! hash_equals(sha1((string) $account->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $account->hasVerifiedEmail()) {
            $account->markEmailAsVerified();
            event(new Verified($account));
            $audit->record('auth.email_verified', 'account', (string) $account->id);
        }

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/auth/email-verified');
    }

    /**
     * POST /api/v1/auth/email/resend  (auth:sanctum)
     */
    public function resend(Request $request): Response
    {
        /** @var Account $account */
        $account = $request->user();

        if (! $account->hasVerifiedEmail()) {
            $account->sendEmailVerificationNotification();
        }

        return response()->noContent();
    }
}
