<?php

namespace Modules\CommunicationModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Modules\AssesmentModule\Models\Attempt;
use Modules\AssesmentModule\Models\CourseCertificate;
use Modules\CommunicationModule\Http\Requests\Notification\StoreNotificationRequest;
use Modules\CommunicationModule\Services\V1\NotificationService;
use Modules\LearningModule\Models\Course;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService)
    {
        $this->middleware('permission:create-notification')->only([
            'store',
            'triggerDigest',
            'notifyAssessmentResult',
            'notifyCertificateIssued',
            'notifyCourseNewContent',
        ]);
    }

    public function index()
    {
        $query = DatabaseNotification::query()
            ->where('notifiable_id', Auth::id())
            ->orderByDesc('created_at');

        if (request()->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        return self::paginated($query->paginate(20), 'Notifications fetched successfully.');
    }

    public function unreadCount()
    {
        $count = DatabaseNotification::query()
            ->where('notifiable_id', Auth::id())
            ->whereNull('read_at')
            ->count();

        return self::success(['unread_count' => $count], 'Unread notifications count fetched successfully.');
    }

    public function store(StoreNotificationRequest $request)
    {
        $payload = $request->validated();
        $user = Auth::user();
        $isBroadcastTarget = ($payload['all_users'] ?? false) || !empty($payload['role_names']);
        $canBroadcastToAllRoles = $user && $user->hasAnyRole(['admin', 'super-admin']);
        $isAuditorTargetingOnlySuperAdmin = $user
            && $user->hasRole('auditor')
            && empty($payload['all_users'])
            && !empty($payload['role_names'])
            && collect($payload['role_names'])->every(fn ($role) => $role === 'super-admin');

        if ($isBroadcastTarget && !$canBroadcastToAllRoles && !$isAuditorTargetingOnlySuperAdmin) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Only admin/super-admin can broadcast by roles, except auditor can target super-admin only.',
                ], 403)
            );
        }

        $count = $this->notificationService->sendToUsers($payload);
        return self::success(['sent' => $count], 'Notifications dispatched successfully.');
    }

    public function markRead(string $notificationId)
    {
        $notification = DatabaseNotification::query()
            ->where('id', $notificationId)
            ->where('notifiable_id', Auth::id())
            ->firstOrFail();
        $notification->markAsRead();
        return self::success($notification->fresh(), 'Notification marked as read.');
    }

    public function markAllRead()
    {
        $updated = $this->notificationService->markAllReadForUser(Auth::id());
        return self::success(['updated' => $updated], 'All notifications marked as read.');
    }

    public function triggerDigest()
    {
        return self::success(['triggered' => true], 'Digest trigger accepted.');
    }

    public function notifyAssessmentResult()
    {
        $data = request()->validate([
            'attempt_id' => ['required', 'integer', 'exists:attempts,id'],
        ]);

        $attempt = Attempt::query()->findOrFail((int) $data['attempt_id']);
        $sent = $this->notificationService->sendAssessmentResultNotification($attempt);

        return self::success(['sent' => $sent], 'Assessment result notification processed.');
    }

    public function notifyCertificateIssued()
    {
        $data = request()->validate([
            'certificate_id' => ['required', 'integer', 'exists:course_certificates,id'],
        ]);

        $certificate = CourseCertificate::query()->findOrFail((int) $data['certificate_id']);
        $sent = $this->notificationService->sendCertificateIssuedNotification($certificate);

        return self::success(['sent' => $sent], 'Certificate issued notification processed.');
    }

    public function notifyCourseNewContent()
    {
        $data = request()->validate([
            'course_id' => ['required'],
        ]);

        $course = Course::query()
            ->where('course_id', $data['course_id'])
            ->orWhere('slug', $data['course_id'])
            ->firstOrFail();

        $sent = $this->notificationService->sendNewCourseContentNotification($course);

        return self::success(['sent' => $sent], 'Course new content notification processed.');
    }
}
