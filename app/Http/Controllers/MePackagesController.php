<?php

namespace App\Http\Controllers;

use App\Models\UserPackage;
use App\Http\Resources\UserPackageResource;

class MePackagesController extends Controller
{
    public function index()
    {
        $q = UserPackage::with([
                'package',
                'therapist.user',
                'user',
                'payments',
                'redemptions',
            ])
            ->where('user_id', auth()->id())
            ->orderByDesc('id');

        return UserPackageResource::collection($q->paginate(20));
    }

    public function show($id)
    {
        $p = UserPackage::with([
                'package',
                'therapist.user',
                'user',
                'payments',
                'redemptions',
                'lastPaidPayment',

            ])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return new UserPackageResource($p);
    }
}
