# EDPay for Magento 2 / Adobe Commerce — Integration Preview

This repository contains an early official integration preview for Magento 2 / Adobe Commerce. **It is not production-ready.** The current source does not provide complete Magento admin configuration or checkout/payment lifecycle wiring. Do not enable it on a live store or use it to collect real payments.

The payment model creates a payment parent and then attaches hosted checkout to that payment. The webhook correlates successful events by the merchant reference. The notes below describe the current preview implementation.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.x
- PHP 8.1 or 8.2 with the `curl` and `json` extensions
- EDPay merchant credentials for sandbox testing

## Install the source preview

From the Magento installation root, clone the repository into the module directory and run the Magento setup commands:

```bash
mkdir -p app/code/Edpay/Payment
git clone https://github.com/edutecka/edpay-magento2-module.git app/code/Edpay/Payment
bin/magento module:enable Edpay_Payment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

There is no Composer package or signed release archive documented for installation. The repository's `composer.json` identifies the module, but does not establish that it has been published to a package registry.

## Current implementation and configuration gaps

- The payment model creates one parent payment through `POST /v1/payments`, then starts hosted checkout through `POST /v1/payments/{id}/public-checkout`; it uses stable order-based idempotency keys and builds the hosted URL from the returned opaque token at `https://pay.edutecka.com/pay?token=...&pk=...`.
- The configured API URL defaults to https://api-pay.edutecka.com, the single EDPay API host. Test and live access use the same host; matching pk_test_... / sk_test_... or pk_live_... / sk_live_... prefixes determine the key mode.
- The current repository does not include Magento admin configuration fields for the API URL, public/private keys or webhook secret, nor complete checkout commands that invoke the hosted-link method and redirect the customer. Add and verify that integration before attempting a checkout.
- The checkout method requires a valid public/private API key pair from the same environment (`pk_test_...` with `sk_test_...`, or `pk_live_...` with `sk_live_...`) and rejects invalid pairs before contacting the API.
- The payment model stores the payment ID and merchant reference in order payment AdditionalInformation. The webhook finds the order by reference, validates status/amount/currency, and prevents reprocessing once invoiced. The webhook route is declared at `/edpay/webhook/index`.
- The webhook controller requires a configured webhook secret and the `Payment-Signature` header. EDPay signs `timestamp + "." + raw request body` with HMAC-SHA256 and sends `Payment-Signature: t=<unix-seconds>,v1=<hex>`. It rejects missing secrets/headers, malformed signatures, timestamps outside the 300-second default window, and invalid HMAC values; comparison is constant-time and occurs before event parsing. The window can be changed through Magento config path `payment/edpay_gateway/webhook_tolerance_seconds` (default `300`).
- Parent and hosted-checkout creation use separate stable order-based idempotency keys.

## Sandbox validation checklist

After completing the missing configuration and routing work in a development environment:

1. Use sandbox credentials and confirm the API endpoint and payment creation and hosted-checkout response with EDPay.
2. Verify checkout redirects only to the hosted URL returned by the API.
3. Register the actual Magento webhook route and test signed and invalid-signature requests using the raw body.
4. Test successful, failed, duplicate, delayed and unknown-order events; inspect invoice creation and order state changes.
5. Confirm amounts, currency, order mapping and idempotency behavior, and review compatibility with the exact Magento patch version.

The declared callback route is `/edpay/webhook/index` (for example, `https://store.example/edpay/webhook/index`). The module still requires a Magento deployment to enable the route and configure the missing API and webhook credentials.

## Documentation

- [Magento integration guide](https://docs-pay.edutecka.com/plugins/magento/)
- [Payments API](https://docs-pay.edutecka.com/api/payments/)
- [Authentication and API keys](https://docs-pay.edutecka.com/guides/authentication/)
- [Webhooks](https://docs-pay.edutecka.com/api/webhooks/)

## Other official SDK repositories

- [JavaScript / TypeScript SDK](https://github.com/edutecka/edpay-javascript-sdk)
- [PHP SDK](https://github.com/edutecka/edpay-php-sdk)
- [Python SDK](https://github.com/edutecka/edpay-python-sdk)
- [Java SDK](https://github.com/edutecka/edpay-java-sdk)
- [.NET SDK](https://github.com/edutecka/edpay-dot-net-sdk)

## Other official plugin repositories

- [WooCommerce (WordPress)](https://github.com/edutecka/edpay-woocommerce-plugin)
- [Shopify](https://github.com/edutecka/edpay-shopify-app)
- [PrestaShop](https://github.com/edutecka/edpay-prestashop-module)
- [OpenCart](https://github.com/edutecka/edpay-opencart-extension)
- [WHMCS](https://github.com/edutecka/edpay-whmcs-gateway)

## License

MIT. See [LICENSE](LICENSE).
