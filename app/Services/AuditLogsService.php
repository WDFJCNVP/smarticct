<?php

namespace App\Services;

use \App\Models\AuditLog;

class AuditLogsService
{
    public function create($attributes)
    {
        return AuditLog::create($attributes);
    }
}
