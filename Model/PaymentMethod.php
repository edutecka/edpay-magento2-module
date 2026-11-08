<?php
namespace Edpay\Payment\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order;

class PaymentMethod extends AbstractMethod
{
    const CODE = 'edpay_gateway';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canOrder = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;

    /**
     * Create Hosted Checkout Session via EDPay API
     *
     * @param Order $order
     * @return string Checkout URL
     * @throws \Exception
     */
    public function createCheckoutSession(Order $order): string
    {
        $publicKey = $this->getConfigData('public_key');
        $privateKey = $this->getConfigData('private_key');
        $apiUrl = rtrim($this->getConfigData('api_url') ?: 'https://api-pay.edutecka.com', '/');

        $orderId = $order->getIncrementId();
        $amountCentavos = (int) round($order->getGrandTotal() * 100);

        $payload = [
            'title' => "Encomenda Magento #{$orderId}",
            'description' => "Pagamento da Encomenda #{$orderId}",
            'amount' => $amountCentavos,
            'currency' => $order->getOrderCurrencyCode() ?: 'MZN',
            'metadata' => [
                'magento_order_id' => $orderId,
                'customer_email' => $order->getCustomerEmail(),
            ],
            'success_url' => $order->getStore()->getUrl('checkout/onepage/success'),
            'cancel_url' => $order->getStore()->getUrl('checkout/cart'),
        ];

        $ch = curl_init("{$apiUrl}/v1/core/payment-links");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "X-EDPay-Public-Key: {$publicKey}",
                "X-EDPay-Private-Key: {$privateKey}",
                "Idempotency-Key: magento-{$orderId}-" . time(),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception("Falha ao comunicar com a EDPay API (HTTP {$httpCode})");
        }

        $result = json_decode((string) $response, true);
        $checkoutUrl = $result['data']['hosted_url'] ?? $result['data']['checkout_url'] ?? null;

        if (!$checkoutUrl) {
            throw new \Exception("URL de checkout não fornecida pela EDPay.");
        }

        return $checkoutUrl;
    }
}
