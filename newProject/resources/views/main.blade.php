@extends('layouts.main')

@section('title', 'Home')

@section('content')

@php
use Illuminate\Support\Facades\Auth;
use App\Models\SelectOutfitDetail;

$pendingSuggestionsCount = 0;
$suggestions = SelectOutfitDetail::where('customer_id', Auth::id())
->where('status', 'Pending Selection')
->count();

if ($suggestions > 0) {
$pendingSuggestionsCount++;
}
@endphp


<div class="container mx-auto px-4 py-8">
    @if($pendingSuggestionsCount > 0)
    <!-- Notification Div -->
    <div class="bg-yellow-200 p-4 rounded-md mb-6 border-2">
        <p class="text-yellow-700 font-medium">คุณมี {{ $pendingSuggestionsCount }} รายการที่มีชุดทดแทนใหม่รอการตอบรับ</p>
        <p class="text-yellow-600 text-sm mt-1">
            กรุณาตรวจสอบและตอบรับหรือปฏิเสธชุดทดแทนเพื่อดำเนินการต่อ
        </p>
        <!-- ปุ่มตรวจสอบคำสั่งซื้อ (เปลี่ยนเป็นสีเหลือง) -->
        <button class="trigger-btn bg-yellow-500 text-white px-4 py-2 rounded-md hover:scale-105 transition-transform duration-200 relative mt-2"
            onclick="showPopup()">
            ตรวจสอบคำสั่งซื้อ
            <span class="notification-badge bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center absolute -top-1 -right-1">
                {{ $pendingSuggestionsCount }}
            </span>
        </button>
    </div>
    
    <img src="{{ url('images/outfits/main.png') }}" alt="Main Outfit" class="w-full h-auto rounded-lg">

    <!-- Popup Modal (ปรับให้ดู modern) -->
    <div class="popup-overlay fixed inset-0 bg-black bg-opacity-70 z-50 opacity-0 transition-opacity duration-500 hidden"
        id="popupOverlay">
        <div class="popup-content absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white p-8 rounded-xl shadow-lg w-11/12 max-w-lg text-center scale-75 transition-transform duration-500">
            <div class="flex justify-center mb-4">
                <div class="bg-yellow-100 p-3 rounded-full">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl"></i>
                </div>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-4">แจ้งเตือนคำสั่งซื้อ</h3>
            <p class="mb-6 text-gray-600">คุณมี <strong class="text-yellow-600">{{ $pendingSuggestionsCount }}</strong> รายการที่รอการยืนยันชุดทดแทน</p>
            <div class="flex justify-center gap-4">
                <a href="{{ route('profile.customer.orderHistory') }}"
                    class="inline-flex items-center bg-green-500 text-white px-5 py-2 rounded-full hover:bg-green-600 transition-all duration-300 shadow-md hover:shadow-lg">
                    <i class="fas fa-history mr-2"></i> ไปที่ประวัติคำสั่งซื้อ
                </a>
                <button class="inline-flex items-center bg-gray-200 text-gray-800 px-5 py-2 rounded-full hover:bg-gray-300 transition-all duration-300"
                    onclick="hidePopup()">
                    <i class="fas fa-times mr-2"></i> ปิด
                </button>
            </div>
        </div>
    </div>
    @endif

    <h2 class="text-2xl font-bold mb-4">รายการชุดไทย</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @if($outfits->isEmpty())
        <p class="text-red-500">❌ ไม่พบผลลัพธ์ที่ตรงกับ "{{ request('searchkey') }}"</p>
        @endif

        @foreach ($outfits as $dress)
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <img src="{{ asset($dress->image) }}" class="w-full h-60 object-cover" alt="{{ $dress->name }}">
            <div class="p-4">
                <h5 class="text-xl font-semibold">{{ $dress->name }}</h5>
                <p class="text-gray-600">{{ $dress->description }}</p>
                <p class="text-lg font-bold text-green-600">฿{{ number_format($dress->price, 2) }}</p>
                <a href="{{ url('orderdetail/outfit/' . $dress->outfit_id) }}"
                    class="mt-3 inline-block px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition">
                    ดูรายละเอียด
                </a>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
@if($pendingSuggestionsCount > 0)
<script>
    function showPopup() {
        const overlay = document.getElementById('popupOverlay');
        overlay.classList.remove('hidden');
        setTimeout(() => {
            overlay.classList.add('opacity-100');
            overlay.querySelector('.popup-content').classList.remove('scale-75');
            overlay.querySelector('.popup-content').classList.add('scale-100');
        }, 10);
    }

    function hidePopup() {
        const overlay = document.getElementById('popupOverlay');
        overlay.classList.remove('opacity-100');
        overlay.querySelector('.popup-content').classList.remove('scale-100');
        overlay.querySelector('.popup-content').classList.add('scale-75');
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, 500);
    }
</script>
@endif
@endpush