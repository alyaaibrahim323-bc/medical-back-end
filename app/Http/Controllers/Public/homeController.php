<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Quote;


class homeController extends Controller
{
    public function homebanner()
    {
        $rows = Banner::active()
            ->get()
            ->map(function ($b) {
                return [
                    'id'        => $b->id,
                    'image_url' => asset('storage/'.$b->image_path),
                ];
            });

        return response()->json(['data' => $rows]);
    }
    public function homeQuote()
        {
            $quotes = Quote::active()->get();
            $q = Quote::active()->inRandomOrder()->first();

            if (!$q) {
                return response()->json(['data' => null]);
            }

            return response()->json([
                'data' => $quotes
            ]);
        }
}
