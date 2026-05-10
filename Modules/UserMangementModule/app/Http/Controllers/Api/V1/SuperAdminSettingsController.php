<?php

namespace Modules\UserMangementModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\UserMangementModule\Http\Requests\Api\V1\SuperAdmin\UpdateSuperAdminSettingsRequest;
use Modules\UserMangementModule\Models\SuperAdminSetting;

class SuperAdminSettingsController extends Controller
{
    public function show()
    {
        $settings = SuperAdminSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'default_language' => 'ar',
                'notifications' => [
                    'in_app' => true,
                    'email' => false,
                    'digest' => false,
                ],
                'integrations' => [
                    'zoom_enabled' => true,
                    'google_classroom_enabled' => true,
                    'webhook_secret_rotation_days' => 90,
                ],
            ]
        );

        return self::success($settings, 'Super-admin settings fetched successfully.');
    }

    public function update(UpdateSuperAdminSettingsRequest $request)
    {
        $settings = SuperAdminSetting::query()->firstOrCreate(['id' => 1], [
            'default_language' => 'ar',
            'notifications' => [],
            'integrations' => [],
        ]);

        $data = $request->validated();

        if (array_key_exists('notifications', $data)) {
            $data['notifications'] = array_merge((array) $settings->notifications, (array) $data['notifications']);
        }

        if (array_key_exists('integrations', $data)) {
            $data['integrations'] = array_merge((array) $settings->integrations, (array) $data['integrations']);
        }

        $settings->update($data);

        return self::success($settings->fresh(), 'Super-admin settings updated successfully.');
    }
}
