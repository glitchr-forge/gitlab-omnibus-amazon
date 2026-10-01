<?php

namespace Omnibus\Amazon\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Amazon\Api;
use Omnibus\Amazon\Mapping;
use Omnibus\Model\Tracking as TrackingModel;
use Omnibus\Model\TrackingEvent;
use Omnibus\Request\Request;
use Omnibus\Request\Tracking;

/** GET /shipping/v2/tracking: the package's event history, oldest first. */
final class TrackingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Tracking;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Tracking);
        $data = $this->api->call('GET', '/shipping/v2/tracking', null, ['trackingId' => $request->trackingNumber, 'carrierId' => 'AMZN_UK']);
        $events = [];
        foreach ($data['eventHistory'] ?? [] as $event) {
            $events[] = new TrackingEvent(new \DateTimeImmutable((string) ($event['eventTime'] ?? 'now')), Mapping::status($event['eventCode'] ?? null), (string) ($event['eventCode'] ?? ''), trim(implode(' ', array_filter([$event['location']['city'] ?? null, $event['location']['countryCode'] ?? null]))) ?: null, $event['eventCode'] ?? null);
        }
        usort($events, static fn (TrackingEvent $a, TrackingEvent $b) => $a->at <=> $b->at);
        $request->setResult(new TrackingModel('amazon', $request->trackingNumber, Mapping::status($data['summary']['status'] ?? ($events ? $events[array_key_last($events)]->code : null)), $events));
    }
}
