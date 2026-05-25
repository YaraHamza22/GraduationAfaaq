<?php

namespace Modules\CommunicationModule\Services\V1;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Modules\UserMangementModule\Models\User;
use Modules\CommunicationModule\Models\ChatMessage;
use Modules\CommunicationModule\Models\ChatMessageRead;
use Modules\CommunicationModule\Models\ChatParticipant;
use Modules\CommunicationModule\Models\ChatThread;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ChatService
{
    public function createThread(array $payload, int $creatorId): ChatThread
    {
        try {
            return DB::transaction(function () use ($payload, $creatorId) {
                $targetParticipantIds = collect($payload['participant_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->unique()
                    ->values();

                if ($targetParticipantIds->isEmpty()) {
                    throw new HttpException(422, 'Select one instructor or student to start the chat.');
                }

                if ($targetParticipantIds->contains($creatorId)) {
                    throw new HttpException(422, 'You cannot start a chat with yourself.');
                }

                $participantIds = $targetParticipantIds
                    ->push($creatorId)
                    ->unique()
                    ->values();

                if ($participantIds->count() !== 2) {
                    throw new HttpException(422, 'Chat threads must include exactly one student and one instructor.');
                }

                $participants = User::query()
                    ->whereIn('id', $participantIds)
                    ->get()
                    ->keyBy('id');

                if ($participants->count() !== $participantIds->count()) {
                    throw new HttpException(422, 'One or more chat participants could not be found.');
                }

                $this->ensureDirectInstructorStudentParticipants($participants, $creatorId);

                $existingThread = $this->findExistingDirectThread($participantIds, $payload['course_id'] ?? null);
                if ($existingThread) {
                    return $existingThread;
                }

                $thread = ChatThread::query()->create([
                    'title' => $payload['title'] ?? null,
                    'course_id' => $payload['course_id'] ?? null,
                    'created_by' => $creatorId,
                ]);

                foreach ($participantIds as $userId) {
                    ChatParticipant::query()->firstOrCreate([
                        'chat_thread_id' => $thread->id,
                        'user_id' => $userId,
                    ], [
                        'role' => $userId === $creatorId ? 'owner' : 'member',
                    ]);
                }

                return $thread;
            });
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Failed to create chat thread', [
                'creator_id' => $creatorId,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(500, 'Unable to create chat thread right now.');
        }
    }

    public function addParticipant(ChatThread $thread, array $payload): ChatParticipant
    {
        throw new HttpException(422, 'Chat threads are limited to one student and one instructor.');
    }

    public function sendMessage(ChatThread $thread, int $senderId, array $payload): ChatMessage
    {
        return ChatMessage::query()->create([
            'chat_thread_id' => $thread->id,
            'sender_id' => $senderId,
            'body' => $payload['body'],
            'metadata' => $payload['metadata'] ?? null,
        ]);
    }

    public function markRead(ChatMessage $message, int $userId): ChatMessageRead
    {
        return ChatMessageRead::query()->firstOrCreate([
            'chat_message_id' => $message->id,
            'user_id' => $userId,
        ], [
            'read_at' => now(),
        ]);
    }

    protected function ensureDirectInstructorStudentParticipants($participants, int $creatorId): void
    {
        if ($participants->count() !== 2) {
            throw new HttpException(422, 'Chat threads must include exactly one student and one instructor.');
        }

        $creator = $participants->get($creatorId);
        if (! $creator) {
            throw new HttpException(422, 'The chat creator could not be resolved.');
        }

        $studentCount = $participants->filter(fn (User $user) => $user->hasRole('student'))->count();
        $instructorCount = $participants->filter(fn (User $user) => $user->hasRole('instructor'))->count();

        if ($studentCount !== 1 || $instructorCount !== 1) {
            throw new HttpException(422, 'Chat threads must be between one student and one instructor.');
        }
    }

    protected function findExistingDirectThread($participantIds, ?int $courseId): ?ChatThread
    {
        return ChatThread::query()
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->whereHas('participants', fn ($query) => $query->whereIn('user_id', $participantIds), '=', $participantIds->count())
            ->has('participants', '=', $participantIds->count())
            ->get()
            ->first(function (ChatThread $thread) use ($participantIds) {
                $threadParticipantIds = ChatParticipant::query()
                    ->where('chat_thread_id', $thread->id)
                    ->pluck('user_id')
                    ->sort()
                    ->values();

                return $threadParticipantIds->all() === $participantIds->sort()->values()->all();
            });
    }
}
