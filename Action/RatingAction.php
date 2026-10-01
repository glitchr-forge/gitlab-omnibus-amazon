<?php

namespace Omnibus\Amazon\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Amazon\Api;
use Omnibus\Amazon\Mapping;
use Omnibus\Model\Rate;
use Omnibus\Request\Rating;
use Omnibus\Request\Request;

/** POST /shipping/v2/shipments/rates: every service Amazon offers, priced; the rate id travels in the Rate's service for purchase. */
final class RatingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Rating;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Rating);
        $s = $request->shipment;
        $data = $this->api->call('POST', '/shipping/v2/shipments/rates', [
            'shipFrom' => Mapping::address($s->sender),
            'shipTo' => Mapping::address($s->recipient),
            'packages' => array_map(static fn ($p, $i) => Mapping::package($p, $s, $i), $s->parcels, array_keys($s->parcels)),
            'channelDetails' => ['channelType' => 'EXTERNAL'],
            'shipDate' => ($s->shippingDate ?? new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
        ]);
        $rates = [];
        foreach ($data['rates'] ?? [] as $rate) {
            $price = $rate['totalCharge'] ?? [];
            $window = $rate['promise']['deliveryWindow'] ?? [];
            $days = null;
            if (!empty($window['end'])) {
                $days = max(0, (int) ceil((strtotime($window['end']) - ($s->shippingDate ?? new \DateTimeImmutable())->getTimestamp()) / 86400));
            }
            $rates[] = new Rate('amazon', (string) ($rate['serviceId'] ?? $rate['rateId']), (string) ($rate['serviceName'] ?? 'Amazon Shipping'), (int) round(((float) ($price['value'] ?? 0)) * 100), strtoupper((string) ($price['unit'] ?? 'EUR')), $days);
        }
        usort($rates, static fn (Rate $a, Rate $b) => $a->amount <=> $b->amount);
        $request->setResult($rates);
    }
}
