<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ThaiOutfit;
use App\Models\CartItem;
use App\Models\User;
use App\Models\ThaiOutfitSizeAndColor;

class CartItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    public function index()
{
    $user = Auth::user();

    // ดึงข้อมูลสินค้าทั้งหมดในตะกร้าของ User นั้นๆ
    $cartItems = CartItem::with(['outfit', 'size', 'color'])
                        ->where('userId', $user->user_id)
                        ->where('status', 'INUSE')
                        ->orderBy('outfit_id')
                        ->get();

    // ดึงข้อมูล stock_quantity ของแต่ละชุดที่เลือกในตะกร้า
    foreach ($cartItems as $cartItem) {
        $cartItem->sizeAndColor = ThaiOutfitSizeAndColor::where('outfit_id', $cartItem->outfit_id)
            ->where('size_id', $cartItem->size_id)
            ->where('color_id', $cartItem->color_id)
            ->first();

        // ✅ ตรวจสอบว่าค่าถูกต้องหรือไม่
        if (!$cartItem->sizeAndColor) {
            $cartItem->sizeAndColor = (object) ['amount' => 0]; // กำหนดค่าเริ่มต้นให้เป็น 0
        }
    }

    return view('cartItem.index', compact('cartItems'));
}




    

    


    public function addToCart(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'กรุณาเข้าสู่ระบบก่อนเพิ่มลงตะกร้า');
        }

        
    
        $user = Auth::user();
        $outfit_id = $request->input('outfit_id');
        $size_id = $request->input('size_id'); // รับค่าขนาดจากฟอร์ม
        $color_id = $request->input('color_id'); // รับค่าสีจากฟอร์ม
        $quantity = (int) $request->input('quantity', 1);
    
        // ตรวจสอบว่ามีสินค้านี้ (outfit_id, size_id, color_id) อยู่ในตะกร้าแล้วหรือไม่
        $item = CartItem::where('outfit_id', $outfit_id)
                        ->where('size_id', $size_id)
                        ->where('color_id', $color_id)
                        ->where('userId', $user->user_id)
                        ->where('status', 'INUSE')
                        ->first();
    
        if ($item) {
            // อัปเดตจำนวนสินค้าถ้ามีอยู่แล้ว
            $item->quantity += $quantity;
            $item->save();
        } else {
            // เพิ่มสินค้าใหม่ถ้ายังไม่มีรายการที่ตรงกัน
            CartItem::create([
                'userId' => $user->user_id,
                'outfit_id' => $outfit_id,
                'size_id' => $size_id,
                'color_id' => $color_id,
                'quantity' => $quantity,
            ]);
        }
    
        return redirect()->back()->with('success', 'เพิ่มสินค้าลงตะกร้าเรียบร้อย');
    }

    public function deleteItem(Request $request)
{
    $cart_id = $request->input('cart_id'); // รับค่า cart_id จากฟอร์ม
    $cartItem = CartItem::find($cart_id);

    if (!$cartItem) {
        return redirect()->back()->with('error', 'ไม่พบสินค้าที่ต้องการลบ');
    }

    // เปลี่ยนสถานะเป็น 'REMOVED' แทนการลบ
    $cartItem->status = 'REMOVED';
    $cartItem->save();

    return redirect()->back()->with('success', 'นำสินค้าออกจากตะกร้าเรียบร้อยแล้ว');
}



    public function updateItem(Request $request)
    {
        $cartItem = CartItem::find($request->cart_id);

        if (!$cartItem) {
            return response()->json(['success' => false, 'message' => 'ไม่พบสินค้าที่ต้องการอัปเดต'], 404);
        }

        // ดึงข้อมูล stock_quantity จาก ThaiOutfitSizeAndColor
        $sizeAndColor = ThaiOutfitSizeAndColor::where('outfit_id', $cartItem->outfit_id)
            ->where('size_id', $cartItem->size_id)
            ->where('color_id', $cartItem->color_id)
            ->where('status', 'INUSE')
            ->first();

        if (!$sizeAndColor || $sizeAndColor->amount === null) {
            return response()->json(['success' => false, 'message' => 'ไม่พบข้อมูลสต็อกสินค้า'], 404);
        }

        // ตรวจสอบว่าจำนวนใหม่ไม่เกิน stock_quantity
        if ($request->quantity > $sizeAndColor->amount) {
            return response()->json([
                'success' => false,
                'message' => 'จำนวนสินค้าเกินจากที่มีในสต็อก! คงเหลือ: ' . $sizeAndColor->amount
            ], 400);
        }

        if ($request->quantity < 1) {
            return response()->json(['success' => false, 'message' => 'จำนวนสินค้าต้องไม่น้อยกว่า 1'], 400);
        }

        // อัปเดตจำนวนสินค้า
        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return response()->json(['success' => true, 'message' => 'อัปเดตจำนวนสินค้าเรียบร้อย']);
    }


    

}
