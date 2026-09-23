<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Category;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminReportController extends Controller
{
    // GET /api/admin/reports
    public function index(Request $request)
    {
        $period = $request->input('period', 'this_month');
        $startDate = null;
        $endDate = Carbon::now()->endOfDay();

        switch ($period) {
            case 'today':
                $startDate = Carbon::today();
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday();
                $endDate = Carbon::yesterday()->endOfDay();
                break;
            case 'this_week':
                $startDate = Carbon::now()->startOfWeek();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                break;
            case 'this_year':
                $startDate = Carbon::now()->startOfYear();
                break;
            case 'custom':
                if ($request->filled('start_date')) {
                    $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
                }
                if ($request->filled('end_date')) {
                    $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
                }
                break;
            default:
                $startDate = Carbon::now()->startOfMonth();
                break;
        }

        $orderQuery = Order::whereBetween('created_at', [$startDate, $endDate]);
        $paymentQuery = Payment::where('payment_status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalSales = (float) $paymentQuery->sum('amount');
        $totalOrders = $orderQuery->count();
        $avgOrderValue = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0;

        // Top Selling Products in Period
        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as units_sold'), DB::raw('SUM(price * quantity) as revenue'))
            ->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('product_id')
            ->orderByDesc('units_sold')
            ->with('product.category')
            ->limit(5)
            ->get();

        // Top Categories
        $topCategories = OrderItem::select('products.category_id', DB::raw('SUM(order_items.quantity) as units_sold'), DB::raw('SUM(order_items.price * order_items.quantity) as revenue'))
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('products.category_id')
            ->orderByDesc('revenue')
            ->with('product.category')
            ->limit(5)
            ->get();

        // Top Customers in Period
        $topCustomers = User::select('users.id', 'users.name', 'users.email', DB::raw('COUNT(orders.id) as order_count'), DB::raw('SUM(orders.total_amount) as total_spent'))
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        return response()->json([
            'period' => $period,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
            'summary' => [
                'total_sales' => round($totalSales, 2),
                'total_orders' => $totalOrders,
                'average_order_value' => $avgOrderValue,
            ],
            'top_products' => $topProducts,
            'top_categories' => $topCategories,
            'top_customers' => $topCustomers,
        ]);
    }
}
