<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'mentor_id',
        'title',
        'description',
        'file_url',
        'file_path',
        'opened_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function hasUploadedFile(): bool
    {
        return filled($this->file_path);
    }

    public function isNew(): bool
    {
        return blank($this->opened_at);
    }

    protected function iconLabel(): Attribute
    {
        return Attribute::get(function () {
            $path = $this->hasUploadedFile() ? $this->file_path : $this->file_url;
            $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));

            return match (true) {
                $extension === 'pdf' => 'PDF',
                in_array($extension, ['mp4', 'mov', 'webm'], true) => 'VÍDEO',
                in_array($extension, ['xlsx', 'xls'], true) => 'XLSX',
                in_array($extension, ['doc', 'docx'], true) => 'DOC',
                ! $this->hasUploadedFile() && filled($this->file_url) => 'LINK',
                default => 'ARQUIVO',
            };
        });
    }
}
