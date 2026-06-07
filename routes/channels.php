<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\CommunicationModule\Models\VirtualSession;
use Modules\CommunicationModule\Support\VirtualSessionAccess;
use Modules\UserMangementModule\Models\User;

Broadcast::routes([
    'middleware' => ['auth:api'],
]);

Broadcast::channel('afaq-live.{sessionId}', function (User $user, int $sessionId): array|bool {
    $session = VirtualSession::query()->find($sessionId);

    if (! $session || ! VirtualSessionAccess::canJoin($user, $session)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name ?? "User #{$user->id}",
    ];
});
