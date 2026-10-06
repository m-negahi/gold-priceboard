<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Billing\Infrastructure\Payments\ZibalGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ZibalGatewayTest extends TestCase
{
    public function test_already_verified_response_requires_paid_server_side_inquiry(): void
    {
        config()->set('billing.zibal.merchant', 'test-merchant');
        Http::preventStrayRequests();
        Http::fake([
            'https://gateway.zibal.ir/v1/verify' => Http::response(['result' => 201], 200),
            'https://gateway.zibal.ir/v1/inquiry' => Http::response([
                'result' => 100, 'status' => 1, 'amount' => 15_000,
                'trackId' => '123456', 'orderId' => '42', 'refNumber' => 'bank-42',
            ], 200),
        ]);

        $result = app(ZibalGateway::class)->verify('123456');

        self::assertTrue($result['verified']);
        self::assertSame(15_000, $result['amount_rial']);
        self::assertSame('bank-42', $result['reference']);
        self::assertSame('42', $result['data']['orderId']);
        Http::assertSentCount(2);
    }

    public function test_inquiry_does_not_mark_unpaid_or_wrong_track_as_verified(): void
    {
        config()->set('billing.zibal.merchant', 'test-merchant');
        Http::preventStrayRequests();
        Http::fake([
            'https://gateway.zibal.ir/v1/verify' => Http::response(['result' => 201], 200),
            'https://gateway.zibal.ir/v1/inquiry' => Http::response([
                'result' => 100, 'status' => 2, 'amount' => 15_000, 'trackId' => 'wrong',
            ], 200),
        ]);

        self::assertFalse(app(ZibalGateway::class)->verify('123456')['verified']);
    }
}
