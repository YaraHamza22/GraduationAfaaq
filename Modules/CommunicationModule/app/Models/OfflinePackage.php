<?php

namespace Modules\CommunicationModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
// use Modules\CommunicationModule\Database\Factories\OfflinePackageFactory;

class OfflinePackage extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['course_id', 'created_by', 'version', 'manifest', 'file_url', 'is_active'];
    protected $casts = ['manifest' => 'array', 'is_active' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['course_id', 'created_by', 'version', 'file_url', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('offline_package')
            ->setDescriptionForEvent(fn(string $e) => "OfflinePackage was {$e}");
    }
}
