<?php

namespace Omnibus\Amazon\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Amazon\Api;
use Omnibus\Amazon\Mapping;
use Omnibus\Exception\CarrierException;
use Omnibus\Model\Label;
use Omnibus\Request\Request;
use Omnibus\Request\Shipping;

/**
 * getRates then purchaseShipment: the rate matching the service (a serviceId;
 * none: the cheapest) bought with a label (PDF 4x6; ZPL with option label_format).
 */
final class ShippingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Shipping;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Shipping);
        $s = $request->shipment;
        $zpl = 'ZPL' === strtoupper((string) $s->option('label_format', 'PDF'));
        $quote = $this->api->call('POST', '/shipping/v2/shipments/rates', [
            'shipFrom' => Mapping::address($s->sender),
            'shipTo' => Mapping::address($s->recipient),
            'packages' => array_map(static fn ($p, $i) => Mapping::package($p, $s, $i), $s->parcels, array_keys($s->parcels)),
            'channelDetails' => ['channelType' => 'EXTERNAL'],
            'shipDate' => ($s->shippingDate ?? new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
        ]);
        $rates = $quote['rates'] ?? [];
        usort($rates, static fn ($a, $b) => ((float) ($a['totalCharge']['value'] ?? 0)) <=> ((float) ($b['totalCharge']['value'] ?? 0)));
        $chosen = null;
        foreach ($rates as $rate) {
            if (null === $s->service || $s->service === ($rate['serviceId'] ?? null) || $s->service === ($rate['rateId'] ?? null)) {
                $chosen = $rate;
                break;
            }
        }
        if (null === $chosen) {
            throw new CarrierException('amazon', null === $s->service ? 'Amazon Shipping offers no rate for this shipment.' : sprintf('Amazon Shipping offers no "%s" for this shipment.', $s->service));
        }
        $data = $this->api->call('POST', '/shipping/v2/shipments', [
            'requestToken' => $quote['requestToken'] ?? '',
            'rateId' => $chosen['rateId'],
            'requestedDocumentSpecification' => ['format' => $zpl ? 'ZPL' : 'PDF', 'size' => ['width' => 4, 'length' => 6, 'unit' => 'INCH'], 'dpi' => 300, 'pageLayout' => 'DEFAULT', 'needFileJoining' => false, 'requestedDocumentTypes' => ['LABEL']],
        ]);
        $number = (string) ($data['packageDocumentDetails'][0]['trackingId'] ?? $data['shipmentId'] ?? '');
        if ('' === $number) {
            throw new CarrierException('amazon', 'Amazon Shipping purchased no shipment.');
        }
        $document = $data['packageDocumentDetails'][0]['packageDocuments'][0] ?? [];
        $content = isset($document['contents']) ? base64_decode((string) $document['contents']) : null;
        $request->setResult(new Label('amazon', $number, $content, $zpl ? Label::ZPL : Label::PDF, null, 'https://track.amazon.com/tracking/'.rawurlencode($number)));
    }
}
