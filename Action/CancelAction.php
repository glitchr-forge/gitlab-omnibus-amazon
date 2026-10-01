<?php

namespace Omnibus\Amazon\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Amazon\Api;
use Omnibus\Request\Cancel;
use Omnibus\Request\Request;

/** PUT /shipping/v2/shipments/{shipmentId}/cancel: the tracking id is accepted as the shipment id Amazon returned. */
final class CancelAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Cancel;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Cancel);
        $this->api->call('PUT', '/shipping/v2/shipments/'.rawurlencode($request->trackingNumber).'/cancel');
        $request->setResult(true);
    }
}
