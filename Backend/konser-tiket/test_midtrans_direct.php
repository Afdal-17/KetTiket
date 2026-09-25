<?php

// Load composer and bootstrap Laravel app to read .env/config
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Midtrans\Config;
use Midtrans\Snap;

echo "=== Direct Midtrans API Test (using .env) ===\n";

// Read keys from config/services.php or .env
$serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY'));
$clientKey = config('services.midtrans.client_key', env('MIDTRANS_CLIENT_KEY'));
$isProd = filter_var(config('services.midtrans.is_production', env('MIDTRANS_IS_PRODUCTION', false)), FILTER_VALIDATE_BOOLEAN);

if (empty($serverKey)) {
    echo "No MIDTRANS_SERVER_KEY configured. Check .env.\n";
    exit(1);
}

Config::$serverKey = $serverKey;
Config::$clientKey = $clientKey;
Config::$isProduction = $isProd;
Config::$isSanitized = filter_var(config('services.midtrans.sanitized', env('MIDTRANS_SANITIZED', true)), FILTER_VALIDATE_BOOLEAN);
Config::$is3ds = filter_var(config('services.midtrans.is_3ds', env('MIDTRANS_IS_3DS', true)), FILTER_VALIDATE_BOOLEAN);

echo "Server Key: " . substr(Config::$serverKey, 0, 25) . "...\n";
echo "Is Production: " . (Config::$isProduction ? 'true' : 'false') . "\n";

try {
    echo "\nTesting Midtrans Snap API...\n";

    $params = [
        'transaction_details' => [
            'order_id' => 'TEST-' . time(),
            'gross_amount' => 10000,
        ],
        'customer_details' => [
            'first_name' => 'Test User',
            'email' => 'test@example.com',
        ],
        'item_details' => [
            [
                'id' => 'test-item',
                'price' => 10000,
                'quantity' => 1,
                'name' => 'Test Item'
            ]
        ]
    ];

    $snapToken = Snap::getSnapToken($params);
    echo "✅ SUCCESS!\n";
    echo "Snap Token: " . substr($snapToken, 0, 30) . "...\n";
    echo "\nMidtrans API is working correctly with current .env keys!\n";

} catch (Exception $e) {
    echo "❌ ERROR!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";

    if (method_exists($e, 'getResponseBody')) {
        echo "Response: " . $e->getResponseBody() . "\n";
    }
}

echo "\n=== Test Complete ===\n";