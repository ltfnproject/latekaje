<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ActivityLogger
{
    public static function log(
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
    ): ?ActivityLog {
        try {
            if (! Schema::hasTable('activity_logs')) {
                return null;
            }

            $user = $actor ?? auth()->user();
            $req = app()->bound('request') ? request() : null;

            return ActivityLog::create([
                'user_id' => $user instanceof User ? $user->getKey() : null,
                'action' => mb_substr($action, 0, 80),
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject ? (int) $subject->getKey() : null,
                'description' => mb_substr($description, 0, 255),
                'properties' => $properties !== [] ? self::redact($properties) : null,
                'ip_address' => $req?->ip(),
                'user_agent' => $req?->userAgent() ? mb_substr((string) $req->userAgent(), 0, 255) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function redact(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/(password|secret|token|pass|pin)/i', $key)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            $sanitized[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $sanitized;
    }
}
