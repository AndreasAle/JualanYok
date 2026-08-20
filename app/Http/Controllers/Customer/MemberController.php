<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DigitalAccess;
use App\Models\Enrollment;
use App\Models\Membership;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $customerIds = Customer::where('user_id', $user->id)->pluck('id');

        $orders = Order::whereIn('customer_id', $customerIds)
            ->orWhere('customer_email', $user->email)
            ->paid()
            ->with('store')
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Member/Dashboard', [
            'stats' => [
                'orders' => Order::whereIn('customer_id', $customerIds)->paid()->count(),
                'downloads' => DigitalAccess::whereIn('customer_id', $customerIds)->count(),
                'courses' => Enrollment::whereIn('customer_id', $customerIds)->count(),
                'memberships' => Membership::whereIn('customer_id', $customerIds)
                    ->where('status', 'ACTIVE')->count(),
            ],
            'recentOrders' => $orders->map(fn (Order $o) => [
                'number' => $o->number,
                'store' => $o->store->name,
                'store_username' => $o->store->username,
                'grand_total' => (float) $o->grand_total,
                'status_label' => $o->status->label(),
                'created_at' => $o->created_at->diffForHumans(),
            ]),
            'courses' => Enrollment::whereIn('customer_id', $customerIds)
                ->with('course.product')
                ->latest()
                ->limit(4)
                ->get()
                ->map(fn (Enrollment $e) => [
                    'id' => $e->id,
                    'title' => $e->course->product->name,
                    'progress' => $e->progress_percent,
                    'thumbnail_url' => $e->course->product->thumbnailUrl(),
                ]),
        ]);
    }

    public function profile(Request $request): Response
    {
        return Inertia::render('Member/Profile', [
            'user' => $request->user()->only(['name', 'username', 'email', 'phone']),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Profil diperbarui.');
    }
}
