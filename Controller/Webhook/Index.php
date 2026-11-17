<?php
namespace Edpay\Payment\Controller\Webhook;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Controller\Result\JsonFactory;

class Index extends Action implements CsrfAwareActionInterface
{
    protected $resultJsonFactory;
    protected $orderFactory;
    protected $paymentCollectionFactory;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory $paymentCollectionFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderFactory = $orderFactory;
        $this->paymentCollectionFactory = $paymentCollectionFactory;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $payload = file_get_contents('php://input');
        $signatureHeader = (string) $this->getRequest()->getHeader('Payment-Signature');

        $scopeConfig = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $secret = trim((string) $scopeConfig->getValue('payment/edpay_gateway/webhook_secret'));
        $toleranceConfig = $scopeConfig->getValue('payment/edpay_gateway/webhook_tolerance_seconds');
        $tolerance = filter_var($toleranceConfig === null || $toleranceConfig === '' ? 300 : $toleranceConfig, FILTER_VALIDATE_INT);
        $tolerance = $tolerance !== false && $tolerance >= 0 ? $tolerance : 300;
        $signatureFields = [];
        foreach (explode(',', $signatureHeader) as $field) {
            $parts = explode('=', trim($field), 2);
            if (count($parts) === 2 && in_array($parts[0], ['t', 'v1'], true)) {
                if (array_key_exists($parts[0], $signatureFields)) {
                    $signatureFields[$parts[0]] = null;
                    break;
                }
                $signatureFields[$parts[0]] = trim($parts[1]);
            }
        }
        $timestamp = $signatureFields['t'] ?? '';
        $signature = $signatureFields['v1'] ?? '';
        $signedAt = preg_match('/^\d+$/', $timestamp) ? filter_var($timestamp, FILTER_VALIDATE_INT) : false;
        $validHeader = $signedAt !== false && preg_match('/^[a-f0-9]{64}$/i', $signature);
        $validTimestamp = $validHeader && abs(time() - $signedAt) <= $tolerance;
        $expected = $validTimestamp ? hash_hmac('sha256', $timestamp . '.' . $payload, $secret) : '';

        if ($payload === '' || $secret === '' || $signatureHeader === '' || !$validTimestamp
            || !hash_equals($expected, strtolower($signature))) {
            return $result->setHttpResponseCode(401)->setData(['error' => 'Invalid webhook signature']);
        }

        $event = json_decode($payload, true);
        if (!$event || empty($event['type'])) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'Invalid JSON payload']);
        }

        if ($event['type'] === 'payment.succeeded') {
            $data = $event['data'] ?? [];
            $reference = (string) ($data['reference'] ?? '');
            $paymentId = (string) ($data['id'] ?? '');
            $payments = $this->paymentCollectionFactory->create()->addFieldToFilter('method', 'edpay_gateway');
            $order = null;
            $payment = null;
            foreach ($payments as $candidatePayment) {
                if ($reference !== '' && $candidatePayment->getAdditionalInformation('edpay_payment_reference') === $reference) {
                    $payment = $candidatePayment;
                    $order = $this->orderFactory->create()->load($candidatePayment->getParentId());
                    break;
                }
            }
            if (!$order || !$order->getId() || !$payment || ($data['status'] ?? '') !== 'succeeded'
                || $paymentId === '' || !isset($data['amount_minor'])
                || (int) $data['amount_minor'] !== (int) round((float) $order->getGrandTotal() * 100)
                || strtoupper((string) ($data['currency'] ?? '')) !== strtoupper((string) $order->getOrderCurrencyCode())) {
                return $result->setHttpResponseCode(409)->setData(['error' => 'Payment reference, status, amount, or currency mismatch']);
            }
            if ($payment->getAdditionalInformation('edpay_payment_id') || !$order->canInvoice()) {
                return $result->setHttpResponseCode(409)->setData(['error' => 'Payment already processed or order cannot be invoiced']);
            }
            $payment->setAdditionalInformation('edpay_payment_id', $paymentId)->save();
            $invoice = $order->prepareInvoice();
            $invoice->register()->pay();
            $order->addRelatedObject($invoice);
            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus(Order::STATE_PROCESSING);
            $order->addCommentToStatusHistory((string) __('EDPay payment confirmed (transaction ID: %1; payment method: %2).', $paymentId, $data['method'] ?? 'Not provided'));
            $order->save();
        }

        return $result->setData(['status' => 'success']);
    }
}
