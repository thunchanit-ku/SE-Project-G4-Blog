<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        // Get the current shop owner's active shop
        $shopId = \App\Models\Shop::where('shop_owner_id', Auth::id())
                     ->where('status', 'active')
                     ->first()->shop_id ?? null;
        
        if (!$shopId) {
            return redirect()->route('shopowner.shops.my-shop')
                ->with('error', 'คุณต้องมีร้านค้าที่ได้รับการอนุมัติก่อนใช้งานหน้านี้');
        }
        
        // Apply filters
        $status = $request->input('status');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = $request->input('search');
        
        $bookings = Booking::with(['user', 'orderDetails.cartItem.outfit'])
            ->where('shop_id', $shopId);
        
        // Apply status filter
        if ($status) {
            $bookings->where('status', $status);
        }
        
        // Apply date range filter
        if ($startDate && $endDate) {
            $startDateObj = Carbon::parse($startDate)->startOfDay();
            $endDateObj = Carbon::parse($endDate)->endOfDay();
            
            $bookings->whereBetween('purchase_date', [$startDateObj, $endDateObj]);
        } elseif ($startDate) {
            $startDateObj = Carbon::parse($startDate)->startOfDay();
            $bookings->whereDate('purchase_date', '>=', $startDateObj);
        } elseif ($endDate) {
            $endDateObj = Carbon::parse($endDate)->endOfDay();
            $bookings->whereDate('purchase_date', '<=', $endDateObj);
        }
        
        // Apply search filter
        if ($search) {
            $bookings->where(function($query) use ($search) {
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhere('booking_id', 'like', "%{$search}%");
            });
        }
        
        // Order by newest first
        $bookings = $bookings->orderBy('purchase_date', 'desc')->paginate(10);
        
        return view('shopowner.bookings.index', compact('bookings', 'status'));
    }
    
    public function show($id)
    {
        $booking = Booking::with([
            'user', 
            'orderDetails.cartItem.outfit', 
            'shop.address', 
            'promotion',
            'customerAddress.address'  // Add this to eager load the relationship chain
        ])->findOrFail($id);
        
        // Check if this booking belongs to the current shop owner
        $shopId = \App\Models\Shop::where('shop_owner_id', Auth::id())
                     ->where('status', 'active')
                     ->first()->shop_id ?? null;
        
        if ($booking->shop_id != $shopId) {
            return redirect()->route('shopowner.bookings.index')
                ->with('error', 'คุณไม่มีสิทธิ์ดูการจองนี้');
        }
        
        return view('shopowner.bookings.show', compact('booking'));
    }
    
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,partial paid,cancelled,completed',
        ]);
        
        $booking = Booking::findOrFail($id);
        
        // Check if this booking belongs to the current shop owner
        $shopId = \App\Models\Shop::where('shop_owner_id', Auth::id())
                     ->where('status', 'active')
                     ->first()->shop_id ?? null;
        
        if ($booking->shop_id != $shopId) {
            return redirect()->route('shopowner.bookings.index')
                ->with('error', 'คุณไม่มีสิทธิ์แก้ไขการจองนี้');
        }
        
        $booking->status = $request->status;
        $booking->save();
        
        return redirect()->route('shopowner.bookings.show', $booking->booking_id)
            ->with('success', 'อัพเดทสถานะการจองเรียบร้อยแล้ว');
    }
    
    function checkTable(){
        // Check if the Booking table exists
        if (Schema::hasTable((new Booking)->getTable())) {
            echo "Booking table exists!<br>";

            // List of expected columns in the Booking table based on the image you provided
            $requiredColumns = [
                'booking_id', 'purchase_date', 'total_price', 'status', 
                'hasOverrented', 'created_at', 'shop_id', 'promotion_id'
            ];

            $missingColumns = [];

            // Check if each column exists
            foreach ($requiredColumns as $column) {
                if (!Schema::hasColumn((new Booking)->getTable(), $column)) {
                    $missingColumns[] = $column;
                }
            }

            if (empty($missingColumns)) {
                echo "All required columns are present!";
            } else {
                echo "Missing columns: " . implode(', ', $missingColumns);
            }
        } else {
            echo "Booking table does not exist!";
        }
    }


    // show booking for admin
    public function adminBooking(Request $request)
    {
        $query = Booking::with([
            'shop',
            'selectService',
            'orderDetails.cartItem.user'
        ]);
    
        if ($request->filled('search')) {
            $search = $request->search;
    
            $query->where(function ($q) use ($search) {
                // Search จาก booking fields
                $q->where('booking_id', 'like', "%{$search}%");
    
                // Search จากชื่อร้านค้า
                $q->orWhereHas('shop', function ($q2) use ($search) {
                    $q2->where('shop_name', 'like', "%{$search}%");
                });
    
                // Search จากชื่อผู้ใช้ (user → ผ่าน cartItem)
                $q->orWhereHas('orderDetails.cartItem.user', function ($q3) use ($search) {
                    $q3->where('name', 'like', "%{$search}%");
                });
            });
        }
    
        $bookings = $query->orderBy('booking_id', 'desc')->get();
    
        return view('admin.booking.booking', [
            'bookings' => $bookings,
        ]);
    }
    

    // admin show orderdetails
    public function adminOrderDetails($id){
        $booking = Booking::with([
            'shop',
            'promotion',
            'orderDetails.cartItem.user',  // preload แบบซ้อน
        ])->findOrFail($id);
        

        // dd($booking->shop,$booking->promotion);
        // dd($booking,$booking->orderDetails);

        return view('admin.booking.detail', [
            'booking' => $booking,
            'orderdetails' => $booking->orderDetails,
            'shop' => $booking->shop,
            'promotion' => $booking->promotion,
            'user' => $booking->orderDetails[0]->cartItem->user,
        ]);
    }

    public function stats(Request $request)
    {
        // Get the current shop owner's active shop
        $shopId = \App\Models\Shop::where('shop_owner_id', Auth::id())
                        ->where('status', 'active')
                        ->first()->shop_id ?? null;
        
        if (!$shopId) {
            return redirect()->route('shopowner.shops.my-shop')
                ->with('error', 'คุณต้องมีร้านค้าที่ได้รับการอนุมัติก่อนใช้งานหน้านี้');
        }

        // Get period from request, default to 'weekly' (changed from 'daily')
        $period = $request->input('period', 'weekly');
        
        // Get bookings for the shop with confirmed status - eager load orderDetails
        $query = Booking::with('orderDetails')
                        ->where('shop_id', $shopId)
                        ->whereIn('status', ['confirmed', 'partial paid'])
                        ->orderBy('purchase_date', 'desc');
        
        // Apply date filtering based on period
        $today = now();
        $startDate = null;
        $endDate = $today;
        
        if ($period == 'custom') {
            // Handle custom date range
            $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
            $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : Carbon::now()->endOfDay();
            
            // Group by day for custom range
            $groupByFormat = 'd';
            $labelFormat = 'Y-m-d';
        } else {
            switch ($period) {
                case 'weekly':
                    $startDate = $today->copy()->startOfWeek();
                    $groupByFormat = 'w';
                    break;
                case 'monthly':
                    $startDate = $today->copy()->startOfMonth();
                    $groupByFormat = 'd';
                    break;
                case 'yearly':
                    $startDate = $today->copy()->startOfYear();
                    $groupByFormat = 'm';
                    break;
            }
        }
        
        // Filter bookings by date range
        $bookings = $query->whereBetween('purchase_date', [$startDate, $endDate])->get();
        
        // Calculate total earnings from order details instead of booking total_price
        $totalEarnings = $bookings->sum(function($booking) {
            return $booking->orderDetails->sum('total');
        });
        
        // Calculate total completed bookings
        $totalBookings = $bookings->count();
        
        // Prepare earnings data for chart
        $earningsData = [];
        
        if ($period == 'custom') {
            // For custom range, we'll show data by day
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $dateKey = $currentDate->format('Y-m-d');
                $earningsData[$dateKey] = 0;
                $currentDate->addDay();
            }
            
            // Group earnings by day
            foreach ($bookings as $booking) {
                $bookingDate = Carbon::parse($booking->purchase_date)->format('Y-m-d');
                if (isset($earningsData[$bookingDate])) {
                    $earningsData[$bookingDate] += $booking->orderDetails->sum('total');
                }
            }
        } elseif ($period == 'weekly') {
            // Initialize days with 0 earnings (1=Monday to 7=Sunday)
            for ($i = 1; $i <= 7; $i++) {
                $earningsData[$i] = 0;
            }
            
            // Group earnings by day of week
            foreach ($bookings as $booking) {
                $dayOfWeek = date('N', strtotime($booking->purchase_date)); // 1 (for Monday) through 7 (for Sunday)
                $earningsData[$dayOfWeek] += $booking->orderDetails->sum('total'); // Calculate from order details
            }
        } elseif ($period == 'monthly') {
            // Initialize days with 0 earnings
            $daysInMonth = $today->daysInMonth;
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $earningsData[sprintf("%02d", $i)] = 0;
            }
            
            // Group earnings by day of month
            foreach ($bookings as $booking) {
                $dayOfMonth = date('d', strtotime($booking->purchase_date));
                $earningsData[$dayOfMonth] += $booking->orderDetails->sum('total'); // Calculate from order details
            }
        } elseif ($period == 'yearly') {
            // Initialize months with 0 earnings
            for ($i = 1; $i <= 12; $i++) {
                $earningsData[sprintf("%02d", $i)] = 0;
            }
            
            // Group earnings by month
            foreach ($bookings as $booking) {
                $month = date('m', strtotime($booking->purchase_date));
                $earningsData[$month] += $booking->orderDetails->sum('total'); // Calculate from order details
            }
        }
        
        return view('shopowner.stats.income', compact('period', 'totalEarnings', 'totalBookings', 'earningsData'));
    }
}
