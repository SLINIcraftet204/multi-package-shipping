<?php

namespace MultiPackageShipping\Subscriber;

use Pickware\PickwareDhl\Api\DhlAdapter;
use Pickware\PickwareDhl\Api\Shipment;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Psr\Log\LoggerInterface;
use MultiPackageShipping\Service\ConfigService;
use Shopware\Core\Checkout\Order\Event\CheckoutOrderPlacedEvent;

class OrderSubscriber implements EventSubscriberInterface
{
    private EntityRepository $orderRepository;
    private DhlAdapter $dhlAdapter;
    private LoggerInterface $logger;
    private ConfigService $configService;

    public function __construct(
        ConfigService $configService,
        EntityRepository $orderRepository,
        DhlAdapter $dhlAdapter,
        LoggerInterface $logger
    ) {
        $this->configService = $configService;
        $this->orderRepository = $orderRepository;
        $this->dhlAdapter = $dhlAdapter;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $context = $event->getContext();
        $orderId = $event->getOrderId();

        $order = $this->orderRepository->search(
            (new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$orderId])),
            $context
        )->first();

        if (!$order) {
            $this->logger->error("Bestellung nicht gefunden: " . $orderId);
            return;
        }

        $this->logger->info("Erstelle DHL-Labels für Bestellung: " . $order->getOrderNumber());

        // Pakete berechnen
        $packages = $this->splitOrderIntoPackages($order);
        foreach ($packages as $index => $packageWeight) {
            try {
                $shipmentData = [
                    'recipient' => [
                        'name' => $order->getBillingAddress()->getFirstName() . ' ' . $order->getBillingAddress()->getLastName(),
                        'street' => $order->getBillingAddress()->getStreet(),
                        'zipcode' => $order->getBillingAddress()->getZipcode(),
                        'city' => $order->getBillingAddress()->getCity(),
                        'country' => $order->getBillingAddress()->getCountry()->getIso(),
                    ],
                    'weight' => $packageWeight,
                ];

                $this->dhlAdapter->createShipment(new Shipment($shipmentData));

                $this->logger->info("DHL-Label für Paket " . ($index + 1) . " erstellt.");
            } catch (\Exception $e) {
                $this->logger->error("Fehler beim Erstellen des DHL-Labels: " . $e->getMessage());
            }
        }
    }

    private function splitOrderIntoPackages($order): array
    {
        $maxWeight = $this->configService->getMaxPackageWeight();
        $currentWeight = 0;
        $packages = [];
        $lineItems = $order->getLineItems();

        foreach ($lineItems as $item) {
            $payload = $item->getPayload();
            $weight = isset($payload['weight']) ? (float) $payload['weight'] : 0;
            $quantity = $item->getQuantity();

            for ($i = 0; $i < $quantity; $i++) {
                if ($currentWeight + $weight > $maxWeight) {
                    $packages[] = $currentWeight;
                    $currentWeight = 0;
                }
                $currentWeight += $weight;
            }
        }

        if ($currentWeight > 0) {
            $packages[] = $currentWeight;
        }

        return $packages;
    }
}
