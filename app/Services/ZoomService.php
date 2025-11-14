<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ZoomService
{
    protected function token(): string
    {
        return Cache::remember('zoom_token', 3000, function(){
            $res = Http::asForm()
             ->withBasicAuth(env('ZOOM_CLIENT_ID'), env('ZOOM_CLIENT_SECRET'))
             ->post(env('ZOOM_BASE_URL','https://api.zoom.us').'/oauth/token', [
                 'grant_type'=>'account_credentials',
                 'account_id'=>env('ZOOM_ACCOUNT_ID')
             ])->json();

            throw_unless(isset($res['access_token']), \RuntimeException::class, 'Zoom auth failed');
            return $res['access_token'];
        });
    }

    public function createMeeting(string $topic, string $startIso, int $durationMin): array
    {
        $res = Http::withToken($this->token())
          ->post(env('ZOOM_BASE_URL','https://api.zoom.us').'/v2/users/me/meetings', [
            'topic'=>$topic,'type'=>2,'start_time'=>$startIso,'duration'=>$durationMin,
            'settings'=>['join_before_host'=>false,'waiting_room'=>true,'approval_type'=>0],
          ])->json();

        return [
          'id'=>(string)($res['id']??''),
          'join_url'=>$res['join_url']??null,
          'start_url'=>$res['start_url']??null
        ];
    }
}
