<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $chatId;
    public int $messageId;
    public int $readerId;

    public function __construct(int $chatId, int $messageId, int $readerId)
    {
        $this->chatId    = $chatId;
        $this->messageId = $messageId;
        $this->readerId  = $readerId;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("chat.{$this->chatId}");
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id'    => $this->chatId,
            'message_id' => $this->messageId,
            'reader_id'  => $this->readerId,
        ];
    }
}
