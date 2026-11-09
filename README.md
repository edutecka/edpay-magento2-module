# EDPay for Magento 2 / Adobe Commerce — Integration Preview

This repository contains an early official integration preview for Magento 2 / Adobe Commerce. **It is not production-ready.** The current source does not provide complete Magento admin configuration or checkout/payment lifecycle wiring. Do not enable it on a live store or use it to collect real payments.

The included payment model contains code to request a hosted payment link, and the webhook controller contains preliminary handling for successful payment events. The notes below describe the files currently in this repository, not a claim of a complete end-to-end Magento integration.

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

- The payment model's `createCheckoutSession()` method makes a `POST /v1/core/payment-links` request with `X-EDPay-Public-Key` and `X-EDPay-Private-Key` headers. It passes the order amount in minor units, currency, order metadata and return URLs, and expects `data.hosted_url` or `data.checkout_url` in the response.
- The configured API URL defaults to `https://api-pay.edutecka.com`. Confirm the correct endpoint and request/response contract with EDPay before testing; do not assume this preview's default is appropriate for your account or environment.
- The current repository does not include Magento admin configuration fields for the API URL, public/private keys or webhook secret, nor complete checkout commands that invoke the hosted-link method and redirect the customer. Add and verify that integration before attempting a checkout.
- The webhook controller is intended to handle `payment.succeeded` and invoice an order identified by `data.metadata.magento_order_id`. The repository does not currently include the Magento route declaration needed to establish the callback URL. Add and test the route before registering a webhook.
- The webhook controller only checks the HMAC signature when a secret is configured. With an empty/missing secret, it accepts the event without signature verification. Configure a secret and verify that invalid signatures are rejected before exposing or testing the callback.
- The payment-link request creates its idempotency value using the current time. Retrying a request can therefore use a different key; test duplicate submissions and order reconciliation before any deployment.

## Sandbox validation checklist

After completing the missing configuration and routing work in a development environment:

1. Use sandbox credentials and confirm the API endpoint and payment-link response with EDPay.
2. Verify checkout redirects only to the hosted URL returned by the API.
3. Register the actual Magento webhook route and test signed and invalid-signature requests using the raw body.
4. Test successful, failed, duplicate, delayed and unknown-order events; inspect invoice creation and order state changes.
5. Confirm amounts, currency, order mapping and idempotency behavior, and review compatibility with the exact Magento patch version.

The callback path implied by the controller and module name is `/edpay/webhook/index` (for example, `https://store.example/edpay/webhook/index`), but it is not usable until routing is added and verified in Magento.

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
