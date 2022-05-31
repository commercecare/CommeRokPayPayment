<?php

namespace CommeRokPayPayment\Library\RokPayLibrary;

use CommeRokPayPayment\Components\Configuration\Config;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use function Symfony\Component\Translation\t;


class RokPayNotification extends AbstractRokPay
{
    protected $data = [];
    protected $config = [];

    private $successUrl;
    private $cancelUrl;
    private $failedUrl;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;




    # FUNCTION TO CHECK HASH VALUE

    /**
     * @param array $config
     * @param OrderTransactionStateHandler $transactionStateHandler
     */
    public function __construct(Config $configuration, OrderTransactionStateHandler $transactionStateHandler)
    {
        $this->config = $configuration->getConfig();
        $this->setShopNumber($this->config['apiCredidentials']['shop_number']);
        $this->setApiKey($this->config['apiCredidentials']['api_key']);
        $this->transactionStateHandler = $transactionStateHandler;


    }

    protected function Rp_digest_hash($post_data, $shopNumber = "")
    {
        if (isset($post_data)) {


            $digest = $shopNumber . $post_data['apiKey'];


            $digestHash = hash('sha512', $digest);

        } else {
            $this->error = 'Required data mission. ex: verify_key, verify_sign';
            return false;
        }

        return $digestHash;
    }


    public function makePayment(array $requestData, $type = 'purchase', $pattern = 'json')
    {

        if (empty($requestData)) {
            return "Please provide a valid information list about transaction with transaction id, amount, success url, fail url, cancel url, store id and pass at least";
        }

        $header = [];

        $this->setApiUrl($this->config['apiDomain'] . $this->config['apiUrl']['make_payment']);


        $data = $this->setProcessingInfo($requestData);


        $context = Context::createDefaultContext();

        $orderId = $requestData['shopOrderId'];
        $tranId = $requestData['shopTransactionId'];

        $response = $this->callToApi($this->data, $header);

        if($response==null){
            $this->transactionStateHandler->fail($tranId,$context);

        }

        return $response;
    }


    public function setProcessingInfo(array $info)
    {


        $this->data['amount'] = $info['amount'];
        $this->data['cancellationUrl'] = $info['cancellationUrl'];
        $this->data['currency'] = $info['currency'];
        $this->data['failureUrl'] = $info['failureUrl'];
        $this->data['products'] = $info['products'];
        $this->data['shopNumber'] = $this->getShopNumber();
        $this->data['shopOrderId'] = $info['shopOrderId'];
        $this->data['shopTransactionId'] = $info['shopTransactionId'];
        $this->data['successUrl'] = $info['successUrl'];
        $this->data['digest'] = $this->Rp_digest_hash($info, $this->getShopNumber());


        return $this->data;

    }


}
