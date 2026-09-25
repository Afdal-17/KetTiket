<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class PaymentController extends Controller
{
    /**
     * Verify the incoming webhook signature.
     */
    protected function validateWebhookSignature(Request $request): bool
    {
        $legacySignature = $request->header('X-Payment-Signature')
            ?? $request->header('x-payment-signature')
            ?? $request->header('X-Signature')
            ?? $request->header('x-signature');

        $legacyTransactionId = (string) $request->input('transaction_id', '');
        $legacyStatus = (string) $request->input('status', '');
        $legacySecret = config('services.payment.webhook_secret', env('PAYMENT_WEBHOOK_SECRET', 'kettiket-local-secret'));

        if (!empty($legacySignature) && !empty($legacyTransactionId) && !empty($legacyStatus)) {
            $expected = hash_hmac('sha256', $legacyTransactionId . ':' . $legacyStatus, $legacySecret);
            if (hash_equals($expected, $legacySignature)) {
                return true;
            }
        }

        $midtransSignature = $request->header('X-Midtrans-Signature')
            ?? $request->input('signature_key');

        $orderId = (string) ($request->input('order_id') ?? $request->input('transaction_id') ?? '');
        $statusCode = (string) ($request->input('status_code') ?? $request->input('status') ?? '');
        $grossAmount = (string) ($request->input('gross_amount') ?? '0');
        $serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY'));

        if (empty($midtransSignature) || empty($orderId) || empty($statusCode) || empty($serverKey)) {
            return false;
        }

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        return hash_equals($expected, $midtransSignature);
    }

    /**
     * Initiate payment (Simulates getting Payment URL from Gateway)
     */
    public function pay(Request $request, string $orderId)
    {
        $order = Order::with('orderDetails.ticketType')->find($orderId);

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'This order cannot be paid. Status is already: ' . $order->status], 400);
        }

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized. You can only pay your own order.'], 403);
        }

        $request->validate([
            'gateway' => 'required|string|max:50',
        ]);

        $gateway = strtolower(trim($request->input('gateway')));

        if ($gateway === 'midtrans') {
            $serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY'));
            $clientKey = config('services.midtrans.client_key', env('MIDTRANS_CLIENT_KEY'));

            // Check if using demo/placeholder credentials
            $isDemoMode = empty($serverKey) || 
                         $serverKey === 'SB-Mid-server-REPLACE_WITH_YOUR_SANDBOX_KEY' ||
                         str_contains($serverKey, 'DEMO') ||
                         str_contains($serverKey, 'PLACEHOLDER');

            if ($isDemoMode) {
                Log::info('Using demo payment mode - no real Midtrans transaction');
                
                // Simulate successful payment for demo
                $demoTransactionId = 'DEMO-' . strtoupper(Str::random(10)) . '-' . time();
                
                Payment::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'gateway' => 'midtrans_demo',
                        'transaction_id' => $demoTransactionId,
                        'status' => 'pending',
                    ]
                );

                // Return demo token for frontend
                return response()->json([
                    'message' => 'Demo payment initiated. This is a simulation for testing purposes.',
                    'transaction_id' => $demoTransactionId,
                    'token' => 'demo_token_' . base64_encode($demoTransactionId),
                    'demo_mode' => true,
                    'redirect_url' => null,
                    'gateway' => 'midtrans_demo',
                    'instructions' => 'Untuk production, silakan dapatkan credentials Midtrans asli dari dashboard.midtrans.com'
                ]);
            }

            if (empty($serverKey) || empty($clientKey)) {
                Log::error('Midtrans credentials not configured', [
                    'server_key_set' => !empty($serverKey),
                    'client_key_set' => !empty($clientKey)
                ]);
                return response()->json([
                    'message' => 'Payment gateway tidak tersedia. Silakan hubungi administrator.',
                    'error_code' => 'MIDTRANS_CONFIG_ERROR'
                ], 500);
            }

            // Validate credentials format
            if (!str_starts_with($serverKey, 'SB-Mid-server-') && !str_starts_with($serverKey, 'Mid-server-')) {
                Log::warning('Invalid Midtrans server key format');
                return response()->json([
                    'message' => 'Konfigurasi payment gateway tidak valid.',
                    'error_code' => 'INVALID_SERVER_KEY'
                ], 500);
            }

            Config::$serverKey = $serverKey;
            Config::$clientKey = $clientKey;
            Config::$isProduction = filter_var(config('services.midtrans.is_production', env('MIDTRANS_IS_PRODUCTION', false)), FILTER_VALIDATE_BOOLEAN);
            Config::$isSanitized = filter_var(config('services.midtrans.sanitized', env('MIDTRANS_SANITIZED', true)), FILTER_VALIDATE_BOOLEAN);
            Config::$is3ds = filter_var(config('services.midtrans.is_3ds', env('MIDTRANS_IS_3DS', true)), FILTER_VALIDATE_BOOLEAN);

            $midtransOrderId = 'KETTIKET-' . $order->id . '-' . strtoupper(Str::random(10));
            $payment = Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'gateway' => 'midtrans',
                    'transaction_id' => $midtransOrderId,
                    'status' => 'pending',
                ]
            );

            $itemDetails = [];
            foreach ($order->orderDetails as $detail) {
                $itemDetails[] = [
                    'id' => (string) $detail->id,
                    'price' => (int) round((float) $detail->price),
                    'quantity' => 1,
                    'name' => $detail->ticketType->name ?? 'Tiket Konser',
                ];
            }

            $params = [
                'transaction_details' => [
                    'order_id' => $midtransOrderId,
                    'gross_amount' => (int) round((float) $order->total),
                ],
                'item_details' => $itemDetails,
                'customer_details' => [
                    'first_name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
                'callbacks' => [
                    'finish' => url('/customer/dashboard'),
                ],
            ];

            try {
                $response = Snap::createTransaction($params);

                // Midtrans PHP SDK may return an object (stdClass) or array depending on version.
                $respArray = is_array($response) ? $response : json_decode(json_encode($response), true);

                Log::info('Midtrans transaction created successfully', [
                    'order_id' => $midtransOrderId,
                    'snap_token' => substr($respArray['token'] ?? 'N/A', 0, 20) . '...'
                ]);

                return response()->json([
                    'message' => 'Payment initiated successfully via Midtrans.',
                    'transaction_id' => $midtransOrderId,
                    'token' => $respArray['token'] ?? null,
                    'redirect_url' => $respArray['redirect_url'] ?? null,
                    'gateway' => 'midtrans',
                ]);
            } catch (\Exception $e) {
                Log::error('Midtrans transaction creation failed', [
                    'order_id' => $midtransOrderId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'message' => 'Gagal memproses pembayaran. Silakan coba lagi.',
                    'error_code' => 'MIDTRANS_API_ERROR'
                ], 500);
            }
        }

        $transactionId = 'TX-' . strtoupper(Str::random(10));

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => $gateway,
            'transaction_id' => $transactionId,
            'status' => 'pending',
        ]);

        $paymentUrl = "https://checkout.sandbox.paymentgateway.com/{$transactionId}";

        return response()->json([
            'message' => 'Payment initiated successfully.',
            'payment_url' => $paymentUrl,
            'transaction_id' => $transactionId,
            'gateway' => $gateway,
        ]);
    }

    /**
     * Webhook to receive asynchronous payment notification from Midtrans
     */
    public function webhook(Request $request)
    {
        Log::info('Payment webhook received', [
            'headers' => $request->headers->all(),
            'payload' => $request->all()
        ]);

        $request->validate([
            'status' => 'sometimes|required|string',
            'transaction_status' => 'sometimes|required|string',
            'transaction_id' => 'sometimes|string',
            'order_id' => 'sometimes|string',
        ]);

        if (!$this->validateWebhookSignature($request)) {
            Log::warning('Invalid webhook signature', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return response()->json(['message' => 'Invalid webhook signature.'], 403);
        }

        $transactionId = $request->input('transaction_id') ?? $request->input('order_id');
        $status = $request->input('transaction_status') ?? $request->input('status');

        if (empty($transactionId) || empty($status)) {
            return response()->json(['message' => 'Missing payment status payload.'], 400);
        }

        // Map Midtrans status to our internal status
        $internalStatus = $status;
        if (in_array($status, ['capture', 'settlement'])) {
            $internalStatus = 'success';
        } elseif (in_array($status, ['deny', 'cancel', 'expire', 'failure'])) {
            $internalStatus = 'failed';
        } elseif (in_array($status, ['pending', 'authorize'])) {
            $internalStatus = 'pending';
        }

        try {
            DB::beginTransaction();

            $payment = Payment::where('transaction_id', $transactionId)->lockForUpdate()->first();

            if (!$payment) {
                Log::warning('Payment transaction not found', ['transaction_id' => $transactionId]);
                DB::rollBack();
                return response()->json(['message' => 'Transaction not found.'], 404);
            }

            if ($payment->status !== 'pending') {
                Log::info('Webhook already processed', [
                    'transaction_id' => $transactionId,
                    'current_status' => $payment->status
                ]);
                DB::rollBack();
                return response()->json(['message' => 'Webhook already processed.'], 200);
            }

            $order = Order::with('orderDetails')->lockForUpdate()->find($payment->order_id);

            if (!$order) {
                Log::warning('Order not found for payment', ['order_id' => $payment->order_id]);
                DB::rollBack();
                return response()->json(['message' => 'Order not found.'], 404);
            }

            $payment->status = $internalStatus;
            $payment->save();

            Log::info('Payment status updated', [
                'transaction_id' => $transactionId,
                'old_status' => 'pending',
                'new_status' => $internalStatus,
                'order_id' => $order->id
            ]);

            if ($internalStatus === 'success') {
                $order->status = 'completed';
                $order->save();

                $ticketsCreated = [];
                foreach ($order->orderDetails as $detail) {
                    $barcode = 'BAR-' . strtoupper(Str::random(12));
                    $qrCode = 'QR-' . strtoupper(Str::random(16));

                    while (Ticket::where('barcode', $barcode)->exists()) {
                        $barcode = 'BAR-' . strtoupper(Str::random(12));
                    }
                    while (Ticket::where('qr_code', $qrCode)->exists()) {
                        $qrCode = 'QR-' . strtoupper(Str::random(16));
                    }

                    $ticket = Ticket::create([
                        'order_detail_id' => $detail->id,
                        'barcode' => $barcode,
                        'qr_code' => $qrCode,
                        'status' => 'active',
                    ]);

                    $ticketsCreated[] = $ticket->id;
                }

                Log::info('Tickets generated successfully', [
                    'order_id' => $order->id,
                    'tickets_created' => count($ticketsCreated),
                    'ticket_ids' => $ticketsCreated
                ]);
            } else {
                $order->status = 'failed';
                $order->save();

                Log::warning('Payment failed, order marked as failed', [
                    'order_id' => $order->id,
                    'payment_status' => $internalStatus
                ]);

                // Restore the ticket quota since payment failed
                foreach ($order->orderDetails as $detail) {
                    $ticketType = TicketType::lockForUpdate()->find($detail->ticket_type_id);
                    $ticketType->quota += 1;
                    $ticketType->save();
                }
            }

            DB::commit();
            return response()->json(['message' => 'Webhook processed successfully'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Webhook processing failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Confirm payment success from Snap callback (for localhost/development)
     * Called by frontend's snap.onSuccess callback
     */
    public function confirmPaymentSuccess(Request $request, string $orderId)
    {
        try {
            DB::beginTransaction();

            $order = Order::with('orderDetails')->lockForUpdate()->find($orderId);

            if (!$order) {
                return response()->json(['message' => 'Order not found.'], 404);
            }

            if ($order->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->first();

            if (!$payment) {
                return response()->json(['message' => 'Payment record not found.'], 404);
            }

            // Update payment status to success
            $payment->status = 'success';
            $payment->save();

            Log::info('Payment confirmed from Snap callback', [
                'order_id' => $order->id,
                'transaction_id' => $payment->transaction_id
            ]);

            // Update order status
            $order->status = 'completed';
            $order->save();

            // Generate tickets for all order details
            $ticketsCreated = [];
            foreach ($order->orderDetails as $detail) {
                $barcode = 'BAR-' . strtoupper(Str::random(12));
                $qrCode = 'QR-' . strtoupper(Str::random(16));

                // Ensure unique barcode
                while (Ticket::where('barcode', $barcode)->exists()) {
                    $barcode = 'BAR-' . strtoupper(Str::random(12));
                }

                // Ensure unique QR code
                while (Ticket::where('qr_code', $qrCode)->exists()) {
                    $qrCode = 'QR-' . strtoupper(Str::random(16));
                }

                $ticket = Ticket::create([
                    'order_detail_id' => $detail->id,
                    'barcode' => $barcode,
                    'qr_code' => $qrCode,
                    'status' => 'active',
                ]);

                $ticketsCreated[] = $ticket->id;
            }

            Log::info('Tickets generated from Snap callback', [
                'order_id' => $order->id,
                'tickets_created' => count($ticketsCreated),
                'ticket_ids' => $ticketsCreated
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment confirmed and tickets generated successfully.',
                'order_id' => $order->id,
                'tickets_created' => count($ticketsCreated),
                'tickets' => $ticketsCreated
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error confirming payment from Snap callback', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Error confirming payment: ' . $e->getMessage()], 500);
        }
    }
}
