<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'requested_by_user_id',
        'export_name',
        'export_format',
        'filter_payload',
        'status',
        'file_path',
        'row_count',
        'file_size_bytes',
        'generated_at',
        'downloaded_at',
        'error_message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'export_format' => 'string',
            'filter_payload' => 'array',
            'status' => 'string',
            'row_count' => 'integer',
            'file_size_bytes' => 'integer',
            'generated_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}

