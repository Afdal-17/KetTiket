<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Organizer;
use App\Models\Order;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Get the Super Admin Dashboard statistics.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin only.'], 403);
        }

        // 1. User stats
        $userStats = User::select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->get();

        // 2. Sales stats
        $totalSales = Order::where('status', 'completed')->sum('total');
        $completedOrdersCount = Order::where('status', 'completed')->count();
        $totalOrdersCount = Order::count();

        // 3. Event stats
        $upcomingEventsCount = Event::where('status', 'published')
            ->where('start_date', '>', now())
            ->count();

        return response()->json([
            'metrics' => [
                'user_stats' => $userStats,
                'total_sales' => (float) $totalSales,
                'completed_orders_count' => $completedOrdersCount,
                'total_orders_count' => $totalOrdersCount,
                'upcoming_events_count' => $upcomingEventsCount,
            ]
        ]);
    }

    /**
     * List all organizers.
     */
    public function organizers(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin only.'], 403);
        }

        $organizers = Organizer::with('user')->get();

        return response()->json([
            'organizers' => $organizers
        ]);
    }

    /**
     * Store/Create a new organizer (Admin only).
     */
    public function storeOrganizer(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin only.'], 403);
        }

        $request->validate([
            'company_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:users,email',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8',
            'verified' => 'boolean',
        ]);

        $organizerUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => 'organizer',
            'status' => true,
        ]);

        $organizer = Organizer::create([
            'user_id' => $organizerUser->id,
            'company_name' => $request->company_name,
            'phone' => $request->phone,
            'verified' => $request->input('verified', true),
        ]);

        return response()->json([
            'message' => 'Organizer created successfully.',
            'organizer' => $organizer->load('user')
        ], 201);
    }

    /**
     * Show detailed organizer info.
     */
    public function showOrganizer(Request $request, string $id)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin only.'], 403);
        }

        $organizer = Organizer::with(['user', 'events'])->find($id);
        if (!$organizer) {
            return response()->json(['message' => 'Organizer not found.'], 404);
        }

        return response()->json([
            'organizer' => $organizer
        ]);
    }

    /**
     * Update an existing organizer (Admin only or Owner).
     */
    public function updateOrganizer(Request $request, string $id)
    {
        $user = $request->user();
        $organizer = Organizer::find($id);

        if (!$organizer) {
            return response()->json(['message' => 'Organizer not found.'], 404);
        }

        if ($user->role !== 'admin' && ($user->role !== 'organizer' || $user->id !== $organizer->user_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'company_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'verified' => 'sometimes|boolean',
        ]);

        if ($request->has('company_name')) {
            $organizer->company_name = $request->company_name;
        }

        if ($request->has('phone')) {
            $organizer->phone = $request->phone;
        }

        if ($user->role === 'admin' && $request->has('verified')) {
            $organizer->verified = $request->verified;
        }

        $organizer->save();

        return response()->json([
            'message' => 'Organizer updated successfully.',
            'organizer' => $organizer->load('user')
        ]);
    }

    /**
     * Delete an organizer (Admin only).
     */
    public function destroyOrganizer(Request $request, string $id)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin only.'], 403);
        }

        $organizer = Organizer::find($id);
        if (!$organizer) {
            return response()->json(['message' => 'Organizer not found.'], 404);
        }

        $organizer->delete();

        return response()->json([
            'message' => 'Organizer deleted successfully.'
        ]);
    }

    /**
     * Verify an organizer.
     */
    public function verifyOrganizer(Request $request, string $id)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin only.'], 403);
        }

        $organizer = Organizer::find($id);
        if (!$organizer) {
            return response()->json(['message' => 'Organizer not found.'], 404);
        }

        $organizer->verified = true;
        $organizer->save();

        return response()->json([
            'message' => "Organizer {$organizer->company_name} verified successfully.",
            'organizer' => $organizer
        ]);
    }

    /**
     * List all users.
     */
    public function users(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin only.'], 403);
        }

        $users = User::with('organizer')->get();

        return response()->json([
            'users' => $users
        ]);
    }

    /**
     * Toggle status (active/deactive) of a user.
     */
    public function toggleUserStatus(Request $request, string $id)
    {
        $currentUser = $request->user();
        if ($currentUser->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin only.'], 403);
        }

        $userToToggle = User::find($id);
        if (!$userToToggle) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($userToToggle->id === $currentUser->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 400);
        }

        $userToToggle->status = !$userToToggle->status;
        $userToToggle->save();

        $statusStr = $userToToggle->status ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "User account has been {$statusStr}.",
            'user' => $userToToggle
        ]);
    }

    /**
     * Toggle user status (active/deactive) or change user role.
     */
    public function changeUserRole(Request $request, string $id)
    {
        $currentUser = $request->user();
        if ($currentUser->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin only.'], 403);
        }

        $request->validate([
            'role' => 'required|string|in:organizer,scanner,admin',
        ]);

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $oldRole = $user->role;
        $user->role = $request->role;
        $user->save();

        // If changed to organizer, create organizer profile if not exists
        if ($request->role === 'organizer' && !$user->organizer) {
            Organizer::create([
                'user_id' => $user->id,
                'company_name' => $user->name . ' Organization',
                'phone' => '-',
                'verified' => true,
            ]);
        }

        return response()->json([
            'message' => "User role changed from {$oldRole} to {$request->role}.",
            'user' => $user->load('organizer')
        ]);
    }

    /**
     * Organizer sales reports.
     */
    public function organizerReport(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'organizer' && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Event::with(['venue']);

        if ($user->role === 'organizer') {
            $query->where('organizer_id', $user->organizer->id);
        }

        $events = $query->get();

        $report = $events->map(function($event) {
            // Completed sales count and total sum
            $salesInfo = Order::where('event_id', $event->id)
                ->where('status', 'completed')
                ->select(
                    DB::raw('count(*) as orders_count'),
                    DB::raw('sum(total) as total_revenue')
                )
                ->first();

            // Total tickets sold
            $ticketsSold = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('orders.event_id', $event->id)
                ->where('orders.status', 'completed')
                ->count();

            // Ticket breakdown per category
            $ticketBreakdown = DB::table('ticket_types')
                ->leftJoin('order_details', 'ticket_types.id', '=', 'order_details.ticket_type_id')
                ->leftJoin('orders', function($join) {
                    $join->on('order_details.order_id', '=', 'orders.id')
                         ->where('orders.status', '=', 'completed');
                })
                ->where('ticket_types.event_id', $event->id)
                ->select(
                    'ticket_types.id',
                    'ticket_types.name',
                    'ticket_types.price',
                    'ticket_types.quota',
                    DB::raw('count(order_details.id) as sold_count'),
                    DB::raw('sum(case when orders.status = "completed" then ticket_types.price else 0 end) as category_revenue')
                )
                ->groupBy('ticket_types.id', 'ticket_types.name', 'ticket_types.price', 'ticket_types.quota')
                ->get();

            // Recent activity for this event
            $recentActivities = DB::table('orders')
                ->join('users', 'orders.user_id', '=', 'users.id')
                ->where('orders.event_id', $event->id)
                ->where('orders.status', 'completed')
                ->orderBy('orders.created_at', 'desc')
                ->limit(10)
                ->select(
                    'orders.id as order_id',
                    'users.name as customer_name',
                    'users.email as customer_email',
                    'orders.total',
                    'payments.gateway',
                    'orders.created_at'
                )
                ->join('payments', 'orders.id', '=', 'payments.order_id')
                ->get();

            return [
                'event_id' => $event->id,
                'title' => $event->title,
                'start_date' => $event->start_date,
                'status' => $event->status,
                'venue' => $event->venue ? $event->venue->name : '-',
                'orders_count' => (int) ($salesInfo->orders_count ?? 0),
                'total_revenue' => (float) ($salesInfo->total_revenue ?? 0),
                'tickets_sold' => (int) $ticketsSold,
                'ticket_breakdown' => $ticketBreakdown,
                'recent_activities' => $recentActivities
            ];
        });

        // Summary metrics for organizer
        $totalRevAll = $report->sum('total_revenue');
        $totalOrdersAll = $report->sum('orders_count');
        $totalTicketsAll = $report->sum('tickets_sold');

        return response()->json([
            'summary' => [
                'total_revenue' => $totalRevAll,
                'total_orders' => $totalOrdersAll,
                'total_tickets_sold' => $totalTicketsAll,
                'total_events' => $report->count(),
            ],
            'report' => $report
        ]);
    }
}
