<?php
namespace CommeRokPayPayment\Service;


use CommeRokPayPayment\Components\Configuration\Config;
use CommeRokPayPayment\Library\RokPayLibrary\RokPayNotification;
use GuzzleHttp\RequestOptions;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use GuzzleHttp\Client;

class RokPayPaymentService implements AsynchronousPaymentHandlerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /** @var RokPayNotification */
    private $rokPayNotification;

    /** @var Config */
    private $config;


    /**
     * @param ContainerInterface $container
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(
                                OrderTransactionStateHandler $transactionStateHandler,
                                ContainerInterface $container,
                                SystemConfigService $systemConfigService)
    {
        $this->container = $container;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->systemConfigService = $systemConfigService;
        $this->config = new Config($this->systemConfigService);
        $this->rokPayNotification = new RokPayNotification($this->config, $transactionStateHandler);

    }
    /**
     * @internal
     * @required
     */
    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        $previous = $this->container;
        $this->container = $container;

        return $previous;
    }


    /**
     * Generates a URL from the given parameters.
     *
     * @see UrlGeneratorInterface
     */
    protected function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->container->get('router')->generate($route, $parameters, $referenceType);
    }

    function array_push_assoc($array, $key, $value){
        $array[$key] = $value;
        return $array;
    }


    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {


        $projectPath = getenv('APP_URL');
        try {
            $post_data = array();

            # PROCESSING INFORMATION
            $tranId = $transaction->getOrderTransaction()->getId();
            $orderId = $transaction->getOrder()->getId();


            $post_data['amount'] = $transaction->getOrder()->getAmountTotal();
            $post_data['cancellationUrl'] =$projectPath . $this->generateUrl('frontend.payment.cancel', ['orderId' => $orderId, 'tranId' => $tranId] );
            $post_data['currency'] = $transaction->getOrder()->getCurrency()->getIsoCode();
            $products =  $transaction->getOrder()->getLineItems();
            $post_data['products'] = array();

            foreach ($products as $product){
                $productObj = [];

                $productObj = $this->array_push_assoc($productObj, 'name' , $product->getLabel());
                $productObj = $this->array_push_assoc($productObj, 'price' , $product->getUnitPrice());
                $productObj = $this->array_push_assoc($productObj, 'quantity' , $product->getQuantity());

                array_push($post_data['products'], $productObj);

            }


            $post_data['shopOrderId'] = $orderId;
            $post_data['shopTransactionId'] = $tranId;
            $post_data['successUrl'] = $projectPath . $this->generateUrl('frontend.payment.success', ['orderId' => $orderId, 'tranId' => $tranId]);
            $post_data['apiKey'] = $this->systemConfigService->get('CommeRokPayPayment.config.apiKey');
            $post_data['shopNumber'] = $this->systemConfigService->get('CommeRokPayPayment.config.shopNumber');
            $post_data['failureUrl'] = $projectPath . $this->generateUrl('frontend.payment.fail', ['orderId' => $orderId, 'tranId' => $tranId]);;

            $rokPay = $this->rokPayNotification;
            $response = $rokPay->makePayment($post_data);

            $responseFormatted = json_decode($response->getBody(),true);

            try{
                return new RedirectResponse($responseFormatted['orderRequest']['paymentUrl']);

            }catch (\Exception $e){
                $context = Context::createDefaultContext();

                $this->transactionStateHandler->fail($tranId,$context);

                throw new AsyncPaymentProcessException(
                    $tranId,
                    \sprintf('An error occurred during the communication with external payment gateway%s%s' , \PHP_EOL , $e->getMessage())
                );
            }

        }catch (\Exception $e){
            $cancelUrl = $this->generateUrl('frontend.payment.cancel', ['orderId' => $orderId, 'tranId' => $tranId]);
            return new RedirectResponse($cancelUrl);
        }

    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $transactionId = $transaction->getOrderTransaction()->getId();
        // Example check if the user cancelled. Might differ for each payment provider
        if ($request->query->getBoolean('cancel')) {

            throw new CustomerCanceledAsyncPaymentException(
                $transactionId,
                'Customer canceled the payment on the RokPay page'
            );
        }
        // Example check for the actual status of the payment. Might differ for each payment provider
        $paymentState = $request->query->getAlpha('status');
        $context = $salesChannelContext->getContext();
        if ($paymentState === 'completed') {
            // Payment completed, set transaction status to "paid"
            $this->transactionStateHandler->paid($transaction->getOrderTransaction()->getId(), $context);
        } else {
            // Payment not completed, set transaction status to "open"
            $this->transactionStateHandler->reopen($transaction->getOrderTransaction()->getId(), $context);
        }
    }

}
