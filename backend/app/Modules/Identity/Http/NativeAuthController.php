<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Audit\AuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class NativeAuthController extends Controller
{
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
