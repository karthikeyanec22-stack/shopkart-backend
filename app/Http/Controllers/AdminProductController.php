<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminProductController extends Controller
{
    // GET /api/admin/products
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images']);

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // Filter Category
        if ($request->filled('category_id') && $request->input('category_id') !== 'all') {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter Status
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') $query->where('status', true);
            if ($status === 'inactive') $query->where('status', false);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Server-side pagination
        $perPage = (int) $request->input('per_page', 20);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    // POST /api/admin/products
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'status' => ['boolean'],
            'featured' => ['boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'], // URLs or base64
            'specifications' => ['nullable', 'array'],
            'variants' => ['nullable', 'array'],
            'size' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'weight' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);
        $validated['status'] = $validated['status'] ?? true;
        $validated['featured'] = $validated['featured'] ?? false;

        $product = Product::create($validated);

        if (!empty($validated['images'])) {
            foreach ($validated['images'] as $imgUrl) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $imgUrl,
                ]);
            }
        }

        $product->load(['category', 'images']);

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product,
        ], 201);
    }

    // GET /api/admin/products/{id}
    public function show($id)
    {
        $product = Product::with(['category', 'images', 'reviews.user'])->findOrFail($id);
        return response()->json(['product' => $product]);
    }

    // PUT /api/admin/products/{id}
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'sku' => ['sometimes', 'string', 'max:100', 'unique:products,sku,' . $id],
            'status' => ['boolean'],
            'featured' => ['boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'specifications' => ['nullable', 'array'],
            'variants' => ['nullable', 'array'],
            'size' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'weight' => ['nullable', 'string'],
        ]);

        if (isset($validated['name']) && $validated['name'] !== $product->name) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);
        }

        $product->update($validated);

        if (isset($validated['images'])) {
            $product->images()->delete();
            foreach ($validated['images'] as $imgUrl) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $imgUrl,
                ]);
            }
        }

        $product->load(['category', 'images']);

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product,
        ]);
    }

    // DELETE /api/admin/products/{id}
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Check if product has historical orders
        $hasOrders = \App\Models\OrderItem::where('product_id', $id)->exists();

        if ($hasOrders) {
            // Soft delete/deactivate to preserve historical orders
            $product->update(['status' => false]);
            $product->delete(); // Soft delete
            return response()->json([
                'message' => 'Product deactivated & soft deleted to preserve historical order records.',
            ]);
        }

        $product->images()->delete();
        $product->forceDelete();

        return response()->json([
            'message' => 'Product deleted permanently.',
        ]);
    }
}
