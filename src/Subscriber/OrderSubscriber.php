<?php


namespace MultiPackageShipping\Subscriber;

use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Order\Event\OrderPlacedEvent;

class OrderSubscriber implements EventSubscriberInterface
{
    private $orderRepository;

    public function __construct(EntityRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_PLACED_EVENT => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(OrderPlacedEvent $event)
    {
        $order = $event->getOrder();
        $lineItems = $order->getLineItems();
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

        // Speichere die Paketinformationen (dies kann später für den Versanddienstleister genutzt werden)
        foreach ($packages as $index => $package) {
            // Speichere das Paket z. B. in einer Bestell-Extension oder logge es
            error_log("Paket " . ($index + 1) . " mit " . count($package) . " Artikeln.");
        }
    }
}
