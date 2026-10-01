<?php

namespace Omnibus\Amazon;

use Omnibus\Amazon\Action\CancelAction;
use Omnibus\Amazon\Action\RatingAction;
use Omnibus\Amazon\Action\ShippingAction;
use Omnibus\Amazon\Action\TrackingAction;
use Omnibus\Config;
use Omnibus\Exception\InvalidConfigException;
use Omnibus\GatewayFactory;
use Symfony\Component\HttpClient\HttpClient;

/**
 *   options:
 *     client_id: '%env(AMAZON_LWA_CLIENT_ID)%'        # the SP-API app's Login with Amazon credentials
 *     client_secret: '%env(AMAZON_LWA_CLIENT_SECRET)%'
 *     refresh_token: '%env(AMAZON_REFRESH_TOKEN)%'    # the authorised Amazon Shipping account
 *     region: eu                                      # eu, na or fe
 *     business_id: AmazonShipping_UK                  # the shipping business (AmazonShipping_UK, _US, _IN, _IT, _ES, _FR, _SA, _AE, _EG, _JP...)
 *     sandbox: true
 *     rates: [...]                                    # optional: configured prices instead of getRates
 *
 * No pickup points: Amazon Shipping delivers to the door.
 */
final class AmazonGatewayFactory extends GatewayFactory
{
    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibus.factory_name' => 'amazon',
            'omnibus.factory_title' => 'Amazon Shipping',
            'omnibus.required_options' => ['client_id', 'client_secret', 'refresh_token'],
            'region' => 'eu',
            'business_id' => null,
            'sandbox' => false,
            'omnibus.api' => function (Config $c) {
                $http = $this->http ?? (class_exists(HttpClient::class) ? HttpClient::create() : throw new InvalidConfigException('The "amazon" gateway needs symfony/http-client.'));

                return new Api($http, (string) $c['client_id'], (string) $c['client_secret'], (string) $c['refresh_token'], (string) $c['region'], $c['business_id'] ?: null, (bool) $c['sandbox']);
            },
            'omnibus.action.rating' => static fn (Config $c) => $c->get('rates') ? null : new RatingAction(),
            'omnibus.action.shipping' => new ShippingAction(),
            'omnibus.action.tracking' => new TrackingAction(),
            'omnibus.action.cancel' => new CancelAction(),
        ]);
    }
}
