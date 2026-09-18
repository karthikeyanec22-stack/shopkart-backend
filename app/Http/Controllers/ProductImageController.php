<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        $path = $request->file('image')->store('products', 'public');

        $productImage = $product->images()->create([
            'image' => $path,
        ]);

        return response()->json([
            'message' => 'Product image uploaded successfully',
            'image' => $productImage,
        ], 201);
    }


    public function destroy(Product $product, ProductImage $image)
{
    if ($image->product_id !== $product->id) {
        return response()->json([
            'message' => 'Image does not belong to this product',
        ], 404);
    }

    Storage::disk('public')->delete($image->image);

    $image->delete();

    return response()->json([
        'message' => 'Product image deleted successfully',
    ]);
}
}