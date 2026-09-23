<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    // GET /api/admin/payments
    public function index(Request $request)
    {
        $query = Payment::with(['order.user']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                  ->orWhere('payment_method', 'like', "%{$search}%")
                  ->orWhere('order_id', $search)
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('order_number', 'like', "%{$search}%")
                         ->orWhereHas('user', function ($uq) use ($search) {
                             $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                         });
                  });
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('payment_status', $request->input('status'));
        }

        $payments = $query->latest()->paginate((int) $request->input('per_page', 20));

        return response()->json($payments);
    }
}
