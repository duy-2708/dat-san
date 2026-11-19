<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AnhSan extends Model
{
    protected $table = 'anh_san';

    protected $fillable = ['duong_dan', 'thu_tu'];

    public function san(): BelongsTo
    {
        return $this->belongsTo(San::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->duong_dan);
    }
}
