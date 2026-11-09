<?php

namespace App\Actions;

use App\Models\AlertReceipt;
use App\Models\AuditLog;
use App\Models\BloodUnit;
use App\Models\StockAlert;
use App\Models\StockRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockEvaluator
{
    public function evaluate(): void
    {
        StockRule::orderBy('id')->each(function ($item): void {
            DB::transaction(function () use ($item): void {
                $rule = StockRule::whereKey($item->id)->lockForUpdate()->firstOrFail();
                StockRule::whereKey($rule->id)->update(['updated_at' => now()]);
                $count = BloodUnit::available()->where('blood_type', $rule->blood_type)->where('component', $rule->component)->where('location', $rule->location)->count();
                $condition = $count === 0 ? 'out_of_stock' : 'low_stock';
                $open = StockAlert::where('open_rule_id', $rule->id)->first();
                if (! $rule->active || $count > $rule->threshold) {
                    if ($open) {
                        $open->update(['open' => false, 'open_rule_id' => null, 'resolved_at' => now(), 'available_count' => $count]);
                        $this->audit('stock.alert.resolved', $open->id);
                    }

                    return;
                }
                if (! $open) {
                    $open = StockAlert::create(['stock_rule_id' => $rule->id, 'open_rule_id' => $rule->id, 'condition' => $condition, 'available_count' => $count, 'open' => true]);
                    $this->audit('stock.alert.opened', $open->id);
                } else {
                    if ($open->condition !== $condition) {
                        AlertReceipt::where('stock_alert_id', $open->id)->update(['acknowledged_at' => null]);
                    }
                    $open->update(['condition' => $condition, 'available_count' => $count]);
                }
                foreach ($rule->recipient_ids as $recipient) {
                    AlertReceipt::firstOrCreate(['stock_alert_id' => $open->id, 'user_id' => $recipient]);
                }
            });
        });
    }

    private function audit(string $action, int $id): void
    {
        AuditLog::create(['actor_id' => null, 'action' => $action, 'subject_id' => (string) $id, 'changed_fields' => ['stock_condition'], 'request_id' => (string) Str::uuid()]);
    }
}
