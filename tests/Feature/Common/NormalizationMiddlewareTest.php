<?php

declare(strict_types=1);

namespace Tests\Feature\Common;

use App\Modules\Common\Http\Middleware\LatinizeDigits;
use App\Modules\Common\Http\Middleware\NormalizePhoneMiddleware;
use App\Modules\Common\Http\Middleware\NormalizeRouteParamsMiddleware;
use App\Modules\Common\Http\Middleware\NormalizeStringMiddleware;
use App\Modules\Common\Http\Middleware\SecurityHeaders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class NormalizationMiddlewareTest extends TestCase
{
    /**
     * Ejra ye zanjire ru yek request e vaghei.
     *
     * 🔴 Tartib HAMUNI-e ke tu `bootstrap/app.php` sabt mishe: string avval،
     * ba'd phone. Barkas kardanesh phone ro mishkune (un vaght
     * `NormalizePhone` yek shomare ba raghame Farsi mibine).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalized(array $input): array
    {
        $request = Request::create('/', 'POST', $input);

        $pipeline = static fn (Request $request): Response => new JsonResponse($request->all());

        $response = (new NormalizeStringMiddleware)->handle(
            $request,
            static fn (Request $r): Response => (new NormalizePhoneMiddleware)->handle($r, $pipeline),
        );

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    // ── Normalize e string ──────────────────────────────────────────

    public function test_arabic_letters_and_persian_digits_are_normalised(): void
    {
        $out = $this->normalized(['name' => 'كيان', 'code' => '۱۲۳']);

        self::assertSame('کیان', $out['name']);
        self::assertSame('123', $out['code']);
    }

    public function test_nested_input_is_normalised_too(): void
    {
        // Form haye panel array mifrestan (`spreads[0][symbol]`).
        $out = $this->normalized(['spreads' => [['value' => '۱۲']]]);

        self::assertSame('12', $out['spreads'][0]['value']);
    }

    public function test_non_strings_pass_through(): void
    {
        $out = $this->normalized(['flag' => true, 'count' => 5, 'nothing' => null]);

        self::assertTrue($out['flag']);
        self::assertSame(5, $out['count']);
        self::assertNull($out['nothing']);
    }

    // ── 🔴 Tartib: string GHABL az phone ────────────────────────────

    /**
     * 🔴 Mohem tarin test e tartib.
     *
     * Yek shomare ba raghame FARSI bayad be E.164 berese. In faghat vaghti
     * kar mikone ke `NormalizeString` GHABL ejra shode bashe — vagarna
     * `PhoneHelper` yek rishte ye bi-ragham e Latin mibine.
     *
     * (`PhoneHelper` khodesh ham raghame Farsi ro mifahme، pas in test
     * SHORT-CIRCUIT nemishe — vali tartib hanuz ghanun ast، chun field haye
     * gheyr e phone faghat az masir e aval rad mishan.)
     */
    public function test_a_persian_digit_phone_reaches_e164(): void
    {
        $out = $this->normalized(['phone' => '۰۹۱۲۱۲۳۴۵۶۷']);

        self::assertSame('+989121234567', $out['phone']);
    }

    /** @return array<string, array{0: string}> */
    public static function phoneFieldNames(): array
    {
        return [
            'phone' => ['phone'],
            'mobile' => ['mobile'],
            'tel' => ['tel'],
            'telephone' => ['telephone'],
            'phone_number' => ['phone_number'],
            'mobile_number' => ['mobile_number'],
            'ba pishvand' => ['owner_phone'],
            'ba pishvand e mobile' => ['contact_mobile'],
        ];
    }

    #[DataProvider('phoneFieldNames')]
    public function test_every_phone_like_field_is_normalised(string $field): void
    {
        $out = $this->normalized([$field => '0912 123 4567']);

        self::assertSame('+989121234567', $out[$field]);
    }

    public function test_a_non_phone_field_is_left_as_digits(): void
    {
        // `code` yek field e phone nist — nabayad be E.164 tabdil beshe.
        $out = $this->normalized(['code' => '09121234567']);

        self::assertSame('09121234567', $out['code']);
    }

    /**
     * 🔴 Shomare ye na-motabar meghdar e ASLI ash ro negah midare — AMDAN.
     *
     * Agar inja `null` bezarim، payam e khata ye form mishe «field ejbari-e»
     * jaye «shomare motabar nist»، va karbar nemifahme chi ghalat bud. Rad
     * kardan kar e VALIDATION ast.
     */
    public function test_an_invalid_phone_keeps_its_original_value(): void
    {
        $out = $this->normalized(['phone' => '0912']);

        self::assertSame('0912', $out['phone']);
    }

    // ── Parameter haye route ────────────────────────────────────────

    public function test_persian_digits_in_a_route_parameter_are_normalised(): void
    {
        Route::middleware(NormalizeRouteParamsMiddleware::class)
            ->get('/t/{id}', static fn (string $id): string => $id);

        // Yek URL e copy-paste shode az matn e Farsi.
        $this->get('/t/'.rawurlencode('۱۰۲۲'))->assertOk()->assertSee('1022');
    }

    public function test_a_persian_slug_parameter_keeps_its_letters(): void
    {
        Route::middleware(NormalizeRouteParamsMiddleware::class)
            ->get('/s/{slug}', static fn (string $slug): string => $slug);

        // ⚠️ Faghat RAGHAM — horoof dast nemikhoran، vagarna yek slug e Farsi
        // mishkane.
        $this->get('/s/'.rawurlencode('طلای-۱۸'))->assertOk()->assertSee('طلای-18', false);
    }

    // ── LatinizeDigits ──────────────────────────────────────────────

    private function latinized(string $content, ?string $contentType): string
    {
        $response = new Response($content, 200, $contentType === null ? [] : ['Content-Type' => $contentType]);

        $out = (new LatinizeDigits)->handle(
            Request::create('/'),
            static fn (): Response => $response,
        );

        return (string) $out->getContent();
    }

    public function test_html_digits_are_latinised(): void
    {
        self::assertSame(
            '<p>1234 tomaan</p>',
            $this->latinized('<p>۱۲۳۴ tomaan</p>', 'text/html; charset=UTF-8'),
        );
    }

    /**
     * 🔴 JSON dast NEMIKHORE.
     *
     * Snapshot e Livewire checksum dare — bazneveshtan e byte haye un state e
     * component ro NA-MOTABAR mikone.
     */
    public function test_json_is_left_alone(): void
    {
        self::assertSame(
            '{"v":"۱۲۳۴"}',
            $this->latinized('{"v":"۱۲۳۴"}', 'application/json'),
        );
    }

    /**
     * 🔴 Fail-CLOSED: content-type e NA-MA'LUM ham rad mishe.
     *
     * Ghablan shart «agar ma'lum ast VA html nist rad kon» bud، pas yek
     * pasokh e bedun e header dast mikhord. Baraye Phase 4 (API ba HMAC) un
     * yek bomb ast: avaz kardan e yek raghame vasat e body emza ro mishkune
     * va payam mishe «emza ghalat ast» bedun e hich sarnakh.
     */
    public function test_a_response_without_a_content_type_is_left_alone(): void
    {
        self::assertSame('{"v":"۱۲۳۴"}', $this->latinized('{"v":"۱۲۳۴"}', null));
    }

    public function test_arabic_digits_are_latinised_too(): void
    {
        self::assertSame('<b>123</b>', $this->latinized('<b>١٢٣</b>', 'text/html'));
    }

    // ── SecurityHeaders ─────────────────────────────────────────────

    public function test_security_headers_are_added(): void
    {
        $response = (new SecurityHeaders)->handle(
            Request::create('/'),
            static fn (): Response => new Response('ok'),
        );

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame(
            'strict-origin-when-cross-origin',
            $response->headers->get('Referrer-Policy'),
        );
        self::assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
    }

    /**
     * 🔴 HSTS ru HTTP frestade NEMISHE.
     *
     * Ru `http://localhost:8300` un domain ro baraye MAH ha HTTPS-only
     * mikone va dev e hame ye projeh haye digar ru hamun `localhost` ro
     * misheknae. Pak kardanesh dasti-e va aksar ruz ha vaght migire ta kasi
     * befahme chera.
     */
    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        $response = (new SecurityHeaders)->handle(
            Request::create('http://localhost/'),
            static fn (): Response => new Response('ok'),
        );

        self::assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_hsts_is_sent_over_https(): void
    {
        $response = (new SecurityHeaders)->handle(
            Request::create('https://example.test/'),
            static fn (): Response => new Response('ok'),
        );

        self::assertStringContainsString(
            'max-age=31536000',
            (string) $response->headers->get('Strict-Transport-Security'),
        );
    }

    /** Agar yek route amdan header e khodesh ro gozashte، baznevisi nemishe. */
    public function test_an_explicit_header_is_not_overwritten(): void
    {
        $response = (new SecurityHeaders)->handle(
            Request::create('/'),
            static fn (): Response => new Response('ok', 200, ['X-Frame-Options' => 'SAMEORIGIN']),
        );

        self::assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }
}
