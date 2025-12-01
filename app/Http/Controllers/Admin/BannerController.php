<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    // GET /admin/banners?status=active|inactive|all
    public function index(Request $r)
{
    // 1) Base query من غير فلتر status (عشان نقدر نطلع counts مظبوطة)
    $base = Banner::query();

    // 2) الأرقام لكل تاب
    $counts = [
        'all'      => (clone $base)->count(),
        'active'   => (clone $base)->where('status', 'active')->count(),
        'inactive' => (clone $base)->where('status', 'inactive')->count(),
    ];

    // 3) فلترة الـ list حسب التاب/الـ status لو مبعوت من الـ UI
    $q = clone $base;

    if ($r->filled('status') && $r->status !== 'all') {
        if (in_array($r->status, ['active', 'inactive'], true)) {
            $q->where('status', $r->status);
        }
    }

    $q->orderBy('sort_order')
      ->orderByDesc('id');

    // 4) الريسبونس: data + counts عشان الداشبورد
    return response()->json([
        'data'   => $q->paginate(20),
        'counts' => $counts,
    ]);
}


    // POST /admin/banners
    public function store(Request $r)
    {
        $data = $r->validate([
            'status'     => ['nullable','in:active,inactive'],
            'sort_order' => ['nullable','integer','min:0'],
            'image'      => ['required','image','mimes:jpg,jpeg,png,webp','max:2048'],
        ]);
        echo $data;
        $path = $r->file('image')->store('banners', 'public');

        $banner = Banner::create([
            'image_path' => $path,
            'status'     => $data['status'] ?? 'active',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $banner], 201);
    }

    // GET /admin/banners/{id}
    public function show($id)
    {
        $b = Banner::findOrFail($id);
        return response()->json(['data' => $b]);
    }

    // PUT /admin/banners/{id}
    public function update(Request $r, $id)
    {
        $banner = Banner::findOrFail($id);

        $data = $r->validate([
            'status'     => ['sometimes','in:active,inactive'],
            'sort_order' => ['sometimes','integer','min:0'],
            'image'      => ['sometimes','image','mimes:jpg,jpeg,png,webp','max:2048'],
        ]);

        if ($r->hasFile('image')) {
            if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $data['image_path'] = $r->file('image')->store('banners','public');
        }

        $banner->update($data);

        return response()->json(['data' => $banner->refresh()]);
    }

    // DELETE /admin/banners/{id}
    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);

        if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
            Storage::disk('public')->delete($banner->image_path);
        }

        $banner->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
