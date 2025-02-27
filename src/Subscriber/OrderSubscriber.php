<?php

namespace MultiPackageShipping\Subscriber;

use Pickware\Shipping\Dhl\DhlAdapter;
use Pickware\Shipping\Shipment\ShipmentService;
use Shopware\Core\Checkout\Order\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class OrderSubscriber implements EventSubscriberInterface
{
    private EntityRepository $orderRepository;
    private ShipmentService $shipmentService;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $orderRepository,
        ShipmentService $shipmentService,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->shipmentService = $shipmentService;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event)
    {
        $order = $event->getOrder();
        $lineItems = $order->getLineItems();
        $context = Context::createDefaultContext();
        $maxWeight = 31.5;
        $currentPackageWeight = 0;
        $packages = [];
        $currentPackage = [];

        foreach ($lineItems as $item) {
            $weight = $item->getPayload()['weight'] ?? 0;
            $quantity = $item->getQuantity();

            for ($i = 0; $i < $quantity; $i++) {
                if ($currentPackageWeight + $weight > $maxWeight) {
                    $packages[] = $currentPackage;
                    $currentPackage = [];
                    $currentPackageWeight = 0;
                }

                $currentPackage[] = $item;
                $currentPackageWeight += $weight;
            }
        }

        if (!empty($currentPackage)) {
            $packages[] = $currentPackage;
        }

        $this->logger->info("Bestellung {$order->getOrderNumber()} wird in " . count($packages) . " Pakete aufgeteilt.");

        foreach ($packages as $index => $package) {
            $packageWeight = array_sum(array_map(fn($item) => $item->getPayload()['weight'] ?? 0, $package));

            // Pickware Versand über DHL
            try {
                $shipment = $this->shipmentService->createShipment(
                    [
                        'orderId' => $order->getId(),
                        'carrier' => DhlAdapter::CARRIER_NAME,
                        'weight' => $packageWeight,
                        'context' => $context,
                    ]
                );

                $this->logger->info("Paket " . ($index + 1) . " erfolgreich an DHL übergeben: " . json_encode($shipment));
            } catch (\Exception $e) {
                $this->logger->error("Fehler beim Erstellen des DHL Versands: " . $e->getMessage());
            }
        }
    }
}
