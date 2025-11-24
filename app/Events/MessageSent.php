<?php

namespace App\Events;

use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $chatId;
    public array $message;

    public function __construct(ChatMessage $message)
    {
        $this->chatId  = $message->chat_id;
        $this->message = (new ChatMessageResource(
            $message->loadMissing('reads', 'sender')
        ))->resolve();
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("chat.{$this->chatId}");
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
