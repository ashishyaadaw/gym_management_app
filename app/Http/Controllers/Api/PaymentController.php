<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with('user', 'memberPlan.plan');

        if ($request->user()->isMember()) {
            $query->where('user_id', $request->user()->id);
        } else {
            if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
            if ($request->filled('status')) $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate($request->get('per_page', 20)));
    }

    /** Record a manual/cash payment (front desk) or mark an invoice paid. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'member_plan_id' => 'nullable|exists:member_plans,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:cash,upi,card,credit_card,debit_card,bank_transfer,other',
            'notes' => 'nullable|string',
        ]);

        $payment = Payment::create([
            ...$data,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json($payment, 201);
    }

    public function show(Payment $payment)
    {
        return response()->json($payment->load('user', 'memberPlan.plan'));
    }

    public function markPaid(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'method' => 'required|in:cash,upi,card,credit_card,debit_card,bank_transfer,other',
            'transaction_reference' => 'nullable|string',
        ]);

        $payment->update([
            ...$data,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json($payment);
    }

    public function refund(Payment $payment)
    {
        $payment->update(['status' => 'refunded']);

        return response()->json($payment);
    }
}
