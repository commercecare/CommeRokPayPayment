<?php

namespace CommeRokPayPayment\Controller\Api;


use Enqueue\Util\UUID;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\RequestOptions;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class ApiTestController
{




    /**
     * @Route(path="/api/_action/rokpay-api-test/verify")
     */
    public function check(RequestDataBag $dataBag): JsonResponse
    {
        $shopNumber = $dataBag->get('CommeRokPayPayment.config.shopNumber');
        $apiKey = $dataBag->get('CommeRokPayPayment.config.apiKey');
        if($dataBag->get('CommeRokPayPayment.config.rpEnvironment') == "Staging"){
           $apiDomain = 'https://staging.rokpay.cloud:8081/rokpay';
        }else{
            $apiDomain = 'https://rokpay.cloud:8081/rokpay';

        }

        $success = false;

        $digest = $shopNumber . $apiKey;


        $digestHash = hash('sha512', $digest);

        $products = array(['name' => 'Test', 'price'=> 0.01, 'quantity' => 1]);


        $client = new Client();


        try{
            $response = $client->post($apiDomain . '/order/verify-shop', [
                RequestOptions::JSON => [
                    "amount"=> 0.01,
                    "cancellationUrl"=> '',
                    "currency"=>  "EUR",
                    "failureUrl"=> "",
                    "products"=>  $products,
                    "shopNumber"=>  $shopNumber,
                    "shopOrderId" => '226a67c787f944b9b0e51bbf1e1294c1',
                    "shopTransactionId"=>'52dd326bd7f24609aa60f806bf46105d',
                    "successUrl"=> '',
                    'digest' => $digestHash
                ] // or 'json' => [...]
            ]);

            $responseFormatted = json_decode($response->getBody(),true);

            if ($response->getStatusCode() == 200 && $responseFormatted) {
                $success = true;
            }

            return new JsonResponse(['success' => $success]);
        }catch (BadResponseException $e){

            $success = false;
            return new JsonResponse(['success' => $success]);

        }
    }
}
