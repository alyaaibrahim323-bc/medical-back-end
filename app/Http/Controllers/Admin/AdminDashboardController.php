<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\UserPackage;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * GET /admin/dashboard/stats
     * الكروت اللي فوق (Total Users / Therapists / Bookings / Subscriptions)
     */
    public function stats(Request $r)
    {
        return response()->json([
            'data' => [
                'total_users'         => User::count(),
                'total_therapists'    => Therapist::count(),
                'total_bookings'      => TherapySession::count(),
                'total_subscriptions' => UserPackage::count(),
            ]
        ]);
    }

    /**
     * GET /admin/dashboard/recent-activity
     * البوكس اللي فيه:
     * - New user registered
     * - New therapist added
     * - Notification sent ...
     */
    public function recentActivity(Request $r)
    {
        // آخر 5 يوزرز
        $users = User::orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (User $u) {
                return [
                    'type'       => 'user_registered',
                    'title'      => 'New user registered – ' . ($u->name ?? 'User #' . $u->id),
                    'created_at' => $u->created_at,
                ];
            });

        // آخر 5 ثيرابست
        $therapists = Therapist::with('user')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (Therapist $t) {
                $name = optional($t->user)->name ?? ('Therapist #' . $t->id);
                return [
                    'type'       => 'therapist_added',
                    'title'      => 'New therapist added – ' . $name,
                    'created_at' => $t->created_at,
                ];
            });

        // آخر 5 Notifications من جدول notifications (لو موجود)
        $notifications = Notification::orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (Notification $n) {
                return [
                    'type'       => 'notification_sent',
                    'title'      => 'Notification sent – ' . ($n->type ?? 'broadcast'),
                    'created_at' => $n->created_at,
                ];
            });

        // ندمج الثلاثة ونرتّبهم بالأحدث
        $items = $users
            ->merge($therapists)
            ->merge($notifications)
            ->sortByDesc('created_at')
            ->values()
            ->take(10)        // نخليهم max 10 rows
            ->map(function ($row) {
                return [
                    'type'       => $row['type'],
                    'title'      => $row['title'],
                    'created_at' => $row['created_at'],
                    'time_ago'   => $row['created_at']
                        ? $row['created_at']->diffForHumans()
                        : null,
                ];
            });

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * GET /admin/dashboard/graph/users
     * الجراف اللي تحت: Total Users – This year vs Last year
     */
    public function usersGraph(Request $r)
    {
        $now        = now();
        $thisYear   = (int) $now->year;
        $lastYear   = $thisYear - 1;

        $thisYearData = $this->usersPerMonth($thisYear);
        $lastYearData = $this->usersPerMonth($lastYear);

        return response()->json([
            'data' => [
                'this_year' => [
                    'year'   => $thisYear,
                    'series' => $thisYearData,
                ],
                'last_year' => [
                    'year'   => $lastYear,
                    'series' => $lastYearData,
                ],
            ]
        ]);
    }

    /**
     * Helper: حساب عدد اليوزرات لكل شهر فى سنة معينة
     */
    protected function usersPerMonth(int $year): array
    {
        // نجهّز مصفوفة للشهور من 1 لـ 12 بقيمة 0
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = 0;
        }

        $rows = User::selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        foreach ($rows as $row) {
            $monthNum = (int) $row->month;
            $months[$monthNum] = (int) $row->total;
        }

        // نرجّعها بصيغة جاهزة للجراف: [ [ 'month' => 'Jan', 'total' => 120 ], ...]
        $out = [];
        foreach ($months as $m => $total) {
            $label = Carbon::create()->month($m)->format('M'); // Jan, Feb, ...
            $out[] = [
                'month' => $label,
                'total' => $total,
            ];
        }

        return $out;
    }
}
