<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    // GET /api/admin/inventory
    public function index(Request $request)
    {
        $query = Product::with('category');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('low_stock') && $request->input('low_stock') === 'true') {
            $query->where('stock', '<=', 10);
        }

        $inventory = $query->orderBy('stock', 'asc')->paginate((int) $request->input('per_page', 20));

        return response()->json($inventory);
    }

    // POST /api/admin/inventory/adjust
    public function adjustStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'adjustment_type' => ['required', 'string', 'in:addition,reduction,adjustment'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        $quantity = $validated['quantity'];
        if ($validated['adjustment_type'] === 'reduction') {
            $quantity = -$quantity;
        }

        $newStock = max(0, $product->stock + $quantity);
        $product->update(['stock' => $newStock]);

        InventoryTransaction::create([
            'product_id' => $product->id,
            'type' => $validated['adjustment_type'],
            'quantity' => $quantity,
            'note' => $validated['note'] ?? "Stock adjusted manually by Admin",
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Stock adjusted successfully.',
            'product' => $product,
        ]);
    }

    // GET /api/admin/inventory/history
    public function history(Request $request)
    {
        $query = InventoryTransaction::with(['product', 'user']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        $history = $query->latest()->paginate((int) $request->input('per_page', 20));

        return response()->json($history);
    }
}
