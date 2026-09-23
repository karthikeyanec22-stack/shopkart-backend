<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCustomerController extends Controller
{
    // GET /api/admin/customers
    public function index(Request $request)
    {
        $query = User::where(function ($q) {
            $q->where('role', 'customer')->orWhereNull('role');
        })
        ->withCount('orders')
        ->withSum(['orders as total_spent' => function ($q) {
            $q->whereIn('status', ['confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered']);
        }], 'total_amount');

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Status Filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $customers = $query->latest()->paginate((int) $request->input('per_page', 20));

        return response()->json($customers);
    }

    // GET /api/admin/customers/{id}
    public function show($id)
    {
        $customer = User::where('id', $id)
            ->with([
                'addresses',
                'orders.items.product.images',
                'orders.payment',
                'orders.delivery.deliveryPartner',
                'reviews.product',
                'wishlists.product',
            ])
            ->withCount('orders')
            ->withSum(['orders as total_spent' => function ($q) {
                $q->whereIn('status', ['confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered']);
            }], 'total_amount')
            ->firstOrFail();

        return response()->json([
            'customer' => $customer,
        ]);
    }

    // PUT /api/admin/customers/{id}/status
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,blocked'],
        ]);

        $customer = User::findOrFail($id);
        $customer->update(['status' => $validated['status']]);

        return response()->json([
            'message' => "Customer status updated to {$validated['status']}.",
            'customer' => $customer,
        ]);
    }
}
