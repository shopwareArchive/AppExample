<?php declare(strict_types=1);

namespace App\Controller;

use App\Services\OrderListService;
use App\SwagAppsystem\Client;
use App\SwagAppsystem\Event;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OrderController extends AbstractController
{
    /**
     * @var OrderListService
     */
    private $orderListService;

    public function __construct(OrderListService $orderListService)
    {
        $this->orderListService = $orderListService;
    }

    /**
     * @Route("/hooks/order/placed", name="hooks.order.placed", methods={"POST"})
     * Generates an order list with an deep link in order to print it.
     */
    public function orderPlacedEvent(Client $client, Event $event): Response
    {
        $eventData = $event->getEventData();
        $order = $eventData['payload']['order'] ?? null;

        // Should not happen, but return if there is no order.
        if (!$order) {
            return new Response();
        }

        $orderId = $order['id'];
        $url = $this->generateUrl('orderList', [], UrlGeneratorInterface::ABSOLUTE_URL);

        //Gets the configuration including the data for the order list.
        $orderListConfiguration = $this->orderListService->getOrderListConfigurationFromOrder($order);

        //Gets the deep link to the order list.
        $orderListLink = $this->orderListService->generateOrderListLink($event, $orderId, $url);

        //Gets the order list table as plain html.
        $orderListTable = $this->renderView('Order/order-list-table.html.twig', ['orderListConfiguration' => $orderListConfiguration]);

        //Updates the order with the order list table and the deep link to the order.
        $this->orderListService->updateOrder($client, $orderId, ['customFields' => ['order-list' => $orderListTable, 'order-list-link' => $orderListLink]]);

        return new Response();
    }

    /**
     * @Route("/iframe/orderlist", name="iframe.orderList", methods={"GET"})
     * Generates an order list out of all open orders in the admin.
     */
    public function iframeOrderList(Client $client): Response
    {
        //Gets the data for the order list.
        $orderListConfiguration = $this->orderListService->getOrderListConfigurationForAllOpenOrders($client);

        //Outputs the order list to the user.
        return $this->render('Order/order-list.html.twig', ['orderListConfiguration' => $orderListConfiguration]);
    }

    /**
     * @Route("/orderlist", name="orderList", methods={"GET"})
     * Generates an order list out of the given order.
     */
    public function orderList(Request $request, Client $client): Response
    {
        $requestQuery = $request->query;
        $shopId = $requestQuery->get('shop-id');
        $orderId = $requestQuery->get('order-id');
        $orderSignature = $requestQuery->get('order-signature');

        //Authenticates the request.
        if (!$this->orderListService->authenticateOrderListLink($shopId, $orderId, $orderSignature)) {
            return new Response(null, 401);
        }

        $orderId = $requestQuery->get('order-id');

        //Gets the order list data
        $orderListConfiguration = $this->orderListService->getOrderListConfigurationFromOrderId($client, $orderId);

        //Outputs the order list to the user.
        return $this->render('Order/order-list.html.twig', ['orderListConfiguration' => $orderListConfiguration]);
    }

    /**
     * @Route("/actionbutton/add/orderlist", name="actionButton.add.orderList")
     * Adds or update an order list with an deep link to an existing order.
     */
    public function addOrderListToExistingOrder(Client $client, Event $event): Response
    {
        $eventData = $event->getEventData();
        $orderId = $eventData['ids'][0];

        //Gets the order list data.
        $orderListConfiguration = $this->orderListService->getOrderListConfigurationFromOrderId($client, $orderId);
        $url = $this->generateUrl('orderList', [], UrlGeneratorInterface::ABSOLUTE_URL);

        //Gets the order list table as plain html.
        $orderListTable = $this->renderView('Order/order-list-table.html.twig', ['orderListConfiguration' => $orderListConfiguration]);

        //Generates an deep link to the order.
        $orderListLink = $this->orderListService->generateOrderListLink($event, $orderId, $url);

        //Updates the existing order with the order list and the deep link to the order.
        $this->orderListService->updateOrder($client, $orderId, ['customFields' => ['order-list' => $orderListTable, 'order-list-link' => $orderListLink]]);

        return new Response();
    }
}
