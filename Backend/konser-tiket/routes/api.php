<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CustomerAiController;
use App\Http\Controllers\OrganizerAiController;
use App\Http\Controllers\AdminAiController;
use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- Public Routes ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/social', [AuthController::class, 'socialLogin']);
Route::post('/login/social/exchange', [AuthController::class, 'exchangeSocialLogin']);
Route::post('/login/phone/start', [AuthController::class, 'startPhoneLogin']);
Route::post('/login/phone/verify', [AuthController::class, 'verifyPhoneLogin']);
Route::post('/webhook/payment', [PaymentController::class, 'webhook']);
Route::post('/webhook/midtrans', [PaymentController::class, 'webhook']);

// Bot Assistant KetTiket — mesin rule-based dari data website sendiri (publik, anti-spam).
Route::post('/chatbot', [ChatbotController::class, 'ask'])->middleware('throttle:30,1');

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

Route::get('/venues', [VenueController::class, 'index']);
Route::get('/venues/{id}/seats', [VenueController::class, 'getSeats']);


// --- Protected Routes ---
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Event Management (Organizer / Admin)
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
    Route::post('/events/{id}/ticket-types', [EventController::class, 'addTicketTypes']);

    // Venue Management (Admin / Organizer)
    Route::post('/venues', [VenueController::class, 'store']);
    Route::post('/venues/{id}/seats', [VenueController::class, 'generateSeats']);

    // Booking & Orders (Customer)
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);

    // Payment Simulation (Customer)
    Route::post('/orders/{id}/pay', [PaymentController::class, 'pay']);
    Route::post('/orders/{id}/confirm-payment', [PaymentController::class, 'confirmPaymentSuccess']);

    // Tickets & E-tickets (Customer & Scanner)
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/scans/history', [TicketController::class, 'scanHistory']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::post('/tickets/{id}/present', [TicketController::class, 'present']);
    Route::post('/tickets/scan', [TicketController::class, 'scan']); // Scanner only policy enforced in controller

    // Reports (Organizer & Admin)
    Route::get('/organizer/reports', [AdminController::class, 'organizerReport']);

    // AI Customer Assistant Routes
    Route::get('/ai/conversations', [CustomerAiController::class, 'indexConversations']);
    Route::post('/ai/conversations', [CustomerAiController::class, 'storeConversation']);
    Route::get('/ai/conversations/{id}', [CustomerAiController::class, 'showConversation']);
    Route::delete('/ai/conversations/{id}', [CustomerAiController::class, 'destroyConversation']);
    Route::post('/ai/chat', [CustomerAiController::class, 'chat']);
    Route::get('/ai/recommendations', [CustomerAiController::class, 'recommendations']);
    Route::post('/ai/messages/{id}/feedback', [CustomerAiController::class, 'submitFeedback']);

    // AI Organizer Assistant Routes
    Route::post('/organizer/ai/generate-description', [OrganizerAiController::class, 'generateDescription']);
    Route::post('/organizer/ai/analyze-sales', [OrganizerAiController::class, 'analyzeSales']);
    Route::post('/organizer/ai/analyze-trends', [OrganizerAiController::class, 'analyzeTrends']);
    Route::post('/organizer/ai/ticket-recommendation', [OrganizerAiController::class, 'ticketRecommendation']);
    Route::post('/organizer/ai/generate-report', [OrganizerAiController::class, 'generateReport']);

    // Organizer Event Management (CRUD)
    Route::get('/organizer/events', [EventController::class, 'index']); // Organizer's own events
    Route::get('/organizer/events/{id}', [EventController::class, 'show']);
    Route::put('/organizer/events/{id}', [EventController::class, 'update']);
    Route::delete('/organizer/events/{id}', [EventController::class, 'destroy']);

    // Super Admin Management
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/organizers', [AdminController::class, 'organizers']);
        Route::post('/organizers', [AdminController::class, 'storeOrganizer']);
        Route::match(['post', 'put', 'patch'], '/organizers/{id}/verify', [AdminController::class, 'verifyOrganizer']);
        Route::get('/organizers/{id}', [AdminController::class, 'showOrganizer']);
        Route::put('/organizers/{id}', [AdminController::class, 'updateOrganizer']);
        Route::delete('/organizers/{id}', [AdminController::class, 'destroyOrganizer']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{id}/status', [AdminController::class, 'toggleUserStatus']);
        Route::put('/users/{id}/role', [AdminController::class, 'changeUserRole']);

        // AI Admin Assistant Routes
        Route::post('/ai/analyze-platform', [AdminAiController::class, 'analyzePlatform']);
        Route::post('/ai/detect-suspicious', [AdminAiController::class, 'detectSuspicious']);
    });
});
