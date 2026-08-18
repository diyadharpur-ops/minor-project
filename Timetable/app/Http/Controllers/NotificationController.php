<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications.
     */
    public function index(Request $request): mixed
    {
        if (! session('admin.auth')) {
            return redirect('/admin/login');
        }

        $notifications = Notification::latest()->paginate(10);
        $totalNotifications = Notification::count();
        $highPriority = Notification::where('priority', 'High')->count();
        $mediumPriority = Notification::where('priority', 'Medium')->count();
        $information = Notification::where('priority', 'Info')->count();
        $unread = Notification::where('status', 'Unread')->count();
        $read = Notification::where('status', 'Read')->count();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'stats' => [
                    'totalNotifications' => $totalNotifications,
                    'highPriority' => $highPriority,
                    'mediumPriority' => $mediumPriority,
                    'information' => $information,
                    'unread' => $unread,
                    'read' => $read,
                ],
                'html' => view('admin.notifications._list', compact('notifications'))->render(),
            ]);
        }

        return view('admin.notifications.index', compact(
            'totalNotifications',
            'highPriority',
            'mediumPriority',
            'information',
            'unread',
            'read',
            'notifications'
        ));
    }

    /**
     * Store a newly created notification.
     */
    public function store(Request $request): mixed
    {
        if (! session('admin.auth')) {
            return redirect('/admin/login');
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'message' => 'nullable|string',
            'priority' => 'nullable|string|in:High,Medium,Info',
            'category' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'audience' => 'nullable|string|in:student,faculty,all',
            'status' => 'nullable|string|in:Read,Unread',
            'module_name' => 'nullable|string|max:255',
            'reference_id' => 'nullable|integer',
        ]);

        $desc = $data['description'] ?? $data['message'] ?? '';
        $cat = $data['category'] ?? $data['type'] ?? 'General';
        $pri = $data['priority'] ?? 'Info';
        $aud = $data['audience'] ?? 'all';

        Notification::create([
            'title' => $data['title'],
            'description' => $desc,
            'message' => $desc,
            'priority' => $pri,
            'category' => $cat,
            'type' => $cat,
            'status' => $data['status'] ?? 'Unread',
            'module_name' => $data['module_name'] ?? 'Manual',
            'reference_id' => $data['reference_id'] ?? null,
            'audience' => $aud,
            'created_by' => session('admin.auth')['name'] ?? 'Admin',
        ]);

        return redirect('/admin/notifications')->with('status', 'Announcement published successfully.');
    }

    /**
     * Filter notifications dynamically.
     */
    public function filter(Request $request, string $type): mixed
    {
        if (! session('admin.auth')) {
            return $request->ajax()
                ? response()->json(['error' => 'Unauthorized'], 401)
                : redirect('/admin/login');
        }

        $query = Notification::query();

        switch (strtolower($type)) {
            case 'high':
                $query->where('priority', 'High');
                break;
            case 'medium':
                $query->where('priority', 'Medium');
                break;
            case 'information':
            case 'info':
                $query->where('priority', 'Info');
                break;
            case 'unread':
                $query->where('status', 'Unread');
                break;
            case 'read':
                $query->where('status', 'Read');
                break;
        }

        $notifications = $query->latest()->paginate(10)->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'stats' => [
                    'totalNotifications' => Notification::count(),
                    'highPriority' => Notification::where('priority', 'High')->count(),
                    'mediumPriority' => Notification::where('priority', 'Medium')->count(),
                    'information' => Notification::where('priority', 'Info')->count(),
                    'unread' => Notification::where('status', 'Unread')->count(),
                    'read' => Notification::where('status', 'Read')->count(),
                ],
                'html' => view('admin.notifications._list', compact('notifications'))->render(),
            ]);
        }

        $totalNotifications = Notification::count();
        $highPriority = Notification::where('priority', 'High')->count();
        $mediumPriority = Notification::where('priority', 'Medium')->count();
        $information = Notification::where('priority', 'Info')->count();
        $unread = Notification::where('status', 'Unread')->count();
        $read = Notification::where('status', 'Read')->count();

        return view('admin.notifications.index', compact(
            'totalNotifications',
            'highPriority',
            'mediumPriority',
            'information',
            'unread',
            'read',
            'notifications',
            'type'
        ));
    }

    /**
     * Refresh the notifications stats and list.
     */
    public function refresh(Request $request): mixed
    {
        if (! session('admin.auth')) {
            return $request->ajax()
                ? response()->json(['error' => 'Unauthorized'], 401)
                : redirect('/admin/login');
        }

        $query = Notification::query();
        $type = $request->input('type', 'all');

        switch (strtolower($type)) {
            case 'high':
                $query->where('priority', 'High');
                break;
            case 'medium':
                $query->where('priority', 'Medium');
                break;
            case 'information':
            case 'info':
                $query->where('priority', 'Info');
                break;
            case 'unread':
                $query->where('status', 'Unread');
                break;
            case 'read':
                $query->where('status', 'Read');
                break;
        }

        $notifications = $query->latest()->paginate(10)->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'stats' => [
                    'totalNotifications' => Notification::count(),
                    'highPriority' => Notification::where('priority', 'High')->count(),
                    'mediumPriority' => Notification::where('priority', 'Medium')->count(),
                    'information' => Notification::where('priority', 'Info')->count(),
                    'unread' => Notification::where('status', 'Unread')->count(),
                    'read' => Notification::where('status', 'Read')->count(),
                ],
                'html' => view('admin.notifications._list', compact('notifications'))->render(),
            ]);
        }

        return redirect('/admin/notifications');
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, int $id): mixed
    {
        if (! session('admin.auth')) {
            return $request->ajax()
                ? response()->json(['error' => 'Unauthorized'], 401)
                : redirect('/admin/login');
        }

        $notification = Notification::findOrFail($id);
        $notification->update(['status' => 'Read']);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.',
                'stats' => [
                    'totalNotifications' => Notification::count(),
                    'highPriority' => Notification::where('priority', 'High')->count(),
                    'mediumPriority' => Notification::where('priority', 'Medium')->count(),
                    'information' => Notification::where('priority', 'Info')->count(),
                    'unread' => Notification::where('status', 'Unread')->count(),
                    'read' => Notification::where('status', 'Read')->count(),
                ],
            ]);
        }

        return back()->with('status', 'Notification marked as read.');
    }

    /**
     * Mark all unread notifications as read.
     */
    public function readAll(Request $request): mixed
    {
        if (! session('admin.auth')) {
            return $request->ajax()
                ? response()->json(['error' => 'Unauthorized'], 401)
                : redirect('/admin/login');
        }

        Notification::where('status', 'Unread')->update(['status' => 'Read']);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read.',
                'stats' => [
                    'totalNotifications' => Notification::count(),
                    'highPriority' => Notification::where('priority', 'High')->count(),
                    'mediumPriority' => Notification::where('priority', 'Medium')->count(),
                    'information' => Notification::where('priority', 'Info')->count(),
                    'unread' => Notification::where('status', 'Unread')->count(),
                    'read' => Notification::where('status', 'Read')->count(),
                ],
            ]);
        }

        return redirect('/admin/notifications')->with('status', 'All notifications marked as read.');
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, int $id): mixed
    {
        if (! session('admin.auth')) {
            return $request->ajax()
                ? response()->json(['error' => 'Unauthorized'], 401)
                : redirect('/admin/login');
        }

        $notification = Notification::find($id);
        if ($notification) {
            $notification->delete();
            Notification::trigger('Notification Deleted', ['id' => $id]);
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification deleted.',
                'stats' => [
                    'totalNotifications' => Notification::count(),
                    'highPriority' => Notification::where('priority', 'High')->count(),
                    'mediumPriority' => Notification::where('priority', 'Medium')->count(),
                    'information' => Notification::where('priority', 'Info')->count(),
                    'unread' => Notification::where('status', 'Unread')->count(),
                    'read' => Notification::where('status', 'Read')->count(),
                ],
            ]);
        }

        return redirect('/admin/notifications')->with('status', 'Notification deleted successfully.');
    }

    /**
     * Clear all notifications.
     */
    public function clear(Request $request): mixed
    {
        if (! session('admin.auth')) {
            return $request->ajax()
                ? response()->json(['error' => 'Unauthorized'], 401)
                : redirect('/admin/login');
        }

        Notification::truncate();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'All notifications cleared.',
                'stats' => [
                    'totalNotifications' => 0,
                    'highPriority' => 0,
                    'mediumPriority' => 0,
                    'information' => 0,
                    'unread' => 0,
                    'read' => 0,
                ],
            ]);
        }

        return redirect('/admin/notifications')->with('status', 'All notifications cleared.');
    }

    /**
     * Display details of a single notification.
     */
    public function show(int $id): mixed
    {
        if (! session('admin.auth')) {
            return redirect('/admin/login');
        }

        $notification = Notification::findOrFail($id);

        return view('admin.notifications.show', compact('notification'));
    }
}
