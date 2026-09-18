<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // GET /api/products
    // Get all products
    public function index()
    {
        $products = Product::with(['category', 'images'])
            ->latest()
            ->get();

        return response()->json([
            'products' => $products,
        ]);
    }

    // POST /api/products
    // Create a new product
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'status' => ['sometimes', 'boolean'],
        ]);

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product,
        ], 201);
    }

    // GET /api/products/{product}
    // Get one product
    public function show(Product $product)
    {
        $product->load('category', 'images');

        return response()->json([
            'product' => $product,
        ]);
    }

    // PUT/PATCH /api/products/{product}
    // Update a product
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'unique:products,slug,' . $product->id,
            ],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'sku' => [
                'sometimes',
                'string',
                'max:255',
                'unique:products,sku,' . $product->id,
            ],
            'status' => ['sometimes', 'boolean'],
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->fresh(),
        ]);
    }

    // DELETE /api/products/{product}
    // Delete a product
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}