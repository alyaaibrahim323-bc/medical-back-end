<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTherapistRequest;
use App\Http\Requests\Admin\UpdateTherapistRequest;
use App\Models\Therapist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\TherapySession;
// لو عندك موديلات تانية للأڤيلابيليتي عدّلي الأسماء دول
use App\Models\TherapistSchedule;
use App\Models\TherapistTimeoff;


class TherapistController extends Controller
{
    public function index(Request $request)
    {
        $q = Therapist::with('user')
            ->when($request->filled('active'), fn($x) =>
                $x->where('is_active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['data' => $q]);
    }

    /**
     * store patterns:
     * 1) create new doctor user + therapist  (name,email,password,phone,...)
     * 2) attach existing user as doctor      (user_id + optional fields)
     */
    public function store(Request $request)
    {
        // هنشوف الأول هل بعت user_id ولا عايز يخلق يوزر جديد
        $isCreateNewUser = !$request->filled('user_id');

        if ($isCreateNewUser) {
            // create new user as doctor
            $data = $request->validate([
                'name'     => ['required','string','max:120'],
                'email'    => ['required','email','unique:users,email'],
                'password' => ['required','string','min:8'],
                'phone'    => ['nullable','string','max:30'],
                // بيانات اختيارية للثيرابست
                'specialty'   => ['nullable'],
                'bio'         => ['nullable'],
                'price_cents' => ['nullable','integer','min:0'],
                'currency'    => ['nullable','string','size:3'],
                'is_active'   => ['nullable','boolean'],
            ]);

            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'phone'    => $data['phone'] ?? null,
                'role'     => 'doctor',
                'status'   => 'active',
            ]);

            // spatie role
            if (!$user->hasRole('doctor')) {
                $user->syncRoles(['doctor']);
            }

            // normalize translatable
            $specialty = null;
            if (isset($data['specialty'])) {
                $specialty = is_string($data['specialty'])
                    ? ['en' => $data['specialty']]
                    : $data['specialty'];
            }
            $bio = null;
            if (isset($data['bio'])) {
                $bio = is_string($data['bio'])
                    ? ['en' => $data['bio']]
                    : $data['bio'];
            }

            $therapist = Therapist::create([
                'user_id'     => $user->id,
                'specialty'   => $specialty,
                'bio'         => $bio,
                'price_cents' => $data['price_cents'] ?? 0,
                'currency'    => $data['currency'] ?? 'EGP',
                'is_active'   => $data['is_active'] ?? true,
            ]);

            return response()->json([
                'message' => 'Doctor account created successfully',
                'data'    => $therapist->load('user'),
            ], 201);

        } else {
            // attach existing user
            $data = $request->validate([
                'user_id'     => ['required','exists:users,id'],
                'specialty'   => ['nullable'],
                'bio'         => ['nullable'],
                'price_cents' => ['nullable','integer','min:0'],
                'currency'    => ['nullable','string','size:3'],
                'is_active'   => ['nullable','boolean'],
            ]);

            $user = User::findOrFail($data['user_id']);

            // حدّث رول اليوزر لو مش doctor
            if ($user->role !== 'doctor') {
                $user->role = 'doctor';
                $user->save();
            }
            if (!$user->hasRole('doctor')) {
                $user->syncRoles(['doctor']);
            }

            // لو له record فعلاً رجّعه وعدّله، لو لأ اعمله
            $therapist = Therapist::firstOrNew(['user_id' => $user->id]);

            // normalize translatable
            if (isset($data['specialty'])) {
                $therapist->specialty = is_string($data['specialty'])
                    ? ['en' => $data['specialty']]
                    : $data['specialty'];
            }
            if (isset($data['bio'])) {
                $therapist->bio = is_string($data['bio'])
                    ? ['en' => $data['bio']]
                    : $data['bio'];
            }

            if (isset($data['price_cents'])) {
                $therapist->price_cents = $data['price_cents'];
            }
            if (isset($data['currency'])) {
                $therapist->currency = $data['currency'];
            }

            $therapist->is_active = $data['is_active'] ?? $therapist->is_active ?? false;
            $therapist->save();

            return response()->json([
                'message' => 'Doctor attached to therapist successfully',
                'data'    => $therapist->load('user'),
            ], 201);
        }
    }

