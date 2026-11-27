<?php

namespace App\Addons\Multivendor\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    public function all_notifications()
    {
        $notifications = auth()->user()->notifications()->paginate(15);
        auth()->user()->unreadNotifications->markAsRead();

        return view('backend.notification.index', compact('notifications'));
    }
}
