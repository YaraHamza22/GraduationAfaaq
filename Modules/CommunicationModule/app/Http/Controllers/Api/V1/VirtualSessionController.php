<?php

namespace Modules\CommunicationModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\CommunicationModule\Events\VirtualSessionSignalSent;
use Modules\CommunicationModule\Jobs\ProcessIntegrationWebhook;
use Modules\CommunicationModule\Http\Requests\VirtualSession\StoreAttendanceRequest;
use Modules\CommunicationModule\Http\Requests\VirtualSession\StoreVirtualSessionRequest;
use Modules\CommunicationModule\Http\Requests\VirtualSession\UpdateVirtualSessionRequest;
use Modules\CommunicationModule\Models\VirtualSession;
use Modules\CommunicationModule\Services\V1\IntegrationService;
use Modules\CommunicationModule\Support\VirtualSessionAccess;
use Throwable;

class VirtualSessionController extends Controller
{
    public function __construct(private IntegrationService $integrationService)
    {
    }

    public function index()
    {
        $query = VirtualSession::query()->latest();
        $user = Auth::user();

        if ($user && ! $user->hasRole('super-admin')) {
            $query->where('host_id', $user->id);
        }

        $sessions = $query->paginate(15);
        return self::paginated($sessions, 'Virtual sessions fetched successfully.');
    }

