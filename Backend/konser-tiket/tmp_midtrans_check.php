<?php
require 'vendor/autoload.php';
use Midtrans\Config;
use Midtrans\Snap;

echo "=== Temp Midtrans Key Check ===\n";

// From .env or configured key
Config::$serverKey = env('MIDTRANS_SERVER_KEY', 'Mid-server-YOUR_SERVER_KEY');
Config::$isProduction = true; // from MIDTRANS_IS_PRODUCTION=true
Config::$isSanitized = true;
Config::$is3ds = true;

try {
    $params = [
        'transaction_details' => [
            'order_id' => 'CHK-' . time(),
            'gross_amount' => 10000,
        ],
        'customer_details' => [
            'first_name' => 'Check User',
            'email' => 'check@example.com',
        ],
        'item_details' => [
            [
                'id' => 'chk-item',
                'price' => 10000,
                'quantity' => 1,
                'name' => 'Check Item'
            ]
        ]
    ];

    echo "Server Key: " . substr(Config::$serverKey, 0, 25) . "...\n";
    echo "Is Production: " . (Config::$isProduction ? 'true' : 'false') . "\n";

    $token = Snap::getSnapToken($params);
    echo "SUCCESS: Snap token received (" . substr($token,0,20) . "...)\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}

echo "=== Done ===\n";