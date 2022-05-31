<?php

namespace CommeRokPayPayment\Components\Controller\Storefront;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RokPayController extends StorefrontController
{
    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;



    /**
     * @param StateMachineRegistry $stateMachineRegistry
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param EntityRepositoryInterface $orderRepository
     */
    public function __construct(StateMachineRegistry $stateMachineRegistry, OrderTransactionStateHandler $transactionStateHandler, EntityRepositoryInterface $orderRepository)
    {
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->orderRepository = $orderRepository;
    }


    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/payment/success", name="frontend.payment.success",defaults={"csrf_protected"=false}, options={"seo"="false"}, methods={"GET"})
     * @throws \Doctrine\DBAL\Exception
     */
    public function success(Request $request)
    {
        $tran_id = $request->get('tranId');
        $order_id = $request->get('orderId');

        $context = Context::createDefaultContext();

        $orderCriteria = new Criteria();
        $orderCriteria->addFilter(new EqualsFilter('id', $order_id));

        /** @var $order OrderEntity */
        $order = $this->orderRepository->search($orderCriteria, $context);

        $status = "";
        $deepLinkCode = "";
        foreach ($order->getEntities()->getElements() as $e){
            $deepLinkCode = $e->getDeepLinkCode();
            $status = $e->getStateMachineState()->getTechnicalName();
        }

        if ($status == 'open') {
            /*
            That means IPN did not work or IPN URL was not set in your merchant panel. Here you need to update order status
            in order table as Processing or Complete.
            Here you can also sent sms or email for successfull transaction to customer
            */


            $this->transactionStateHandler->process($tran_id, $context);

            $this->stateMachineRegistry->transition(new Transition(
                OrderDefinition::ENTITY_NAME,
                $order_id,
                'process',
                'stateId'
            ), $context);

            $request->getSession()->getFlashBag()->add('success','Your order is successfully created and payment is successful!');
            $successUrl = getenv('APP_URL') . '/checkout/finish?orderId=' . $order_id . '&tranId=' . $tran_id;

            return new RedirectResponse($successUrl);
        }else{

            $successUrl = getenv('APP_URL') . '/account/order/' . $deepLinkCode;
            return new RedirectResponse($successUrl);


        }
    }


    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/payment/cancel", name="frontend.payment.cancel",defaults={"csrf_protected"=false}, options={"seo"="false"}, methods={"GET"})
     */
    public function cancel(Request $request)
    {
        $tran_id = $request->get('tranId');

        $context = Context::createDefaultContext();

        $this->transactionStateHandler->cancel($tran_id,$context);

        $request->getSession()->getFlashBag()->add('danger', 'Payment canceled!.');

        $cancelUrl = getenv('APP_URL') . '/account/order/';

        return new RedirectResponse($cancelUrl);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/payment/fail", name="frontend.payment.fail",defaults={"csrf_protected"=false}, options={"seo"="false"}, methods={"GET"})
     */
    public function fail(Request $request)
    {
        $tran_id = $request->get('tranId');

        $context = Context::createDefaultContext();

        $this->transactionStateHandler->fail($tran_id,$context);

        $request->getSession()->getFlashBag()->add('danger', 'Payment failed!.');

        $response = $this->redirectToRoute('frontend.account.order.page');
        $failUrl = getenv('APP_URL') . '/account/order/';

        return new RedirectResponse($failUrl);

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



}