    public function show($id)
    {
        $t = Therapist::with('user')->findOrFail($id);
        return response()->json(['data' => $t]);
    }

    public function update(UpdateTherapistRequest $request, $id)
    {
        $t = Therapist::with('user')->findOrFail($id);
        $data = $request->validated();

        foreach (['specialty','bio'] as $f) {
            if (array_key_exists($f, $data) && is_string($data[$f])) {
                $data[$f] = ['en' => $data[$f]];
            }
        }

        $t->update($data);

        // لو فعلناه لازم نتأكد اليوزر دكتور ومفعّل
        if (array_key_exists('is_active', $data) && $data['is_active'] === true) {
            $user = $t->user;
            if ($user->role !== 'doctor') {
                $user->role = 'doctor';
                $user->save();
            }
            if (!$user->hasRole('doctor')) {
                $user->syncRoles(['doctor']);
            }
        }

        return response()->json(['data' => $t->refresh()->load('user')]);
    }

    public function destroy($id)
    {
        Therapist::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function activate(Request $request, $id)
    {
        $request->validate(['is_active' => ['required','boolean']]);
        $t = Therapist::with('user')->findOrFail($id);
        $t->update(['is_active' => (bool) $request->is_active]);

        // لو فعّلناه نخلي اليوزر دكتور
        if ($request->boolean('is_active')) {
            $user = $t->user;
            if ($user->role !== 'doctor') {
                $user->role = 'doctor';
                $user->save();
            }
            if (!$user->hasRole('doctor')) {
                $user->syncRoles(['doctor']);
            }
        }

        return response()->json(['data' => $t]);
    }

        /**
     * Availability tab فى شاشة تفاصيل الدكتور:
     * GET /admin/therapists/{id}/schedules
     */
    public function schedules($id)
    {
        $therapist = Therapist::findOrFail($id);

        // NOTE: عدّلي اسم الموديل والحقول حسب اللى عندك
        $rows = TherapistSchedule::where('therapist_id', $therapist->id)
            ->orderBy('day_of_week')   // لو عندك عمود day_of_week
            ->orderBy('from_time')     // أو from / to
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }

    /**
     * Timeoffs tab فى شاشة تفاصيل الدكتور:
     * GET /admin/therapists/{id}/timeoffs
     */
    public function timeoffs($id)
    {
        $therapist = Therapist::findOrFail($id);

        // NOTE: عدّلي اسم الموديل لو عندك Timeoff أو TherapistTimeoff
        $rows = TherapistTimeoff::where('therapist_id', $therapist->id)
            ->orderByDesc('start_at')
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }

    /**
     * Performance & Activity tab:
     * كل الجلسات بتاعة دكتور معين + فلاتر status/scope
     * GET /admin/therapists/{id}/sessions?status=&scope=&from=&to=
     */
    public function sessions(Request $request, $id)
    {
        $therapist = Therapist::findOrFail($id);

        $q = TherapySession::with(['user','userPackage.package'])
            ->where('therapist_id', $therapist->id)
            ->orderByDesc('scheduled_at');

        // فلتر بالـ status: pending / confirmed / completed / cancelled / no_show
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        // scope = upcoming | past
        if ($request->query('scope') === 'upcoming') {
            $q->where('scheduled_at', '>=', now());
        } elseif ($request->query('scope') === 'past') {
            $q->where('scheduled_at', '<', now());
        }

        // فلتر بتاريخ من / إلى (اختيارى)
        if ($request->filled('from')) {
            $q->whereDate('scheduled_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('scheduled_at', '<=', $request->to);
        }

        return response()->json([
            'data' => $q->paginate(20),
        ]);
    }

}
