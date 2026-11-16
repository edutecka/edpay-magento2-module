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
        $publicKey = trim((string) $this->getConfigData('public_key'));
        $privateKey = trim((string) $this->getConfigData('private_key'));
        $publicMode = preg_match('/^pk_(test|live)_[a-fA-F0-9]{24}$/D', $publicKey, $publicMatch) ? $publicMatch[1] : null;
        $privateMode = preg_match('/^sk_(test|live)_[a-fA-F0-9]{64}$/D', $privateKey, $privateMatch) ? $privateMatch[1] : null;
        if ($publicMode === null || $privateMode === null || $publicMode !== $privateMode) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Configure a valid EDPay public key and private key from the same environment before accepting payments.')
            );
        }
        $apiUrl = rtrim($this->getConfigData('api_url') ?: 'https://api-pay.edutecka.com', '/');

        $orderId = $order->getIncrementId();
        $amountCentavos = (int) round($order->getGrandTotal() * 100);

        $headers = [
            'Content-Type: application/json',
            "X-EDPay-Public-Key: {$publicKey}",
            "X-EDPay-Private-Key: {$privateKey}",
        ];
        $request = function (string $url, array $payload, string $key) use ($headers): array {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
                CURLOPT_HTTPHEADER => array_merge($headers, ["Idempotency-Key: {$key}"]),
                CURLOPT_POSTFIELDS => json_encode($payload),
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            return [$httpCode, json_decode((string) $response, true), $error];
        };
        $prefix = 'magento-order-' . $orderId;
        [$httpCode, $link, $error] = $request("{$apiUrl}/v1/payment-links", [
            'amount_minor' => $amountCentavos,
            'currency' => $order->getOrderCurrencyCode() ?: 'MZN',
        ], "{$prefix}-link-create");
        $linkId = $link['data']['id'] ?? null;
        if ($error || $httpCode < 200 || $httpCode >= 300 || !is_string($linkId) || $linkId === '') {
            throw new \Exception((string) __('Failed to create an EDPay payment link (HTTP %1).', $httpCode));
        }
        [$sessionCode, $session, $sessionError] = $request("{$apiUrl}/v1/checkout/sessions", [
            'payment_link_id' => $linkId,
        ], "{$prefix}-checkout-session");
        $token = $session['data']['token'] ?? null;
        if ($sessionError || $sessionCode < 200 || $sessionCode >= 300 || !is_string($token) || $token === '') {
            throw new \Exception((string) __('Failed to create an EDPay checkout session (HTTP %1).', $sessionCode));
        }
        $order->getPayment()->setAdditionalInformation('edpay_payment_link_id', $linkId);
        $order->getPayment()->save();
        return 'https://pay.edutecka.com/pay?' . http_build_query([
            'token' => $token, 'pk' => $publicKey,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
