<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationSettingController extends Controller
{
 
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $settings = NotificationSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'general'  => true,
                'session'  => true,
                'rating'   => true,
                'security' => true,
                'system'   => true,
            ]
        );

        return response()->json(['data' => $settings]);
    }

  
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'general'  => ['sometimes','boolean'],
            'session'  => ['sometimes','boolean'],
            'rating'   => ['sometimes','boolean'],
            'security' => ['sometimes','boolean'],
            'system'   => ['sometimes','boolean'],
        ]);

        $settings = NotificationSetting::firstOrCreate(['user_id' => $user->id]);
        $settings->update($data);

        return response()->json(['data' => $settings->fresh()]);
    }
}
