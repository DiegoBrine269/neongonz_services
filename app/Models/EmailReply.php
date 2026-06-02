<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailReply extends Model
{
    protected $fillable = [
        'email_id',
        'from',
        'body',
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }
}
