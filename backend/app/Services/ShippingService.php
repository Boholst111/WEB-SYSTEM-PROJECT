<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ShippingService
{
    protected array $courierConfig;

    public function __construct()
    {
        $this->courierConfig = config('shipping.couriers', $this->getDefaultCourierConfig());
    }

    /**
     * Get default courier configuration if config file is not available.
     */
    private function getDefaultCourierConfig(): array
    {
        return [
            'lbc' => [
                'enabled' => true,
                'name' => 'LBC Express',
                'rates' => ['standard' => 150.0, 'express' => 250.0],
                'weight_rate' => 20.0,
                'services' => [
                    'standard' => ['name' => 'LBC Standard', 'estimated_days' => 3],
                    'express' => ['name' => 'LBC Express', 'estimated_days' => 1]
                ]
            ],
            'jnt' => [
                'enabled' => true,
                'name' => 'J&T Express',
                'rates' => ['standard' => 120.0, 'express' => 200.0],
                'weight_rate' => 15.0,
                'services' => [
                    'standard' => ['name' => 'J&T Standard', 'estimated_days' => 3],
                    'express' => ['name' => 'J&T Express', 'estimated_days' => 2]
                ]
            ],
            'ninjavan' => [
                'enabled' => true,
                'name' => 'Ninja Van',
                'rates' => ['standard' => 130.0, 'express' => 220.0],
                'weight_rate' => 18.0,
                'services' => [
                    'standard' => ['name' => 'Ninja Van Standard', 'estimated_days' => 3],
                    'express' => ['name' => 'Ninja Van Express', 'estimated_days' => 2]
                ]
            ],
            '2go' => [
                'enabled' => true,
                'name' => '2GO Express',
                'rates' => ['standard' => 140.0, 'express' => 240.0],
                'weight_rate' => 22.0,
                'services' => [
                    'standard' => ['name' => '2GO Standard', 'estimated_days' => 4],
                    'express' => ['name' => '2GO Express', 'estimated_days' => 2]
                ]
            ]
        ];
    }

    /**
     * Generate bulk shipping labels for multiple orders.
     */
    public function generateBulkLabels(array $orderIds, string $courierService, string $serviceType = 'standard'): array
    {
        $orders = Order::whereIn('id', $orderIds)
            ->with(['user', 'items.product'])
            ->get();

        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $result = $this->generateShippingLabel($order, $courierService, $serviceType);
                
                if ($result['success']) {
                    $successful++;
                    
                    // Update order with tracking information
                    $order->update([
                        'tracking_number' => $result['tracking_number'],
                        'courier_service' => $courierService,
                        'status' => Order::STATUS_PROCESSING
                    ]);
                } else {
                    $failed++;
                }

                $results[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'success' => $result['success'],
                    'tracking_number' => $result['tracking_number'] ?? null,
                    'label_url' => $result['label_url'] ?? null,
                    'message' => $result['message']
                ];

            } catch (\Exception $e) {
                $failed++;
                $results[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return [
            'summary' => [
                'total' => count($orders),
                'successful' => $successful,
                'failed' => $failed
            ],
            'results' => $results
        ];
    }

    /**
     * Generate shipping label for a single order.
     */
    public function generateShippingLabel(Order $order, string $courierService, string $serviceType = 'standard'): array
    {
        try {
            $courierConfig = $this->getCourierConfig($courierService);
            
            if (!$courierConfig) {
                return [
                    'success' => false,
                    'message' => "Unsupported courier service: {$courierService}"
                ];
            }

            $shipmentData = $this->prepareShipmentData($order, $serviceType);
            
            $response = match ($courierService) {
                'lbc' => $this->createLBCShipment($shipmentData, $courierConfig),
                'jnt' => $this->createJNTShipment($shipmentData, $courierConfig),
                'ninjavan' => $this->createNinjaVanShipment($shipmentData, $courierConfig),
                '2go' => $this->create2GoShipment($shipmentData, $courierConfig),
                default => ['success' => false, 'message' => 'Courier not implemented']
            };

            if ($response['success']) {
                Log::info('Shipping label generated', [
                    'order_id' => $order->id,
                    'courier' => $courierService,
                    'tracking_number' => $response['tracking_number']
                ]);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Shipping label generation failed', [
                'order_id' => $order->id,
                'courier' => $courierService,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate shipping label: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get tracking information for a shipment.
     */
    public function getTrackingInfo(string $trackingNumber, string $courierService = null): ?array
    {
        if (!$courierService) {
            // Try to detect courier from tracking number format
            $courierService = $this->detectCourierFromTrackingNumber($trackingNumber);
        }

        if (!$courierService) {
            return null;
        }

        try {
            $courierConfig = $this->getCourierConfig($courierService);
            
            if (!$courierConfig) {
                return null;
            }

            $trackingInfo = match ($courierService) {
                'lbc' => $this->getLBCTracking($trackingNumber, $courierConfig),
                'jnt' => $this->getJNTTracking($trackingNumber, $courierConfig),
                'ninjavan' => $this->getNinjaVanTracking($trackingNumber, $courierConfig),
                '2go' => $this->get2GoTracking($trackingNumber, $courierConfig),
                default => null
            };

            return $trackingInfo;

        } catch (\Exception $e) {
            Log::error('Tracking info retrieval failed', [
                'tracking_number' => $trackingNumber,
                'courier' => $courierService,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Create shipment record and update order.
     */
    public function createShipment(Order $order, array $options): array
    {
        $courierService = $options['courier_service'] ?? 'lbc';
        $serviceType = $options['service_type'] ?? 'standard';

        $result = $this->generateShippingLabel($order, $courierService, $serviceType);

        if ($result['success']) {
            // Update order status to shipped
            $order->update([
                'status' => Order::STATUS_SHIPPED,
                'shipped_at' => now(),
                'tracking_number' => $result['tracking_number'],
                'courier_service' => $courierService
            ]);
        }

        return $result;
    }

    /**
     * Calculate shipping cost for an order.
     */
    public function calculateShippingCost(Order $order, string $courierService, string $serviceType = 'standard'): float
    {
        $courierConfig = $this->getCourierConfig($courierService);
        
        if (!$courierConfig) {
            return 0.0;
        }

        // Calculate based on weight, dimensions, and destination
        $weight = $this->calculateOrderWeight($order);
        $dimensions = $this->calculateOrderDimensions($order);
        $destination = $order->shipping_address;

        // Base rate calculation (simplified)
        $baseRate = $courierConfig['rates'][$serviceType] ?? 150.0;
        
        // Weight-based pricing
        $weightRate = max(0, ($weight - 1) * ($courierConfig['weight_rate'] ?? 20));
        
        // Distance-based pricing (simplified - would use actual distance calculation)
        $distanceRate = $this->calculateDistanceRate($destination, $courierConfig);

        return $baseRate + $weightRate + $distanceRate;
    }

    /**
     * Get available shipping options for an order.
     */
    public function getShippingOptions(Order $order): array
    {
        $options = [];

        foreach ($this->courierConfig as $courier => $config) {
            if (!$config['enabled']) {
                continue;
            }

            foreach ($config['services'] as $service => $serviceConfig) {
                $cost = $this->calculateShippingCost($order, $courier, $service);
                $estimatedDays = $serviceConfig['estimated_days'] ?? 3;

                $options[] = [
                    'courier' => $courier,
                    'service' => $service,
                    'name' => $serviceConfig['name'],
                    'cost' => $cost,
                    'estimated_delivery_days' => $estimatedDays,
                    'estimated_delivery_date' => now()->addDays($estimatedDays)->format('Y-m-d'),
                    'description' => $serviceConfig['description'] ?? ''
                ];
            }
        }

        // Sort by cost
        usort($options, fn($a, $b) => $a['cost'] <=> $b['cost']);

        return $options;
    }

    /**
     * Prepare shipment data for courier APIs.
     */
    private function prepareShipmentData(Order $order, string $serviceType): array
    {
        $shippingAddress = $order->shipping_address;
        
        return [
            'order_number' => $order->order_number,
            'service_type' => $serviceType,
            'sender' => [
                'name' => config('app.name'),
                'company' => config('app.name'),
                'address' => config('shipping.sender_address'),
                'phone' => config('shipping.sender_phone'),
                'email' => config('shipping.sender_email')
            ],
            'recipient' => [
                'name' => $shippingAddress['first_name'] . ' ' . $shippingAddress['last_name'],
                'company' => $shippingAddress['company'] ?? '',
                'address' => $this->formatAddress($shippingAddress),
                'phone' => $shippingAddress['phone'],
                'email' => $order->user->email
            ],
            'package' => [
                'weight' => $this->calculateOrderWeight($order),
                'dimensions' => $this->calculateOrderDimensions($order),
                'value' => $order->total_amount,
                'description' => $this->getPackageDescription($order)
            ],
            'special_instructions' => $order->notes
        ];
    }

    /**
     * LBC API integration.
     */
    private function createLBCShipment(array $shipmentData, array $config): array
    {
        // Mock implementation - replace with actual LBC API calls
        $trackingNumber = 'LBC' . now()->format('YmdHis') . rand(1000, 9999);
        
        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'label_url' => "https://lbc.com.ph/labels/{$trackingNumber}.pdf",
            'message' => 'LBC shipment created successfully'
        ];
    }

    /**
     * J&T Express API integration.
     */
    private function createJNTShipment(array $shipmentData, array $config): array
    {
        // Mock implementation - replace with actual J&T API calls
        $trackingNumber = 'JT' . now()->format('YmdHis') . rand(1000, 9999);
        
        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'label_url' => "https://jtexpress.ph/labels/{$trackingNumber}.pdf",
            'message' => 'J&T shipment created successfully'
        ];
    }

    /**
     * Ninja Van API integration.
     */
    private function createNinjaVanShipment(array $shipmentData, array $config): array
    {
        // Mock implementation - replace with actual Ninja Van API calls
        $trackingNumber = 'NV' . now()->format('YmdHis') . rand(1000, 9999);
        
        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'label_url' => "https://ninjavan.co/labels/{$trackingNumber}.pdf",
            'message' => 'Ninja Van shipment created successfully'
        ];
    }

    /**
     * 2GO Express API integration.
     */
    private function create2GoShipment(array $shipmentData, array $config): array
    {
        // Mock implementation - replace with actual 2GO API calls
        $trackingNumber = '2GO' . now()->format('YmdHis') . rand(1000, 9999);
        
        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'label_url' => "https://2go.com.ph/labels/{$trackingNumber}.pdf",
            'message' => '2GO shipment created successfully'
        ];
    }

    /**
     * Get LBC tracking information.
     */
    private function getLBCTracking(string $trackingNumber, array $config): ?array
    {
        // Mock tracking data - replace with actual API calls
        return [
            'tracking_number' => $trackingNumber,
            'status' => 'in_transit',
            'status_description' => 'Package is in transit',
            'estimated_delivery' => now()->addDays(2)->format('Y-m-d'),
            'events' => [
                [
                    'timestamp' => now()->subDays(1)->toISOString(),
                    'status' => 'picked_up',
                    'description' => 'Package picked up from sender',
                    'location' => 'Manila Hub'
                ],
                [
                    'timestamp' => now()->toISOString(),
                    'status' => 'in_transit',
                    'description' => 'Package in transit to destination',
                    'location' => 'Cebu Hub'
                ]
            ]
        ];
    }

    /**
     * Get J&T tracking information.
     */
    private function getJNTTracking(string $trackingNumber, array $config): ?array
    {
        // Mock tracking data - replace with actual API calls
        return [
            'tracking_number' => $trackingNumber,
            'status' => 'out_for_delivery',
            'status_description' => 'Out for delivery',
            'estimated_delivery' => now()->format('Y-m-d'),
            'events' => [
                [
                    'timestamp' => now()->subDays(2)->toISOString(),
                    'status' => 'picked_up',
                    'description' => 'Package picked up',
                    'location' => 'Origin Hub'
                ],
                [
                    'timestamp' => now()->subDays(1)->toISOString(),
                    'status' => 'in_transit',
                    'description' => 'Package in transit',
                    'location' => 'Destination Hub'
                ],
                [
                    'timestamp' => now()->toISOString(),
                    'status' => 'out_for_delivery',
                    'description' => 'Out for delivery',
                    'location' => 'Local Facility'
                ]
            ]
        ];
    }

    /**
     * Get Ninja Van tracking information.
     */
    private function getNinjaVanTracking(string $trackingNumber, array $config): ?array
    {
        // Mock tracking data - replace with actual API calls
        return [
            'tracking_number' => $trackingNumber,
            'status' => 'delivered',
            'status_description' => 'Package delivered',
            'delivered_at' => now()->subHours(2)->toISOString(),
            'events' => [
                [
                    'timestamp' => now()->subDays(3)->toISOString(),
                    'status' => 'picked_up',
                    'description' => 'Package picked up',
                    'location' => 'Pickup Point'
                ],
                [
                    'timestamp' => now()->subDays(1)->toISOString(),
                    'status' => 'out_for_delivery',
                    'description' => 'Out for delivery',
                    'location' => 'Delivery Hub'
                ],
                [
                    'timestamp' => now()->subHours(2)->toISOString(),
                    'status' => 'delivered',
                    'description' => 'Package delivered successfully',
                    'location' => 'Customer Address'
                ]
            ]
        ];
    }

    /**
     * Get 2GO tracking information.
     */
    private function get2GoTracking(string $trackingNumber, array $config): ?array
    {
        // Mock tracking data - replace with actual API calls
        return [
            'tracking_number' => $trackingNumber,
            'status' => 'processing',
            'status_description' => 'Package being processed',
            'estimated_delivery' => now()->addDays(3)->format('Y-m-d'),
            'events' => [
                [
                    'timestamp' => now()->subHours(4)->toISOString(),
                    'status' => 'received',
                    'description' => 'Package received at facility',
                    'location' => '2GO Branch'
                ]
            ]
        ];
    }

    /**
     * Helper methods.
     */
    private function getCourierConfig(string $courier): ?array
    {
        return $this->courierConfig[$courier] ?? null;
    }

    private function detectCourierFromTrackingNumber(string $trackingNumber): ?string
    {
        if (str_starts_with($trackingNumber, 'LBC')) return 'lbc';
        if (str_starts_with($trackingNumber, 'JT')) return 'jnt';
        if (str_starts_with($trackingNumber, 'NV')) return 'ninjavan';
        if (str_starts_with($trackingNumber, '2GO')) return '2go';
        
        return null;
    }

    private function calculateOrderWeight(Order $order): float
    {
        $totalWeight = 0;
        
        // Load items with products to avoid lazy loading
        $order->load('items.product');
        
        foreach ($order->items as $item) {
            $productWeight = $item->product->weight ?? 0.5; // Default 0.5kg
            $totalWeight += $productWeight * $item->quantity;
        }
        
        return max(1.0, $totalWeight); // Minimum 1kg
    }

    private function calculateOrderDimensions(Order $order): array
    {
        // Simplified dimension calculation
        $itemCount = $order->items->sum('quantity');
        
        return [
            'length' => min(50, $itemCount * 10), // Max 50cm
            'width' => min(40, $itemCount * 8),   // Max 40cm
            'height' => min(30, $itemCount * 6),  // Max 30cm
        ];
    }

    private function formatAddress(array $address): string
    {
        return implode(', ', array_filter([
            $address['address_line_1'],
            $address['address_line_2'] ?? null,
            $address['city'],
            $address['province'],
            $address['postal_code']
        ]));
    }

    private function getPackageDescription(Order $order): string
    {
        $itemCount = $order->items->sum('quantity');
        return "Diecast models and collectibles ({$itemCount} items)";
    }

    private function calculateDistanceRate(array $destination, array $config): float
    {
        // Simplified distance-based rate calculation
        // In a real implementation, this would use actual distance calculation
        $province = $destination['province'] ?? '';
        
        $rates = [
            'Metro Manila' => 0,
            'Luzon' => 50,
            'Visayas' => 100,
            'Mindanao' => 150
        ];

        // Simple province-to-region mapping (simplified)
        if (str_contains(strtolower($province), 'manila') || 
            str_contains(strtolower($province), 'quezon') ||
            str_contains(strtolower($province), 'rizal')) {
            return $rates['Metro Manila'];
        }

        return $rates['Luzon']; // Default to Luzon rate
    }
}