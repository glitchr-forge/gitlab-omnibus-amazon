# omnibus/amazon

Amazon Shipping for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): rates,
purchased shipments with their labels, tracking and cancellation - the Selling Partner API's
Shipping v2, with Login with Amazon.

```yaml
omnibus:
    gateways:
        amazon:
            factory: amazon
            options:
                client_id: '%env(AMAZON_LWA_CLIENT_ID)%'
                client_secret: '%env(AMAZON_LWA_CLIENT_SECRET)%'
                refresh_token: '%env(AMAZON_REFRESH_TOKEN)%'
                region: eu                               # eu, na, fe
                business_id: AmazonShipping_UK           # the shipping business
                sandbox: true
                rates: [...]                             # optional: configured prices instead of getRates
```

The service is Amazon's serviceId as rates return it (none: the cheapest rate is purchased).
Shipment options: `label_format` (PDF, ZPL), `description`. No pickup points.

Credentials: an SP-API application (Seller Central > Develop Apps, the Shipping role) gives the
LWA client id and secret; authorising it on the Amazon Shipping account gives the refresh token.

Built from Amazon's published SP-API documentation and tested on recorded answers; not yet run
against the sandbox: that needs the credentials above.

License: LGPL-3.0-or-later.
