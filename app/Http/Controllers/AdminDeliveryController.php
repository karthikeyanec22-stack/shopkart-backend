<?php

namespace App\Http\Controllers;

use App\Models\DeliveryPartner;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;

class AdminDeliveryController extends Controller
{
    // GET /api/admin/delivery-partners
    public function indexPartners(Request $request)
    {
        $query = DeliveryPartner::withCount('deliveries');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $partners = $query->latest()->paginate((int) $request->input('per_page', 20));

        return response()->json($partners);
    }

    // POST /api/admin/delivery-partners
    public function storePartner(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:100', 'unique:delivery_partners,employee_id'],
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'status' => ['string', 'in:active,inactive'],
        ]);

        $partner = DeliveryPartner::create($validated);

        return response()->json([
            'message' => 'Delivery Partner created successfully.',
            'partner' => $partner,
        ], 201);
    }

    // PUT /api/admin/delivery-partners/{id}
    public function updatePartner(Request $request, $id)
    {
        $partner = DeliveryPartner::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:100', 'unique:delivery_partners,employee_id,' . $id],
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'status' => ['string', 'in:active,inactive'],
        ]);

        $partner->update($validated);

        return response()->json([
            'message' => 'Delivery Partner updated successfully.',
            'partner' => $partner,
        ]);
    }

    // GET /api/admin/deliveries
    public function indexDeliveries(Request $request)
    {
        $query = Delivery::with(['order.user', 'order.address', 'deliveryPartner']);

        if ($request->filled('status')) {
            $query->where('delivery_status', $request->input('status'));
        }

        $deliveries = $query->latest()->paginate((int) $request->input('per_page', 20));

        return response()->json($deliveries);
    }

    // POST /api/admin/orders/{id}/assign-delivery
    public function assignDelivery(Request $request, $id)
    {
        $validated = $request->validate([
            'delivery_partner_id' => ['required', 'exists:delivery_partners,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $order = Order::findOrFail($id);
        $partner = DeliveryPartner::findOrFail($validated['delivery_partner_id']);

        $delivery = Delivery::updateOrCreate(
            ['order_id' => $order->id],
            [
                'delivery_partner_id' => $partner->id,
                'assigned_at' => now(),
                'assigned_by' => $request->user()->name ?? 'Admin',
                'delivery_status' => 'assigned',
                'notes' => $validated['notes'] ?? null,
            ]
        );

        $order->update([
            'delivery_status' => 'assigned',
            'status' => 'shipped',
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'shipped',
            'note' => "Assigned to delivery partner '{$partner->name}' (Emp ID: {$partner->employee_id})",
            'changed_by' => $request->user()->name ?? 'Admin',
        ]);

        $delivery->load(['order.user', 'deliveryPartner']);

        return response()->json([
            'message' => 'Delivery partner assigned successfully.',
            'delivery' => $delivery,
        ]);
    }
}
