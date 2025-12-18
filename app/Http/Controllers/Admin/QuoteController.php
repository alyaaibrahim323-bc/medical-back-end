<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    // GET /admin/quotes?status=active|inactive|all
public function index(Request $r)
    {
        $search = trim((string) $r->query('search', ''));
        $status = $r->query('status', null);

        $q = Quote::query()
            ->when($status && $status !== 'all', fn($x) => $x->where('status', $status))
            ->when($search !== '', function ($x) use ($search) {
                $x->where(function ($qq) use ($search) {
                    // JSON search (MySQL)
                    $qq->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`text`, '$.en')) LIKE ?", ["%{$search}%"])
                       ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(`text`, '$.ar')) LIKE ?", ["%{$search}%"]);
                });
            })
            ->orderBy('sort_order')
            ->orderByDesc('id');

        return response()->json(['data' => $q->paginate(20)]);
    }


    // POST /admin/quotes
    public function store(Request $r)
    {
        $data = $r->validate([
            // ممكن تبعتي text_en, text_ar من الداشبورد ونجمعهم
            'text_en'   => ['required','string'],
            'text_ar'   => ['nullable','string'],
            'status'    => ['nullable','in:active,inactive'],
            'sort_order'=> ['nullable','integer','min:0'],
        ]);

        $text = ['en' => $data['text_en']];
        if (!empty($data['text_ar'])) {
            $text['ar'] = $data['text_ar'];
        }

        $q = Quote::create([
            'text'       => $text,
            'status'     => $data['status'] ?? 'active',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $q], 201);
    }

    // GET /admin/quotes/{id}
    public function show($id)
    {
        $q = Quote::findOrFail($id);
        return response()->json(['data' => $q]);
    }

    // PUT /admin/quotes/{id}
    public function update(Request $r, $id)
    {
        $quote = Quote::findOrFail($id);

        $data = $r->validate([
            'text_en'   => ['sometimes','string'],
            'text_ar'   => ['sometimes','nullable','string'],
            'status'    => ['sometimes','in:active,inactive'],
            'sort_order'=> ['sometimes','integer','min:0'],
        ]);

        $payload = $quote->text ?? [];

        if (array_key_exists('text_en',$data)) {
            $payload['en'] = $data['text_en'];
        }
        if (array_key_exists('text_ar',$data)) {
            if ($data['text_ar']) {
                $payload['ar'] = $data['text_ar'];
            } else {
                unset($payload['ar']);
            }
        }

        $update = [
            'text'       => $payload,
        ];

        if (isset($data['status']))     $update['status'] = $data['status'];
        if (isset($data['sort_order'])) $update['sort_order'] = $data['sort_order'];

        $quote->update($update);

        return response()->json(['data' => $quote->refresh()]);
    }

    // DELETE /admin/quotes/{id}
    public function destroy($id)
    {
        Quote::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
