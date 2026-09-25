<?php

// Set your Sandbox Server Key here or load from environment
$serverKey = getenv('MIDTRANS_SERVER_KEY') ?: 'SB-Mid-server-YOUR_SERVER_KEY';

$payload = json_encode([
    'transaction_details' => [
        'order_id' => 'RAW-TEST-' . time(),
        'gross_amount' => 10000,
    ]
]);

$ch = curl_init('https://app.sandbox.midtrans.com/snap/v1/transactions');

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($serverKey . ':'),
    ],
    CURLOPT_POSTFIELDS => $payload,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP STATUS: {$httpCode}\n";

if ($error) {
    echo "CURL ERROR: {$error}\n";
}

echo "RESPONSE:\n";
echo $response . "\n";