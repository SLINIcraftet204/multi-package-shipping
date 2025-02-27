<?php

namespace MultiPackageShipping\Subscriber;

use Pickware\Shipping\Shipment\Events\ShipmentsCreatedEvent;
use Pickware\Shipping\Shipment\ShipmentService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShipmentSubscriber implements EventSubscriberInterface
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
            ShipmentsCreatedEvent::class => 'onShipmentCreated',
        ];
    }

    public function onShipmentCreated(ShipmentsCreatedEvent $event)
    {
        $context = Context::createDefaultContext();
        $shipments = $event->getShipments();

        foreach ($shipments as $shipment) {
            $orderId = $shipment->getOrderId();
            $order = $this->orderRepository->search(
                (new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$orderId])),
                $context
            )->first();

            if (!$order) {
                $this->logger->error("Bestellung nicht gefunden für Shipment: " . $orderId);
                continue;
            }

            $this->logger->info("Verarbeitung von Shipment für Bestellung: " . $order->getOrderNumber());

            // Hier werden die Pakete berechnet
            $packages = $this->splitOrderIntoPackages($order);
            foreach ($packages as $index => $packageWeight) {
                try {
                    $shipment = $this->shipmentService->createShipment([
                        'orderId' => $orderId,
                        'carrier' => 'dhl',
                        'weight' => $packageWeight,
                        'context' => $context,
                    ]);

                    $this->logger->info("Paket " . ($index + 1) . " erfolgreich an DHL übergeben.");
                } catch (\Exception $e) {
                    $this->logger->error("Fehler beim Erstellen des DHL Versands: " . $e->getMessage());
                }
            }
        }
    }

    private function splitOrderIntoPackages($order)
    {
        $maxWeight = 31.5;
        $currentWeight = 0;
        $packages = [];
        $lineItems = $order->getLineItems();

        foreach ($lineItems as $item) {
            $weight = $item->getPayload()['weight'] ?? 0;
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
