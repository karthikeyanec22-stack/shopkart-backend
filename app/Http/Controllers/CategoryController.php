<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // GET /api/categories
    // Get all categories
    public function index()
    {
        $categories = Category::latest()->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    // POST /api/categories
    // Create category
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
        ]);

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category,
        ], 201);
    }

    // GET /api/categories/{category}
    // Get one category
    public function show(Category $category)
    {
        $category->load('products');

        return response()->json([
            'category' => $category,
        ]);
    }

    // PUT/PATCH /api/categories/{category}
    // Update category
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'unique:categories,slug,' . $category->id,
            ],
            'description' => ['nullable', 'string'],
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category->fresh(),
        ]);
    }

    // DELETE /api/categories/{category}
    // Delete category
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}