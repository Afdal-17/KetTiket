<?php
// scripts/discover_midtrans_channels.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Midtrans\Config;
use Midtrans\Snap;

echo "=== Midtrans Channels Discovery ===\n";

$serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY'));
$clientKey = config('services.midtrans.client_key', env('MIDTRANS_CLIENT_KEY'));
$isProd = filter_var(config('services.midtrans.is_production', env('MIDTRANS_IS_PRODUCTION', false)), FILTER_VALIDATE_BOOLEAN);

Config::$serverKey = $serverKey;
Config::$clientKey = $clientKey;
Config::$isProduction = $isProd;
Config::$isSanitized = filter_var(config('services.midtrans.sanitized', env('MIDTRANS_SANITIZED', true)), FILTER_VALIDATE_BOOLEAN);
Config::$is3ds = filter_var(config('services.midtrans.is_3ds', env('MIDTRANS_IS_3DS', true)), FILTER_VALIDATE_BOOLEAN);

echo "Server Key: " . substr(Config::$serverKey, 0, 25) . "...\n";
echo "Client Key: " . (empty(Config::$clientKey) ? '(not set)' : substr(Config::$clientKey,0,20)."...") . "\n";
echo "Is Production: " . (Config::$isProduction ? 'true' : 'false') . "\n\n";

$params = [
    'transaction_details' => [
        'order_id' => 'DISC-' . time(),
        'gross_amount' => 500000,
    ],
    'customer_details' => [
        'first_name' => 'Discovery',
        'email' => 'disc@example.com',
    ],
    'item_details' => [
        [
            'id' => 'discover-item',
            'price' => 500000,
            'quantity' => 1,
            'name' => 'Discovery Item'
        ]
    ],
    // Try to force-show many payment channels
    'enabled_payments' => ['credit_card','gopay','shopeepay','bca_va','bni_va','bri_va','permata_va','echannel','akulaku','indomaret','danamon_online','alfamart']
];

try {
    $response = Snap::createTransaction($params);
    // Convert to array if object
    $respArray = is_array($response) ? $response : json_decode(json_encode($response), true);

    echo "CreateTransaction response:\n";
    print_r($respArray);

    if (!empty($respArray['redirect_url'])) {
        echo "\nAttempting to fetch redirect page HTML (may be dynamic) ...\n";
        $url = $respArray['redirect_url'];
        // Use file_get_contents with context
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: PHP-script\r\nAccept: text/html\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            echo "Failed to fetch redirect HTML (remote requires browser JS).\n";
        } else {
            // show snippet
            $snippet = substr($html, 0, 2000);
            echo "Redirect page HTML snippet (first 2000 chars):\n";
            echo $snippet . "\n";
        }
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    if (method_exists($e, 'getResponseBody')) {
        echo "Response: " . $e->getResponseBody() . "\n";
    }
}

echo "=== Done ===\n";