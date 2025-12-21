<?php

namespace App\Http\Controllers;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Http\Requests\ReadChatMessageRequest;
use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\ChatRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;



class ChatMessageController extends Controller
{
    /**
     * List messages in a chat (paginated, oldest → newest).
     */
    public function index(Request $request, Chat $chat)
    {
        $this->authorize('view', $chat);

        $messages = $chat->messages()
            ->with(['sender', 'reads'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return ChatMessageResource::collection($messages);
    }

    /**
     * Store a new message in a chat.
     */
  public function store(StoreChatMessageRequest $request, Chat $chat): ChatMessageResource
{
    $this->authorize('message', $chat);

    $user       = $request->user();
    $senderRole = $this->resolveSenderRole($user);
    $path       = $this->storeAttachmentIfExists($request, $chat);
    $type       = $this->detectMessageType($request, $path);

    $msg = ChatMessage::create([
        'chat_id'         => $chat->id,
        'sender_id'       => $user->id,
        'sender_role'     => $senderRole,
        'type'            => $type,
        'body'            => $request->input('body'),
        'attachment_path' => $path,
        'duration_ms'     => $request->input('duration_ms'),
        'replied_to_id'   => $request->input('replied_to_id'),
    ]);

    // 🟢 تحديث حالة الشات (pending / replied)
    $this->updateChatStatusOnNewMessage($chat, $senderRole);

    // 🟣 Auto Notification: لو اللي بيرد دكتور أو أدمن → نبعت إشعار لليوزر
    if (in_array($senderRole, ['therapist', 'admin'], true) && $chat->user_id) {

        $fromName = $senderRole === 'therapist'
            ? optional($chat->therapist?->user)->name
            : 'Support';

        app(NotificationService::class)->sendToUser(
            $chat->user_id,
            'chat_reply',
            [
                'from'    => $fromName,
                'chat_id' => $chat->id,
            ]
        );
    }

    // ✅ Auto Read: لو المرسل دكتور/أدمن → اعتبر آخر رسالة من الكلاينت اتقرأت
    if (in_array($senderRole, ['therapist', 'admin'], true)) {
        $lastClientMsg = $chat->messages()
            ->where('sender_role', 'client')
            ->latest('id')
            ->first();

        if ($lastClientMsg) {
            ChatRead::updateOrCreate(
                [
                    'message_id' => $lastClientMsg->id,
                    'user_id'    => $user->id, // الدكتور أو الأدمن
                ],
                [
                    'read_at'    => now(),
                ]
            );

            // نبث event عشان الـ UI يحدّث حالة الرسالة
            broadcast(new MessageRead(
                $chat->id,
                $lastClientMsg->id,
                $user->id
            ))->toOthers();
        }
    }

    // بث الرسالة في الريل تايم
    broadcast(new MessageSent($msg))->toOthers();

    return new ChatMessageResource(
        $msg->loadMissing('reads', 'sender')
    );
}


    /**
     * Mark a specific message as read by current user.
     */
    public function read(ReadChatMessageRequest $request, Chat $chat): JsonResponse
    {
        $this->authorize('participate', $chat);

        $user      = $request->user();
        $messageId = (int) $request->input('message_id');

        $message = $chat->messages()
            ->where('id', $messageId)
            ->firstOrFail();

        if ($message->sender_id === $user->id) {
            return response()->json([
                'ok'      => false,
                'message' => 'Sender cannot mark own message as read.',
            ], 422);
        }

        ChatRead::updateOrCreate(
            [
                'message_id' => $message->id,
                'user_id'    => $user->id,
            ],
            [
                'read_at' => now(),
            ]
        );

        broadcast(new MessageRead(
            $chat->id,
            $message->id,
            $user->id
        ))->toOthers();

        $readBy = $message->reads()->pluck('user_id');

        return response()->json([
            'ok'          => true,
            'message_id'  => $message->id,
            'read_by'     => $readBy,
            'read_by_me'  => true,
        ]);
    }

    /**
     * Resolve logical sender role string.
     */
    protected function resolveSenderRole(\App\Models\User $user): string
{
    // log debugging (تقدري تمسحيه بعد ما تتأكدي)
    Log::info('RESOLVE SENDER ROLE', [
        'user_id' => $user->id,
        'roles'   => $user->getRoleNames(),
        'db_role' => $user->role ?? null,
        'has_therapist' => (bool) $user->therapist,
    ]);

    // 1) Spatie roles لو إنتِ مستخدماها
    if ($user->hasRole('admin')) {
        return 'admin';
    }

    if ($user->hasRole('doctor')) {
        // فى الداتا انتِ بتسميها therapist جوّه الشات
        return 'therapist';
    }

    if ($user->hasRole('client')) {
        return 'client';
    }

    // 2) fallback على عمود users.role لو لسه بتستعمليه
    if (isset($user->role)) {
        if ($user->role === 'admin') {
            return 'admin';
        }
        if (in_array($user->role, ['doctor', 'therapist'], true)) {
            return 'therapist';
        }
        if ($user->role === 'client') {
            return 'client';
        }
    }

    // 3) fallback أخير: لو عنده relationship therapist يبقى دكتور
    if ($user->therapist) {
        return 'therapist';
    }

    // 4) آخر اختيار: اعتبره client
    return 'client';
}


    /**
     * Store attachment if present and return path or null.
     */
    protected function storeAttachmentIfExists(Request $request, Chat $chat): ?string
    {
        if (! $request->hasFile('file')) {
            return null;
        }

        return $request->file('file')->store(
            "chats/{$chat->id}",
            'public'
        );
    }

    /**
     * Update chat status & timestamps based on who sent the message.
     */
    protected function updateChatStatusOnNewMessage(Chat $chat, string $senderRole): void
    {
        $now = now();

        $chat->last_message_at = $now;

        if ($senderRole === 'client') {
            $chat->status                 = 'pending';
            $chat->last_client_message_at = $now;
        } elseif (in_array($senderRole, ['therapist', 'admin'], true)) {
            $chat->status                    = 'replied';
            $chat->last_therapist_message_at = $now;
        }

        $chat->save();
    }

    protected function detectMessageType(Request $request, ?string $path): string
{
    // مفيش فايل → Text
    if (! $path) {
        return 'text';
    }

    // لو الفرونت بعِت type صريح نمشي معاه (مثلاً audio)
    if ($request->filled('type')) {
        return $request->input('type');
    }

    // نحدد من الـ MIME
    $file = $request->file('file');
    if (! $file) {
        return 'file';
    }

    $mime = $file->getMimeType();

    if (str_starts_with($mime, 'image/')) {
        return 'image';
    }

    if (str_starts_with($mime, 'audio/')) {
        return 'audio';
    }

    return 'file';
}

}
