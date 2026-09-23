<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

class AdminCouponController extends Controller
{
    // GET /api/admin/coupons
    public function index(Request $request)
    {
        $query = Coupon::withCount('usages');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('code', 'like', "%{$search}%");
        }

        $coupons = $query->latest()->paginate((int) $request->input('per_page', 20));

        return response()->json($coupons);
    }

    // POST /api/admin/coupons
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'discount_type' => ['required', 'string', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'active' => ['boolean'],
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['active'] = $validated['active'] ?? true;

        $coupon = Coupon::create($validated);

        return response()->json([
            'message' => 'Coupon created successfully.',
            'coupon' => $coupon,
        ], 201);
    }

    // PUT /api/admin/coupons/{id}
    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', 'unique:coupons,code,' . $id],
            'discount_type' => ['sometimes', 'string', 'in:percentage,fixed'],
            'discount_value' => ['sometimes', 'numeric', 'min:0'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'active' => ['boolean'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $coupon->update($validated);

        return response()->json([
            'message' => 'Coupon updated successfully.',
            'coupon' => $coupon,
        ]);
    }

    // DELETE /api/admin/coupons/{id}
    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);

        if ($coupon->usages()->count() > 0) {
            $coupon->update(['active' => false]);
            return response()->json([
                'message' => 'Coupon deactivated to preserve historical usage logs.',
            ]);
        }

        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully.',
        ]);
    }

    // Customer Public API: POST /api/coupons/validate
    public function validateCoupon(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'order_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $coupon = Coupon::where('code', strtoupper($validated['code']))
            ->where('active', true)
            ->first();

        if (!$coupon) {
            return response()->json(['message' => 'Invalid or expired coupon code.'], 404);
        }

        if ($validated['order_amount'] < $coupon->minimum_order_amount) {
            return response()->json([
                'message' => "Minimum order amount for this coupon is ₹{$coupon->minimum_order_amount}.",
            ], 422);
        }

        $discount = 0;
        if ($coupon->discount_type === 'percentage') {
            $discount = ($validated['order_amount'] * $coupon->discount_value) / 100;
            if ($coupon->maximum_discount && $discount > $coupon->maximum_discount) {
                $discount = $coupon->maximum_discount;
            }
        } else {
            $discount = min($coupon->discount_value, $validated['order_amount']);
        }

        return response()->json([
            'message' => 'Coupon applied successfully!',
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => $coupon->discount_value,
                'discount_calculated' => round($discount, 2),
            ],
        ]);
    }
}
