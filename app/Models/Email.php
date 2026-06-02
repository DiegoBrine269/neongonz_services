<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Email extends Model
{
    protected $fillable = [
        'message_id',
        'recipient',
        'subject',
        'emailable_type',
        'emailable_id',
    ];

    public function emailable(): MorphTo
    {
        return $this->morphTo();
    }

    public function replies(): HasMany
    {
        return $this->hasMany(EmailReply::class);
    }
}
