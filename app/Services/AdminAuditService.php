<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\Business;
use Illuminate\Database\Eloquent\Model;

class AdminAuditService
{
    public function record(
        string $action,
        ?Business $business = null,
        ?Model $subject = null,
        array $metadata = []
    ): AdminAuditLog {
        $user = auth()->user();

        return AdminAuditLog::create([
            'admin_user_id' =>
                $user?->id,

            'business_id' =>
                $business?->id,

            'action' =>
                $action,

            'subject_type' =>
                $subject?->getMorphClass(),

            'subject_id' =>
                $subject?->getKey(),

            'metadata' =>
                $metadata ?: null,

            'ip_address' =>
                request()?->ip(),

            'user_agent' =>
                request()?->userAgent(),
        ]);
    }
}
