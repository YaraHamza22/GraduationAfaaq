<?php

namespace Modules\CommunicationModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\CommunicationModule\Database\Factories\OfflineSyncLogFactory;

class OfflineSyncLog extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'offline_package_id', 'device_id', 'client_event_id', 'action', 'payload', 'created_at'];
    protected $casts = ['payload' => 'array', 'created_at' => 'datetime'];
}
