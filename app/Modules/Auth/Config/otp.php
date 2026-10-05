<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Auth / OTP
|--------------------------------------------------------------------------
*/

return [

    // Omr e code — 120s: kutah tar az sabr e karbar baraye SMS e kond nist،
    // boland tar az chizi ke yek brute force behesh berese ham nist.
    'ttl_seconds' => (int) env('OTP_EXPIRES_IN', 120),

    // Fasele ye resend. Kelid esh phone ast (na IP) — bebin `OtpThrottle`.
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN', 60),

    /*
     * 🔴 Ba'd az in tedad ghalat، code BATEL mishe — na faghat shomordan.
     *
     * 6 ragham = 10^6 halat. Bedun e batel kardan، yek bot tu 120 saniye
     * mitune hezaran hads bezane؛ ba 3 talash، ehtemal e hads 3e-6 ast.
     */
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 3),

    'digits' => (int) env('OTP_LENGTH', 6),

    /*
     * 🔴 FAGHAT dev: code tu pasokh miad + dokme ye por kardan e khodkar.
     *
     * Tu production hich vaght true nemishe — `SignIn` (P2-07) ham faghat
     * vaghti `app.debug` roshan ast ehteram esh mizare (do ghofl).
     */
    'bypass' => (bool) env('BYPASS_OTP', false),

    // Connection e Redis baraye store (Redis e OMUMI — OTP cache e vaghei-e).
    'connection' => env('OTP_REDIS_CONNECTION', 'default'),

];
