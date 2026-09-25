<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\PaymentController;
use App\Models\Order;
use Illuminate\Http\Request;

echo "=== Midtrans Payment Test ===\n";

// Test 1: Check Midtrans config
echo "\n1. Testing Midtrans Configuration:\n";
$serverKey = config('services.midtrans.server_key');
$clientKey = config('services.midtrans.client_key');

echo "Server Key: " . ($serverKey ? substr($serverKey, 0, 20) . '...' : 'NOT SET') . "\n";
echo "Client Key: " . ($clientKey ? substr($clientKey, 0, 20) . '...' : 'NOT SET') . "\n";
echo "Is Production: " . (config('services.midtrans.is_production') ? 'true' : 'false') . "\n";

// Test 2: Check if we have any orders
echo "\n2. Testing Order Availability:\n";
$orders = Order::with('orderDetails.ticketType')->take(3)->get();
echo "Total orders in DB: " . Order::count() . "\n";

if ($orders->count() > 0) {
    $testOrder = $orders->first();
    echo "Test order ID: " . $testOrder->id . "\n";
    echo "Order status: " . $testOrder->status . "\n";
    echo "Order total: Rp " . number_format($testOrder->total) . "\n";
    
    // Test 3: Try to create Midtrans payment
    echo "\n3. Testing Midtrans Payment Creation:\n";
    try {
        $controller = new PaymentController();
        
        // Create mock request
        $request = Request::create('/orders/' . $testOrder->id . '/pay', 'POST', [
            'gateway' => 'midtrans'
        ]);
        
        // Mock authenticated user
        $user = $testOrder->user;
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        $response = $controller->pay($request, $testOrder->id);
        
        echo "Response Status: " . $response->getStatusCode() . "\n";
        $responseData = json_decode($response->getContent(), true);
        
        if ($response->getStatusCode() === 200) {
            echo "✅ Payment creation successful!\n";
            echo "Transaction ID: " . ($responseData['transaction_id'] ?? 'N/A') . "\n";
            echo "Token: " . (isset($responseData['token']) ? 'Generated' : 'Not generated') . "\n";
        } else {
            echo "❌ Payment creation failed!\n";
            echo "Error: " . ($responseData['message'] ?? 'Unknown error') . "\n";
            if (isset($responseData['error_code'])) {
                echo "Error Code: " . $responseData['error_code'] . "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Exception occurred:\n";
        echo "Message: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        
        // Show first few lines of stack trace
        $trace = explode("\n", $e->getTraceAsString());
        echo "Stack trace (first 5 lines):\n";
        for ($i = 0; $i < min(5, count($trace)); $i++) {
            echo "  " . $trace[$i] . "\n";
        }
    }
    
} else {
    echo "❌ No orders found in database. Please create an order first.\n";
}

echo "\n=== Test Complete ===\n";