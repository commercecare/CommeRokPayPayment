<?php
namespace CommeRokPayPayment\Library\RokPayLibrary;


use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

abstract class AbstractRokPay implements RokPayInterface
{

    protected $apiUrl;
    protected $shopNumber;
    protected $apiKey;

    /**
     * @return mixed
     */
    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    /**
     * @param mixed $apiUrl
     */
    public function setApiUrl($apiUrl): void
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param mixed $apiKey
     */
    public function setApiKey($apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @return mixed
     */
    public function getShopNumber()
    {
        return $this->shopNumber;
    }

    /**
     * @param mixed $shopNumber
     */
    public function setShopNumber($shopNumber): void
    {
        $this->shopNumber = $shopNumber;
    }

    public function callToApi($data, $header = ['Content-Type'=>'application/json'])
    {

        $client = new Client();

        $response = $client->post($this->getApiUrl(), [
            RequestOptions::JSON => [
                "amount"=> $data['amount'],
                "cancellationUrl"=>  $data['cancellationUrl'],
                "currency"=>  $data['currency'],
                "failureUrl"=> $data['failureUrl'],
                "products"=>  $data['products'],
                "shopNumber"=>  $this->getShopNumber(),
                "shopOrderId" =>$data['shopOrderId'],
                "shopTransactionId"=>$data['shopTransactionId'],
                "successUrl"=> $data['successUrl'],
                'digest' => $data['digest']
            ] // or 'json' => [...]
        ]);


        $responseBody = $response->getBody()->getContents();
            return $response;
    }

    /**
     * @param $response
     * @param string $type
     * @param string $pattern
     * @return false|mixed|string
     */
    public function formatResponse($response, $type = 'checkout', $pattern = 'json')
    {
        $sslcz = json_decode($response, true);

        if ($type != 'checkout') {
            return $sslcz;
        } else {
            if (isset($sslcz['GatewayPageURL']) && $sslcz['GatewayPageURL'] != "") {
                // this is important to show the popup, return or echo to send json response back
                if($this->getApiUrl() != null && $this->getApiUrl() == 'https://staging.rokpay.cloud:8081/rokpay') {
                    $response = json_encode(['status' => 'SUCCESS', 'data' => $sslcz['GatewayPageURL'], 'logo' => $sslcz['storeLogo']]);
                } else {
                    $response = json_encode(['status' => 'success', 'data' => $sslcz['GatewayPageURL'], 'logo' => $sslcz['storeLogo']]);
                }
            } else {
                $response = json_encode(['status' => 'fail', 'data' => null, 'message' => $sslcz['failedreason']]);
            }

            if ($pattern == 'json') {
                return $response;
            } else {
                echo $response;
            }
        }
    }

}
