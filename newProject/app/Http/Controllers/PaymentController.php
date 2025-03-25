<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\OrderDetail;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function showPaymentForm($booking_id, $cycle)
    {
        $booking = Booking::findOrFail($booking_id);

        $orderDetails = OrderDetail::where('booking_id', $booking_id)
            ->where('booking_cycle', $cycle)
            ->get();

        $total = $orderDetails->sum('total');

        return view('payment.form', compact('booking', 'cycle', 'orderDetails', 'total'));
    }

    public function processPayment(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,booking_id',
            'booking_cycle' => 'required|in:1,2',
            'payment_method' => 'required|string',
        ]);

        $booking_id = $request->booking_id;
        $cycle = $request->booking_cycle;

        // รวมยอดจาก OrderDetail ตามรอบ
        $total = OrderDetail::where('booking_id', $booking_id)
            ->where('booking_cycle', $cycle)
            ->sum('total');

        // ตรวจสอบซ้ำว่าเคยจ่ายรอบนี้แล้วหรือยัง
        $existing = Payment::where('booking_id', $booking_id)
            ->where('booking_cycle', $cycle)
            ->first();

        if ($existing) {
            return back()->withErrors(['error' => 'ชำระเงินรอบนี้ไปแล้ว']);
        }

        Payment::create([
            'payment_method' => $request->payment_method,
            'total' => $total,
            'status' => 'paid',
            'booking_cycle' => $cycle,
            'booking_id' => $booking_id,
        ]);

        return redirect()->route('cartItem.allItem')->with('success', 'ชำระเงินสำเร็จแล้ว');
    }

    public function index()
    {
        $bookings = Booking::with(['payments', 'orderDetails', 'selectService'])
            ->whereBelongsTo(auth()->user())
            ->where('status', 'confirmed') // ✅ เพิ่มเงื่อนไขตรงนี้
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($bookings as $booking) {
            $paid = $booking->payments->sum('total');

            // ✅ คิดเฉพาะค่าชุดทั้งหมด (เฉพาะที่สถานะ booking = confirmed แล้ว)
            $total = $booking->orderDetails->sum('total');

            $booking->total_price = $total;
            $booking->paid = $paid;
            $booking->unpaid = $total - $paid;
            $booking->total_with_staff = $total;
        }

        return view('payment.index', compact('bookings'));
    }


    public function viewUpdate($booking_id)
{
    $booking = Booking::with(['payments'])->where('booking_id', $booking_id)->firstOrFail();

    // ✅ ต้องใช้ ->filter() หรือ ->where() บน Collection
    $payments = $booking->payments->where('status', 'unpaid');

    return view('payment.viewUpdate', compact('payments', 'booking'));
}

public function updateMethod(Request $request, $id)
{
    $request->validate([
        'payment_method' => 'required|in:credit_card,paypal',
    ]);

    $payment = Payment::findOrFail($id);

    $payment->payment_method = $request->payment_method;
    $payment->status = 'paid'; // หากต้องการให้ระบบจ่ายทันที
    $payment->save();

    return redirect()->route('payment.index')->with('success', 'บันทึกการชำระเงินเรียบร้อยแล้ว');
}







}
