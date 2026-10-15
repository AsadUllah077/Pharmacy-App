<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Services\BranchAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RefreshInventoryAlerts implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(): void
    {
        foreach (Branch::cursor() as $branch) {
            DB::transaction(function () use ($branch) {
                BranchAccess::lock($branch->id);
                $today = now(config('pharmacy.timezone'))->toDateString();
                $until = now(config('pharmacy.timezone'))->addDays(config('pharmacy.expiry_warning_days'))->toDateString();
                DB::table('operational_alerts')->where('branch_id', $branch->id)->update(['resolved' => true, 'updated_at' => now()]);
                foreach (MedicineBatch::with('medicine')->where('branch_id', $branch->id)->where('quantity', '>', 0)->whereNotNull('expires_at')->where('expires_at', '<=', $until)->cursor() as $batch) {
                    $expired = $batch->expires_at->toDateString() <= $today;
                    $this->alert($branch->id, 'batch:'.$batch->id, ($expired ? 'Expired: ' : 'Near expiry: ').$batch->medicine->name.' · lot '.$batch->lot_number.' · '.$batch->expires_at->toDateString());
                }
                foreach (Medicine::where('active', true)->withSum(['batches as available' => fn ($q) => $q->sellable()->where('branch_id', $branch->id)], 'quantity')->cursor() as $medicine) {
                    if ((int) $medicine->available <= $medicine->reorder_level) {
                        $this->alert($branch->id, 'low:'.$branch->id.':'.$medicine->id, 'Low stock: '.$medicine->name.' · '.(int) $medicine->available.' units');
                    }
                }
            }, 3);
        }
    }

    private function alert(int $branch, string $key, string $message): void
    {
        DB::table('operational_alerts')->upsert([['branch_id' => $branch, 'key' => $key, 'message' => $message, 'resolved' => false, 'created_at' => now(), 'updated_at' => now()]], ['key'], ['message', 'resolved', 'updated_at']);
    }
}
