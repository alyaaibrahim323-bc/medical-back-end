<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index(Request $r)
{
    $base = Banner::query();

    $counts = [
        'all'      => (clone $base)->count(),
        'active'   => (clone $base)->where('status', 'active')->count(),
        'inactive' => (clone $base)->where('status', 'inactive')->count(),
    ];

    $q = clone $base;

    if ($r->filled('status') && $r->status !== 'all') {
        if (in_array($r->status, ['active', 'inactive'], true)) {
            $q->where('status', $r->status);
        }
    }

    $q->orderBy('sort_order')
      ->orderByDesc('id');

    return response()->json([
        'data'   => $q->paginate(20),
        'counts' => $counts,
    ]);
}


    public function store(Request $r)
    {
        $data = $r->validate([
            'status'     => ['nullable','in:active,inactive'],
            'sort_order' => ['nullable','integer','min:0'],
            'image'      => ['required','image','mimes:jpg,jpeg,png,webp','max:2048'],
        ]);
        $path = $r->file('image')->store('banners', 'public');

        $banner = Banner::create([
            'image_path' => $path,
            'status'     => $data['status'] ?? 'active',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $banner], 201);
    }

    public function show($id)
    {
        $b = Banner::findOrFail($id);
        return response()->json(['data' => $b]);
    }

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
