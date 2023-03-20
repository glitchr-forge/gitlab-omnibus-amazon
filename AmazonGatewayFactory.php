<?php

namespace Omnibus\Amazon;

use Omnibus\Core\GatewayFactory;

class AmazonMwsGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'omnibus.factory_name' => 'amazon-mws',
            'omnibus.factory_title' => 'Amazon MWS',
        ]);
    }
}