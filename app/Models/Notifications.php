<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Notifications extends Model
{
    use HasUuids;

    protected $table = 'push_notifications';
    protected $primaryKey = 'notification_id';

    protected $fillable = [
        'notification_id',
        'title',
        'body',
        'data',
        'audience',
        'user_id',
        'created_by',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
    ];

    public $timestamps = true;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (!Schema::hasTable('push_notifications') && Schema::hasTable('notifications')) {
            $this->setTable('notifications');
        }
    }
}
