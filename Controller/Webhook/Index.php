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

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderFactory = $orderFactory;
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
        $signature = $this->getRequest()->getHeader('X-EDPay-Signature');

        $scopeConfig = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $secret = $scopeConfig->getValue('payment/edpay_gateway/webhook_secret');

        if ($secret) {
            $expected = hash_hmac('sha256', $payload, $secret);
            if (!hash_equals($expected, (string) $signature)) {
                return $result->setHttpResponseCode(401)->setData(['error' => 'Assinatura HMAC inválida']);
            }
        }

        $event = json_decode($payload, true);
        if (!$event || empty($event['type'])) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'Payload JSON inválido']);
        }

        if ($event['type'] === 'payment.succeeded') {
            $data = $event['data'] ?? [];
            $orderIncrementId = $data['metadata']['magento_order_id'] ?? null;
            if ($orderIncrementId) {
                $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
                if ($order->getId() && $order->canInvoice()) {
                    $invoice = $order->prepareInvoice();
                    $invoice->register()->pay();
                    $order->addRelatedObject($invoice);
                    $order->setState(Order::STATE_PROCESSING);
                    $order->setStatus(Order::STATE_PROCESSING);
                    $order->addCommentToStatusHistory("Pagamento EDPay confirmado (ID: {$data['id']}) via {$data['method']}.");
                    $order->save();
                }
            }
        }

        return $result->setData(['status' => 'success']);
    }
}
