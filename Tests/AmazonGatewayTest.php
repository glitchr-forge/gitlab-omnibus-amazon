<?php

namespace Omnibus\Amazon\Tests;

use Omnibus\Amazon\AmazonGatewayFactory;
use Omnibus\Exception\CarrierException;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\Model\TrackingStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AmazonGatewayTest extends TestCase
{
    private array $calls = [];

    private static function shipment(?string $service = null): Shipment
    {
        return new Shipment(new Address('Glitch Art', ['1 Rue du Test'], '67000', 'Strasbourg', 'FR'), new Address('Alex Martin', ['10 Downing Street'], 'SW1A 2AA', 'London', 'GB', email: 'alex@example.org'), [new Parcel(850, 30, 20, 10, 2500, 'GBP')], $service, reference: 'ORDER-1042');
    }

    private function gateway(): \Omnibus\GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $path = (string) parse_url($url, \PHP_URL_PATH);
            $this->calls[] = [$method, $url, \is_string($options['body'] ?? null) && str_starts_with($options['body'], '{') ? json_decode($options['body'], true) : [], $options['headers']];

            return match (true) {
                str_contains($url, 'api.amazon.com/auth') => new MockResponse(json_encode(['access_token' => 'Atza|tok', 'expires_in' => 3600])),
                '/shipping/v2/shipments/rates' === $path => new MockResponse(json_encode(['payload' => ['requestToken' => 'rt-1', 'rates' => [
                    ['rateId' => 'r-std', 'serviceId' => 'std-uk', 'serviceName' => 'Amazon Shipping Standard', 'totalCharge' => ['value' => 4.2, 'unit' => 'GBP'], 'promise' => ['deliveryWindow' => ['start' => '2026-10-03T08:00:00Z', 'end' => '2026-10-05T20:00:00Z']]],
                    ['rateId' => 'r-exp', 'serviceId' => 'exp-uk', 'serviceName' => 'Amazon Shipping Express', 'totalCharge' => ['value' => 7.9, 'unit' => 'GBP']],
                ]]])),
                '/shipping/v2/shipments' === $path => new MockResponse(json_encode(['payload' => ['shipmentId' => 'sh-1', 'packageDocumentDetails' => [['trackingId' => 'TBA123456789000', 'packageDocuments' => [['type' => 'LABEL', 'format' => 'PDF', 'contents' => base64_encode('%PDF-1.4 amz')]]]]]])),
                '/shipping/v2/tracking' === $path => new MockResponse(json_encode(['payload' => ['trackingId' => 'TBA123456789000', 'summary' => ['status' => 'DELIVERED'], 'eventHistory' => [['eventCode' => 'DELIVERED', 'eventTime' => '2026-10-02T11:30:00Z', 'location' => ['city' => 'London', 'countryCode' => 'GB']], ['eventCode' => 'IN_TRANSIT', 'eventTime' => '2026-10-01T17:00:00Z', 'location' => ['city' => 'Coventry', 'countryCode' => 'GB']]]]])),
                str_ends_with($path, '/cancel') => new MockResponse(json_encode(['payload' => new \stdClass()])),
                default => new MockResponse(json_encode(['errors' => [['code' => 'NotFound', 'message' => 'No such resource '.$path]]]), ['http_code' => 404]),
            };
        });

        return (new AmazonGatewayFactory($http))->create(['client_id' => 'amzn1.application-oa2-client.x', 'client_secret' => 'secret', 'refresh_token' => 'Atzr|refresh', 'region' => 'eu', 'business_id' => 'AmazonShipping_UK', 'sandbox' => true]);
    }

    public function testRatesComeCheapestFirstWithTheDeliveryWindow(): void
    {
        $rates = $this->gateway()->rate(self::shipment());
        self::assertSame(['std-uk', 'exp-uk'], array_map(fn ($r) => $r->service, $rates));
        self::assertSame(420, $rates[0]->amount);
        self::assertSame('GBP', $rates[0]->currency);
        self::assertNotNull($rates[0]->days);
        self::assertStringStartsWith('https://sandbox.sellingpartnerapi-eu.amazon.com', $this->calls[1][1]);
        self::assertContains('x-amz-access-token: Atza|tok', $this->calls[1][3]);
        self::assertContains('x-amzn-shipping-business-id: AmazonShipping_UK', $this->calls[1][3]);
        self::assertSame('KILOGRAM', $this->calls[1][2]['packages'][0]['weight']['unit']);
    }

    public function testTheChosenRateIsPurchasedWithItsLabel(): void
    {
        $label = $this->gateway()->ship(self::shipment('exp-uk'));
        self::assertSame('TBA123456789000', $label->trackingNumber);
        self::assertSame('%PDF-1.4 amz', $label->content);
        self::assertSame('r-exp', $this->calls[2][2]['rateId']);
        self::assertSame('rt-1', $this->calls[2][2]['requestToken']);

        $this->calls = [];
        $this->gateway()->ship(self::shipment());
        self::assertSame('r-std', $this->calls[2][2]['rateId'], 'no service: the cheapest');

        $this->expectException(CarrierException::class);
        $this->gateway()->ship(self::shipment('nope'));
    }

    public function testTrackingAndCancel(): void
    {
        $gateway = $this->gateway();
        $tracking = $gateway->track('TBA123456789000');
        self::assertSame(TrackingStatus::DELIVERED, $tracking->status);
        self::assertSame(TrackingStatus::IN_TRANSIT, $tracking->events[0]->status);
        self::assertSame('London GB', $tracking->latest()->location);
        self::assertTrue($gateway->cancel('sh-1'));
        self::assertSame('PUT', end($this->calls)[0]);
    }
}
