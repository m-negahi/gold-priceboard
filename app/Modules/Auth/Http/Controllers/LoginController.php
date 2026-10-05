<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Application\Actions\SendOtpAction;
use App\Modules\Auth\Application\Actions\VerifyOtpAction;
use App\Modules\Auth\Application\Queries\PendingOtpQuery;
use App\Modules\Auth\Domain\Exceptions\OtpException;
use App\Modules\Auth\Http\Requests\RequestCodeRequest;
use App\Modules\Auth\Http\Requests\VerifyCodeRequest;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controller e NAZOK: Alpine (`alpine/login.js`) safhe ro dast dare، in
 * endpoint ha faghat be Action ha vasl-and. Hich mantegh e OTP inja nist.
 *
 * ── Step ro SERVER tasmim migire ────────────────────────────────────
 * Shomare ye pending tu session mimune؛ pas refresh (ya baz kardan e
 * dobare) be step e CODE bar migarde va shomarande az server edame mide —
 * na az aval. (UI/flow az noskhe ye ghabli (Desktop) avorde shod؛ backend
 * be Action haye IN proje vasl ast: HMAC-hash، enumeration-safe، throttle
 * ye phone+IP.)
 *
 * ── 🔴 retryAfter tu pasokh e 429 ──────────────────────────────────
 * Cooldown ya'ni «code GHABLAN rafte» — pas client bayad be step e code
 * bere va shomarande ro az `retry_after` begire، na inke khata ye merge
 * bebine. Session ham set mishe ta refresh hamin raftar ro dashte bashe.
 */
final readonly class LoginController
{
    private const SESSION_PHONE = 'login.phone';

    private const SESSION_CODE = 'login.code';

    public function __construct(
        private SendOtpAction $sendOtp,
        private VerifyOtpAction $verifyOtp,
        private PendingOtpQuery $pending,
        private AuthFactory $auth,
    ) {}

    public function show(Request $request): View
    {
        return view('auth::login', ['initial' => $this->initialState($request)]);
    }

    public function requestCode(RequestCodeRequest $request): JsonResponse
    {
        $phone = (string) $request->string('phone');

        try {
            $result = $this->sendOtp->execute($phone, (string) $request->ip());
        } catch (OtpException $e) {
            if ($e->retryAfterSeconds > 0) {
                // Cooldown/throttle: shomare yad e session mimune ta refresh
                // ham be step e code bere.
                $request->session()->put(self::SESSION_PHONE, $phone);

                return new JsonResponse([
                    'message' => $e->getMessage(),
                    'retry_after' => $e->retryAfterSeconds,
                ], 429);
            }

            throw ValidationException::withMessages(['phone' => $e->getMessage()]);
        }

        $request->session()->put(self::SESSION_PHONE, $phone);
        // 🔴 Code FAGHAT zire bypass (dev) tu session mimune ta ba'd az
        // refresh ham kart e «حالت آزمایشی» dide beshe؛ gheyr az un hich ja
        // zakhire nemishe.
        $request->session()->put(self::SESSION_CODE, $result->bypassCode);

        return new JsonResponse([
            'phone' => $phone,
            'resend_in' => $result->resendCooldownSeconds,
            'expires_in' => $result->expiresInSeconds,
            // Faghat zire bypass kelid esh HAST؛ na inke null bashe.
            ...($result->bypassCode !== null ? ['code' => $result->bypassCode] : []),
        ]);
    }

    /** Safhe ye reload-shode in ro mikhad ta shomarande EDAME bede. */
    public function cooldown(Request $request): JsonResponse
    {
        $challenge = $this->pending->challengeFor((string) $request->string('phone'));

        return new JsonResponse(['resend_in' => $challenge?->resendInSeconds ?? 0]);
    }

    /** «تغییر شماره»: pending faramush mishe ta refresh be step e shomare bere. */
    public function reset(Request $request): JsonResponse
    {
        $this->forget($request);

        return new JsonResponse(null, 204);
    }

    public function verify(VerifyCodeRequest $request): JsonResponse
    {
        try {
            $user = $this->verifyOtp->execute(
                (string) $request->string('phone'),
                (string) $request->string('code'),
                (string) $request->ip(),
            );
        } catch (OtpException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        $this->forget($request);
        $this->auth->guard()->login($user, remember: true);
        // 🔴 Zed e session fixation: session id ba'd az login avaz mishe.
        $request->session()->regenerate();

        return new JsonResponse([
            'redirect' => $user->is_admin ? route('panel.dashboard') : route('home'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auth->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Step e avvaliye: age code e zende ya cooldown-i hast -> step e `code`
     * ba shomarande ye server؛ vagarna step e `phone` va session pak.
     *
     * @return array{step: string, phone: string, resendIn: int, expiresIn: int, code: string|null}
     */
    private function initialState(Request $request): array
    {
        $phone = (string) $request->session()->get(self::SESSION_PHONE, '');
        $challenge = $phone !== '' ? $this->pending->challengeFor($phone) : null;

        if ($challenge === null) {
            $this->forget($request);

            return ['step' => 'phone', 'phone' => $phone, 'resendIn' => 0, 'expiresIn' => 0, 'code' => null];
        }

        $code = $request->session()->get(self::SESSION_CODE);

        return [
            'step' => 'code',
            'phone' => $challenge->phone,
            'resendIn' => $challenge->resendInSeconds,
            'expiresIn' => $challenge->expiresInSeconds,
            'code' => is_string($code) && (bool) config('otp.bypass') ? $code : null,
        ];
    }

    private function forget(Request $request): void
    {
        $request->session()->forget([self::SESSION_PHONE, self::SESSION_CODE]);
    }
}
