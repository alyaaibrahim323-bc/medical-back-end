<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\StoreTimeoffRequest;
use App\Models\Therapist;
use App\Models\TherapistTimeoff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimeoffController extends Controller
{
    protected function therapistOfDoctor()
    {
        $user = Auth::user();
        return Therapist::where('user_id', $user->id)->firstOrFail();
    }

    public function index()
    {
        $t = $this->therapistOfDoctor();
        return response()->json(['data'=>$t->timeoffs()->orderBy('off_date')->get()]);
    }

    public function store(StoreTimeoffRequest $request)
    {
        $t = $this->therapistOfDoctor();
        $data = $request->validated();
        $data['therapist_id'] = $t->id;

        $off = TherapistTimeoff::create($data);
        return response()->json(['data'=>$off], 201);
    }

    public function destroy($id)
    {
        $t = $this->therapistOfDoctor();
        TherapistTimeoff::where('therapist_id',$t->id)->findOrFail($id)->delete();
        return response()->json(['message'=>'Deleted']);
    }
}
