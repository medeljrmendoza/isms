<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'task_no',
        'category',
        'reference_tag',
        'due_date',
        'priority',
        'task_status',
        'task_type',
        'created_by',
        'is_approved',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_approved' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
