<?php

namespace Modules\CommunicationModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\CommunicationModule\Http\Requests\Chat\AddChatParticipantRequest;
use Modules\CommunicationModule\Http\Requests\Chat\StoreChatThreadRequest;
use Modules\CommunicationModule\Models\ChatMessage;
use Modules\CommunicationModule\Models\ChatParticipant;
use Modules\CommunicationModule\Models\ChatThread;
use Modules\CommunicationModule\Services\V1\ChatService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ChatThreadController extends Controller
{
    public function __construct(private ChatService $chatService)
    {
        $this->middleware('permission:list-chat_messages')->only(['index', 'show', 'participants', 'unreadCount']);
        $this->middleware('permission:create-chat_message')->only(['store', 'addParticipant']);
        $this->middleware('permission:update-chat_message')->only(['archive']);
        $this->middleware('permission:delete-chat_message')->only(['removeParticipant']);
    }

    public function index()
    {
        try {
            $userId = Auth::id();
            $threadIds = ChatParticipant::query()
                ->where('user_id', $userId)
                ->pluck('chat_thread_id');

            $threads = ChatThread::query()
                ->whereIn('id', $threadIds)
                ->with([
                    'latestMessage:id,chat_thread_id,body,created_at,updated_at',
                    'participants.user:id,name,email',
                ])
                ->latest()
                ->paginate(15);

            $threads->getCollection()->transform(function (ChatThread $thread) {
                $thread->setAttribute(
                    'last_message_body',
                    $thread->latestMessage?->body ?? 'No messages yet.'
                );

                return $thread;
            });

            return self::paginated($threads, 'Chat threads fetched successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to fetch chat threads', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(500, 'Unable to load chat threads right now.');
        }
    }

    public function store(StoreChatThreadRequest $request)
    {
        $thread = $this->chatService->createThread($request->validated(), Auth::id());
        return self::success($thread, 'Chat thread created successfully.', 201);
    }

    public function show(ChatThread $chatThread)
    {
        $this->authorize('view', $chatThread);

        $chatThread->load([
            'participants.user:id,name,email',
            'latestMessage:id,chat_thread_id,body,created_at,updated_at',
        ]);

        return self::success($chatThread, 'Chat thread fetched successfully.');
    }

    public function archive(ChatThread $chatThread)
    {
        $this->authorize('manage', $chatThread);
        $chatThread->update(['is_archived' => true, 'archived_at' => now()]);
        return self::success($chatThread->fresh(), 'Chat thread archived successfully.');
    }

    public function participants(ChatThread $chatThread)
    {
        $this->authorize('view', $chatThread);

        try {
            $participants = ChatParticipant::query()
                ->where('chat_thread_id', $chatThread->id)
                ->with('user:id,name,email')
                ->get();

            return self::success($participants, 'Participants fetched successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to fetch chat participants', [
                'chat_thread_id' => $chatThread->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(500, 'Unable to load chat participants right now.');
        }
    }

    public function addParticipant(AddChatParticipantRequest $request, ChatThread $chatThread)
    {
        throw new HttpException(422, 'Chat threads are limited to one student and one instructor.');
    }

    public function removeParticipant(ChatThread $chatThread, int $userId)
    {
        throw new HttpException(422, 'Chat threads are limited to one student and one instructor.');
    }

    public function unreadCount()
    {
        try {
            $userId = Auth::id();
            $threadIds = ChatParticipant::query()->where('user_id', $userId)->pluck('chat_thread_id');

            $unread = ChatMessage::query()
                ->whereIn('chat_thread_id', $threadIds)
                ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $userId))
                ->count();

            return self::success(['unread_count' => $unread], 'Unread count fetched successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to fetch unread chat count', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(500, 'Unable to load unread chat count right now.');
        }
    }
}
