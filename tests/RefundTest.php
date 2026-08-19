<?php

namespace DavidMaximous\Fawaterak\Tests;

use DavidMaximous\Fawaterak\Classes\FawaterakRefund;
use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;
use Illuminate\Support\Facades\Http;

class RefundTest extends TestCase
{
    public function test_it_lists_refund_types()
    {
        $this->fakeApi('*/api/v3/refund/refundTypes', Http::response([
            'status' => 'success',
            'data' => [
                '0' => 'invoice',
                '1' => 'Payment link transaction',
                '2' => 'Collection link transaction',
                '3' => 'Integration transaction',
            ],
        ]));

        $result = (new FawaterakRefund())->types('en');

        $this->assertSame('success', $result['status']);
        $this->assertSame('Integration transaction', $result['data']['3']);
        $this->assertSame(['lang' => 'en'], $this->bodyFor('refundTypes'));
    }

    public function test_the_language_defaults_to_the_app_locale()
    {
        $this->app->setLocale('ar');

        $this->fakeApi('*/api/v3/refund/refundReasons', Http::response([
            'status' => 'success',
            'data' => ['المنتج غير متوفر'],
        ]));

        (new FawaterakRefund())->reasons();

        $this->assertSame(['lang' => 'ar'], $this->bodyFor('refundReasons'));
    }

    public function test_it_lists_refund_reasons()
    {
        $this->fakeApi('*/api/v3/refund/refundReasons', Http::response([
            'status' => 'success',
            'data' => [
                'Product not available',
                'Customer changed their mind',
                'Incorrect product delivered',
                'Other',
            ],
        ]));

        $result = (new FawaterakRefund())->reasons('en');

        $this->assertCount(4, $result['data']);
    }

    public function test_it_creates_a_refund_request()
    {
        $this->fakeApi('*/api/v3/refund/create', Http::response([
            'status' => 'success',
            'message' => 'Refunded successfully',
        ]));

        $result = (new FawaterakRefund())
            ->setRefundType(FawaterakRefund::INTEGRATION_TRANSACTION)
            ->setRefundId(12345)
            ->setReason('Customer changed their mind')
            ->setRefundableAmount(50)
            ->setComment('Partial refund for order #1001')
            ->create();

        $this->assertSame('success', $result['status']);
        $this->assertSame('Refunded successfully', $result['message']);

        $this->assertSame([
            'refund_type' => '3',
            'refund_id' => 12345,
            'reason' => 'Customer changed their mind',
            'refundable_amount' => 50,
            'comment' => 'Partial refund for order #1001',
        ], $this->bodyFor('refund/create'));
    }

    public function test_the_comment_is_optional()
    {
        $this->fakeApi('*/api/v3/refund/create', Http::response([
            'status' => 'success', 'message' => 'Refunded successfully',
        ]));

        (new FawaterakRefund())
            ->setRefundType(FawaterakRefund::INVOICE)
            ->setRefundId(1)
            ->setReason('Other')
            ->setRefundableAmount(10)
            ->create();

        $this->assertArrayNotHasKey('comment', $this->bodyFor('refund/create'));
    }

    public function test_creating_without_required_fields_throws()
    {
        $this->expectException(MissingPaymentInfoException::class);

        (new FawaterakRefund())->setRefundId(1)->create();
    }

    public function test_a_rejected_refund_is_surfaced()
    {
        $this->fakeApi('*/api/v3/refund/create', Http::response([
            'status' => 'error',
            'message' => 'Refundable amount exceeds the remaining balance',
        ], 400));

        $result = (new FawaterakRefund())
            ->setRefundType(FawaterakRefund::INVOICE)
            ->setRefundId(1)
            ->setReason('Other')
            ->setRefundableAmount(999999)
            ->create();

        $this->assertSame('error', $result['status']);
        $this->assertSame('Refundable amount exceeds the remaining balance', $result['message']);
    }

    public function test_it_lists_refund_requests_with_pagination()
    {
        $this->fakeApi('*/api/v3/refund/index', Http::response([
            'current_page' => 1,
            'data' => [[
                'id' => 42,
                'refundable_type' => 'Invoice',
                'refundable_amount' => 50,
                'status' => 'pending',
                'refundable_id' => 12345,
                'reason' => 'Other',
                'created_at' => '2026-06-01 10:00:00',
                'can_delete' => true,
            ]],
            'per_page' => 10,
            'last_page' => 1,
            'total' => 1,
            'from' => 1,
            'to' => 1,
            'next_page_url' => null,
            'prev_page_url' => null,
        ]));

        $result = (new FawaterakRefund())->all();

        $this->assertSame('success', $result['status']);
        $this->assertSame(42, $result['data'][0]['id']);
        $this->assertSame(10, $result['pagination']['per_page']);
        $this->assertSame(1, $result['pagination']['total']);
    }

    public function test_it_reads_refund_details()
    {
        $this->fakeApi('*/api/v3/refund/details/42', Http::response([
            'id' => 42,
            'refundable_amount' => 50,
            'status' => 'pending',
            'reason' => 'Other',
            'created_at' => '2026-06-01 10:00:00',
        ]));

        $result = (new FawaterakRefund())->details(42);

        $this->assertSame('success', $result['status']);
        $this->assertSame(42, $result['data']['id']);
    }

    public function test_missing_refund_details_are_reported_as_failed()
    {
        $this->fakeApi('*/api/v3/refund/details/99', Http::response([]));

        $result = (new FawaterakRefund())->details(99);

        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['data']);
    }

    public function test_it_deletes_a_pending_refund_request()
    {
        $this->fakeApi('*/api/v3/refund/delete', Http::response(['status' => 'success']));

        $result = (new FawaterakRefund())->delete(42);

        $this->assertSame('success', $result['status']);
        $this->assertSame(['refund_id' => 42], $this->bodyFor('refund/delete'));
    }

    public function test_deleting_an_approved_refund_is_rejected()
    {
        $this->fakeApi('*/api/v3/refund/delete', Http::response([
            'status' => 'error',
            'message' => 'Request not found or already approved',
        ], 400));

        $result = (new FawaterakRefund())->delete(42);

        $this->assertSame('error', $result['status']);
        $this->assertSame('Request not found or already approved', $result['message']);
    }

    public function test_deleting_without_an_id_throws()
    {
        $this->expectException(MissingPaymentInfoException::class);

        (new FawaterakRefund())->delete();
    }
}
