<?php

namespace Modules\UserMangementModule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperAdminSetting extends Model
{
    use HasFactory;

    protected $table = 'super_admin_settings';

    protected $fillable = [
        'default_language',
        'notifications',
        'integrations',
    ];

    protected $casts = [
        'notifications' => 'array',
        'integrations' => 'array',
    ];
}
