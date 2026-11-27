<?php

namespace App\Addons\Multivendor\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index()
    {
        abort_unless((int) get_setting('conversation_system') === 1, 404);

        $conversations = $this->query()->with(['sender', 'receiver', 'messages'])->latest()->paginate(10);

        return view('backend.conversations.index', compact('conversations'));
    }

    public function show($id)
    {
        $conversation = $this->ownedConversation((int) decrypt($id));
        $conversation->forceFill(
            $conversation->sender_id === auth()->id()
                ? ['sender_viewed' => 1]
                : ['receiver_viewed' => 1]
        )->save();

        return view('backend.conversations.show', compact('conversation'));
    }

    public function storeMessage(Request $request)
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:5000'],
        ]);
        $conversation = $this->ownedConversation((int) $data['conversation_id']);

        $message = new Message;
        $message->conversation_id = $conversation->id;
        $message->user_id = auth()->id();
        $message->message = $data['message'];
        $message->save();
        $conversation->forceFill(
            $conversation->sender_id === auth()->id()
                ? ['sender_viewed' => 1, 'receiver_viewed' => 0]
                : ['sender_viewed' => 0, 'receiver_viewed' => 1]
        )->save();

        return back()->with('success', translate('Message has been sent'));
    }

    public function refresh()
    {
        $conversations = $this->query()->with(['sender', 'receiver', 'messages'])->latest()->paginate(10);

        return view('backend.conversations.index', compact('conversations'));
    }

    private function query()
    {
        return Conversation::query()->where(function ($query) {
            $query->where('sender_id', auth()->id())->orWhere('receiver_id', auth()->id());
        });
    }

    private function ownedConversation(int $id): Conversation
    {
        return $this->query()->with('messages')->whereKey($id)->firstOrFail();
    }
}
