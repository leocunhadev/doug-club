<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'number',
        'title',
        'duration_seconds',
        'video_provider',
        'video_id',
        'thumbnail_path',
        'published_at',
        'position',
    ];

    protected $casts = [
        'published_at' => 'date',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    protected function embedUrl(): Attribute
    {
        return Attribute::get(fn () => match ($this->video_provider) {
            'vimeo' => "https://player.vimeo.com/video/{$this->video_id}",
            'youtube' => "https://www.youtube-nocookie.com/embed/{$this->video_id}",
            default => throw new \InvalidArgumentException("Unsupported video provider: {$this->video_provider}"),
        });
    }

    protected function durationFormatted(): Attribute
    {
        return Attribute::get(function () {
            if ($this->duration_seconds === null) {
                return null;
            }

            $hours = intdiv($this->duration_seconds, 3600);
            $minutes = intdiv($this->duration_seconds % 3600, 60);
            $seconds = $this->duration_seconds % 60;

            return $hours > 0
                ? sprintf('%dh %02dmin', $hours, $minutes)
                : sprintf('%d:%02d', $minutes, $seconds);
        });
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(function () {
            if (filled($this->thumbnail_path)) {
                return Str::startsWith($this->thumbnail_path, ['http://', 'https://'])
                    ? $this->thumbnail_path
                    : Storage::disk('public')->url($this->thumbnail_path);
            }

            return match ($this->video_provider) {
                'youtube' => "https://img.youtube.com/vi/{$this->video_id}/hqdefault.jpg",
                default => null,
            };
        });
    }
}