    public function enrolledStudents(VirtualSession $virtualSession)
    {
        if (!$virtualSession->course_id) {
            return self::success([], 'No course linked to this session.');
        }

        $students = User::query()
            ->join('enrollments', 'users.id', '=', 'enrollments.learner_id')
            ->where('enrollments.course_id', $virtualSession->course_id)
            ->select('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get();

        return self::success($students, 'Enrolled students fetched successfully.');
    }

    public function studentIndex()
    {
        $sessions = VirtualSession::query()
            ->join('enrollments', 'virtual_sessions.course_id', '=', 'enrollments.course_id')
            ->where('enrollments.learner_id', Auth::id())
            ->whereNotIn('virtual_sessions.status', ['draft'])
            ->select('virtual_sessions.*')
            ->orderBy('virtual_sessions.starts_at', 'asc')
            ->paginate(15);

        return self::paginated($sessions, 'Your virtual sessions fetched successfully.');
    }

    public function store(StoreVirtualSessionRequest $request)
    {
        $courseId = $request->integer('course_id') ?: null;
        if (! VirtualSessionAccess::canCreateForCourse(Auth::user(), $courseId)) {
            return self::error('You can only create live sessions for your own courses.', 403);
        }

        $session = VirtualSession::query()->create($request->validated() + [
            'host_id' => Auth::id(),
            'status' => 'draft',
        ]);
        return self::success($session, 'Virtual session created successfully.', 201);
    }

    public function show(VirtualSession $virtualSession)
    {
        return self::success($virtualSession, 'Virtual session fetched successfully.');
    }

    public function update(UpdateVirtualSessionRequest $request, VirtualSession $virtualSession)
    {
        $this->authorize('update', $virtualSession);
        $courseId = $request->has('course_id') ? ($request->integer('course_id') ?: null) : $virtualSession->course_id;

        if (! VirtualSessionAccess::canCreateForCourse(Auth::user(), $courseId)) {
            return self::error('You can only attach live sessions to your own courses.', 403);
        }

        $virtualSession->update($request->validated());
        return self::success($virtualSession->fresh(), 'Virtual session updated successfully.');
    }

    public function destroy(VirtualSession $virtualSession)
    {
        $this->authorize('delete', $virtualSession);
        $virtualSession->delete();
        return self::success(null, 'Virtual session deleted successfully.');
    }

    public function publish(VirtualSession $virtualSession)
    {
        $this->authorize('update', $virtualSession);
        $published = $this->integrationService->publishSession($virtualSession);
        return self::success($published, 'Virtual session published successfully.');
    }

    public function cancel(VirtualSession $virtualSession)
    {
        $this->authorize('update', $virtualSession);
        $cancelled = $this->integrationService->cancelSession($virtualSession);
        return self::success($cancelled, 'Virtual session cancelled successfully.');
    }

    public function storeAttendance(StoreAttendanceRequest $request, VirtualSession $virtualSession)
    {
        $user = Auth::user();
        if (! $user || ! VirtualSessionAccess::canJoin($user, $virtualSession)) {
            return self::error('You are not allowed to store attendance for this session.', 403);
        }

        $requestedUserId = (int) $request->validated('user_id');
        if (! VirtualSessionAccess::canManage($user, $virtualSession) && $requestedUserId !== (int) $user->id) {
            return self::error('You can only submit your own attendance for this session.', 403);
        }

        $attendance = $this->integrationService->storeAttendance($virtualSession, $request->validated());
        return self::success($attendance, 'Session attendance stored successfully.', 201);
    }

    public function joinContext(VirtualSession $virtualSession)
    {
        $user = Auth::user();
        if (! $user || ! VirtualSessionAccess::canJoin($user, $virtualSession)) {
            return self::error('You are not allowed to join this live session.', 403);
        }

        if ($virtualSession->provider !== 'afaq_live') {
            return self::error('Join context is only available for Afaq live sessions.', 422);
        }

        $isManager = VirtualSessionAccess::canManage($user, $virtualSession);
        if (! $isManager && ($virtualSession->status ?? 'draft') !== 'published') {
            return self::error('This live session is not published yet.', 403);
        }

        $reverb = config('broadcasting.connections.reverb');
        $options = (array) data_get($reverb, 'options', []);
        $roomId = (string) data_get($virtualSession->metadata, 'room_id', "afaq-session-{$virtualSession->id}");
        $iceServers = (array) data_get(config('communicationmodule.integrations.afaq_live'), 'ice_servers', []);

        return self::success([
            'session_id' => $virtualSession->id,
            'room_id' => $roomId,
            'channel_name' => "private-afaq-live.{$virtualSession->id}",
            'signal_url' => route('communication.virtual-sessions.signal', ['virtualSession' => $virtualSession->id]),
            'broadcast_auth_url' => url('/broadcasting/auth'),
            'ice_servers' => $iceServers,
            'reverb' => [
                'key' => (string) data_get($reverb, 'key', ''),
                'host' => (string) ($options['host'] ?? request()->getHost()),
                'port' => (int) ($options['port'] ?? 8080),
                'scheme' => (string) ($options['scheme'] ?? 'http'),
                'use_tls' => (bool) ($options['useTLS'] ?? false),
            ],
        ], 'Join context fetched successfully.');
    }

    public function signal(Request $request, VirtualSession $virtualSession)
    {
        $user = Auth::user();
        if (! $user || ! VirtualSessionAccess::canJoin($user, $virtualSession)) {
            return self::error('You are not allowed to signal this session.', 403);
        }

        if ($virtualSession->provider !== 'afaq_live') {
            return self::error('Signaling is only available for Afaq live sessions.', 422);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:join,offer,answer,ice-candidate,status,chat,leave'],
            'sender_peer_id' => ['required', 'string', 'max:80'],
            'target_peer_id' => ['nullable', 'string', 'max:80'],
            'payload' => ['nullable', 'array'],
        ]);

        VirtualSessionSignalSent::dispatch($virtualSession->id, [
            'session_id' => $virtualSession->id,
            'sender_user_id' => $user->id,
            'sender_name' => $user->name ?? "User #{$user->id}",
            'type' => $validated['type'],
            'sender_peer_id' => $validated['sender_peer_id'],
            'target_peer_id' => $validated['target_peer_id'] ?? null,
            'payload' => $validated['payload'] ?? [],
            'sent_at' => now()->toIso8601String(),
        ]);

        return self::success([
            'queued' => true,
            'type' => $validated['type'],
        ], 'Signal sent successfully.');
    }

    public function webhook(Request $request, string $provider): JsonResponse
    {
        try {
            $signature = (string) $request->header('X-Signature');
            if (! $signature) {
                return self::error('Missing webhook signature.', 422);
            }

            $secret = (string) config("communicationmodule.webhooks.{$provider}.secret");
            if ($secret === '') {
                return self::error('Webhook secret not configured for provider.', 500);
            }

            $payload = $request->getContent();
            $expected = hash_hmac('sha256', $payload, $secret);
            $normalized = str_starts_with($signature, 'sha256=')
                ? substr($signature, 7)
                : $signature;

            if (! hash_equals($expected, $normalized)) {
                return self::error('Invalid webhook signature.', 401);
            }

            $payloadData = (array) $request->json()->all();
            ProcessIntegrationWebhook::dispatch($provider, $payloadData);

            return self::success([
                'provider' => $provider,
                'received' => true,
                'event' => $request->input('event'),
            ], 'Webhook payload accepted.');
        } catch (Throwable $e) {
            return self::error('Webhook processing failed.', 422, $e->getMessage());
        }
    }
}
