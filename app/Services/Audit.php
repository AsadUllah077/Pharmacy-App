<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Audit
{
    public static function record(User $user, ?int $branch, string $action, string $subject, array $metadata = []): void
    {
        DB::table('audit_logs')->insert(['user_id' => $user->id, 'branch_id' => $branch, 'action' => $action, 'subject' => $subject, 'metadata' => json_encode($metadata), 'created_at' => now()]);
    }
}
