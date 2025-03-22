<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Issue;
use App\Models\Notifications;


class IssueController extends Controller
{
    public function create()
    {
        return view('issue.report_issue');
    }

    public function replyPage($id)
    {
        $issue = Issue::findOrFail($id);
        return view('issue.issue_reply',compact('issue'));
    }
    
    public function reply(Request $request, $id)
    {
        $validated = $request->validate([
            'reply' => 'required|string|max:1000',
        ]);
    
        $issue = Issue::findOrFail($id);
        if($issue->status == 'reported'){
            $issue->status = 'in_progress';
        }
        $issue->reply = $validated['reply'];
        $issue->save();
        return redirect()->route('admin.issue.show')->with('success', 'การตอบกลับถูกบันทึก');
    }

    public function issueReported($id)
    {
        $issue = Issue::findOrFail($id);
        return view('issue.issue_report',compact('issue'));
    }

    public function reportPage(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:256',
            'description' => 'required|string|max:1000',
        ]);
    
        // บันทึกปัญหาลงในฐานข้อมูล
        $issue = Issue::create([
            'user_id' => Auth::user()->user_id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => 'reported',
        ]);
    
        // ส่งการแจ้งเตือนให้ Admin
        $this->sendNotificationToAdmin($issue);
    
        return $this->showReportStatus();
    }
    
    private function sendNotificationToAdmin($issue)
    {
        // ส่งการแจ้งเตือนให้ Admin
        Notifications::create([
            'issue_id' => $issue->id,
            'message' => 'มีปัญหาถูกแจ้งจากผู้ใช้ กรุณาตรวจสอบ',
        ]);
    }
    
    public function updateStatus($issueId)
    {
        $issue = Issue::findOrFail($issueId);
        if($issue->status == 'reported'){
            $issue->status = 'in_progress';  // เปลี่ยนสถานะ
        }else{
            $issue->status = 'fixed';  // เปลี่ยนสถานะ
        }
        $issue->save();
    
        return back()->with('success', 'ปัญหาถูกแก้ไขและแจ้งให้ผู้ใช้ทราบ');
    }

    // show Notifications ของ admin
    public function showNotifications()
    {
        $notifications = Notifications::join('issues', 'notifications.issue_id', '=', 'issues.id')->where('status','!=', 'fixed')->get();

        // dd($notifications);
        
        return view('notifications.index', compact('notifications'));
    }

    // show Notifications ของ user
    public function showReportStatus()
    {
        $notifications = auth()->user()->issue()->get();
        // dd($notifications);
        
        return view('notifications.index', compact('notifications'));
    }

    
    public function readNotification($notificationId)
    {
        $notification = Notifications::findOrFail($notificationId);
        $notification->is_read = true;
        $notification->user_id = Auth::user()->user_id;
        $notification->save();

        return redirect()->route('issues.show', $notification->issue_id);
    }

 
}
