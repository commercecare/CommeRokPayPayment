<?php
namespace CommeRokPayPayment\Components\Configuration;



use Shopware\Core\System\SystemConfig\SystemConfigService;

class Config
{
    private $projectPath;

    private $apiDomain;

    private $shopNumber;

    private $apiKey;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;



    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
        $this->projectPath = getenv('APP_URL');
        if($this->systemConfigService->get('CommeRokPayPayment.config.rpEnvironment') == "Staging"){
            $this->apiDomain = 'https://staging.rokpay.cloud:8081/rokpay';
        }else{
            $this->apiDomain = 'https://rokpay.cloud:8081/rokpay';

        }
        $this->shopNumber = $this->systemConfigService->get('CommeRokPayPayment.config.shopNumber');
        $this->apiKey = $this->systemConfigService->get('CommeRokPayPayment.config.apiKey');
    }


    public function getConfig()
    {


        if(str_contains($this->projectPath, "/public")){
            $this->projectPath = str_replace("/public", '', $this->projectPath);
        }
        if(str_contains($this->projectPath, "/staging")){
            $this->projectPath = str_replace("/staging", '', $this->projectPath);
        }

        // Setting all variables that are required for calling API's
        return [
            'projectPath' => $this->projectPath,
            'apiDomain' => $this->apiDomain,
            'apiCredidentials' => [
                'shop_number' => $this->shopNumber,
                'api_key' => $this->apiKey
            ],
            'apiUrl' => [
                'make_payment' => "/order/verify-shop"
            ],
            'success_url' => 'payment/success',
            'failed_url' => 'payment/fail',
            'cancel_url' => 'payment/cancel',
        ];
    }
}
