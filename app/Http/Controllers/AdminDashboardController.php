<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalCustomers = User::where('role', 'customer')->orWhereNull('role')->count();
        $totalProducts = Product::count();
        $totalOrders = Order::count();
        $totalRevenue = Payment::where('payment_status', 'completed')->sum('amount');

        // Status counts
        $statusCounts = Order::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $lowStockProductsCount = Product::where('stock', '<=', 10)->count();

        // Time-based Sales Summaries
        $now = Carbon::now();

        $todaySales = Payment::where('payment_status', 'completed')
            ->whereDate('created_at', $now->toDateString())
            ->sum('amount');

        $weekSales = Payment::where('payment_status', 'completed')
            ->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
            ->sum('amount');

        $monthSales = Payment::where('payment_status', 'completed')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('amount');

        $yearSales = Payment::where('payment_status', 'completed')
            ->whereYear('created_at', $now->year)
            ->sum('amount');

        // Lists
        $recentOrders = Order::with(['user', 'address', 'payment'])
            ->latest()
            ->limit(5)
            ->get();

        $recentCustomers = User::where('role', 'customer')
            ->orWhereNull('role')
            ->latest()
            ->limit(5)
            ->get();

        $lowStockProducts = Product::where('stock', '<=', 10)
            ->with('category')
            ->orderBy('stock', 'asc')
            ->limit(8)
            ->get();

        $topSellingProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(price * quantity) as total_revenue'))
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->with('product.category', 'product.images')
            ->limit(5)
            ->get();

        return response()->json([
            'metrics' => [
                'total_customers' => $totalCustomers,
                'total_products' => $totalProducts,
                'total_orders' => $totalOrders,
                'total_revenue' => round($totalRevenue, 2),
                'pending_orders' => $statusCounts['pending'] ?? 0,
                'processing_orders' => $statusCounts['processing'] ?? 0,
                'shipped_orders' => $statusCounts['shipped'] ?? 0,
                'delivered_orders' => $statusCounts['delivered'] ?? 0,
                'cancelled_orders' => $statusCounts['cancelled'] ?? 0,
                'low_stock_count' => $lowStockProductsCount,
            ],
            'sales_summaries' => [
                'today' => round($todaySales, 2),
                'this_week' => round($weekSales, 2),
                'this_month' => round($monthSales, 2),
                'this_year' => round($yearSales, 2),
            ],
            'recent_orders' => $recentOrders,
            'recent_customers' => $recentCustomers,
            'low_stock_products' => $lowStockProducts,
            'top_selling_products' => $topSellingProducts,
        ]);
    }
}
