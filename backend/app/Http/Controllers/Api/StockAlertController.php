<?php

namespace App\Http\Controllers\Api;

use App\Actions\StockEvaluator;
use App\Http\Controllers\Controller;
use App\Models\AlertReceipt;
use App\Models\AuditLog;
use App\Models\StockAlert;
use App\Models\StockRule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockAlertController extends Controller
{
    public function rules(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('alerts.manage'), 403);
        $rules = StockRule::orderBy('id')->paginate(10);

        return response()->json(['data' => $rules->items(), 'meta' => ['total' => $rules->total(), 'current_page' => $rules->currentPage(), 'last_page' => $rules->lastPage()], 'staff' => User::where('active', true)->whereHas('role', fn ($q) => $q->whereIn('name', ['Red Cross Administrator', 'Red Cross Personnel']))->get(['id', 'name'])]);
    }

    public function saveRule(Request $request, StockEvaluator $evaluator): JsonResponse
    {
        abort_unless($request->user()->hasPermission('alerts.manage'), 403);
        $input = $request->validate(['id' => ['nullable', 'integer'], 'version' => ['required', 'integer', 'min:0'], 'blood_type' => ['required', Rule::in(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'])], 'component' => ['required', 'string', 'min:2', 'max:80'], 'location' => ['required', 'string', 'min:2', 'max:100'], 'threshold' => ['required', 'integer', 'between:0,999999'], 'recipient_ids' => ['bail', 'required', 'array', 'min:1', 'max:100', 'exists:users,id'], 'recipient_ids.*' => ['required', 'integer', 'distinct'], 'active' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $input['recipient_ids'] = array_map('intval', $input['recipient_ids']);
        $recipients = User::with('role')->whereKey($input['recipient_ids'])->get();
        abort_unless($recipients->count() === count($input['recipient_ids']) && $recipients->every(fn ($recipient) => $recipient->hasPermission('inventory.read')), 422, 'Choose active inventory staff.');
        try {
            $record = DB::transaction(function () use ($request, $input, $evaluator): StockRule {
                $fields = Arr::only($input, ['blood_type', 'component', 'location', 'threshold', 'recipient_ids', 'active']);
                if (! empty($input['id'])) {
                    $record = StockRule::findOrFail($input['id']);
                    abort_unless($record->blood_type === $input['blood_type'] && $record->component === $input['component'] && $record->location === $input['location'], 422, 'A rule scope cannot be changed; disable it and create a new rule.');
                    $fields['recipient_ids'] = json_encode($input['recipient_ids'], JSON_THROW_ON_ERROR);
                    abort_if(StockRule::whereKey($record->id)->where('version', $input['version'])->update([...$fields, 'version' => DB::raw('version + 1'), 'updated_at' => now()]) !== 1, 409);
                    $record->refresh();
                } else {
                    abort_unless($input['version'] === 0, 409);
                    $record = StockRule::create([...$fields, 'version' => 1]);
                }
                AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'stock.rule.saved', 'subject_id' => (string) $record->id, 'reason' => $input['reason'], 'changed_fields' => ['stock_rule', 'recipients'], 'request_id' => $request->attributes->get('request_id')]);
                $evaluator->evaluate();

                return $record;
            });
        } catch (QueryException $exception) {
            if (empty($input['id']) && (string) $exception->getCode() === '23000' && StockRule::where('blood_type', $input['blood_type'])->where('component', $input['component'])->where('location', $input['location'])->exists()) {
                abort(409, 'This stock scope already has a rule.');
            }
            throw $exception;
        }

        return response()->json(['data' => $record]);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('inventory.read'), 403);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $rules = StockRule::where('active', true)->whereJsonContains('recipient_ids', (int) $request->user()->id)->get();
        $records = StockAlert::whereIn('stock_rule_id', $rules->pluck('id'))->orderByDesc('open')->latest('id')->paginate(10);
        $receipts = AlertReceipt::where('user_id', $request->user()->id)->whereIn('stock_alert_id', collect($records->items())->pluck('id'))->pluck('acknowledged_at', 'stock_alert_id');
        $data = collect($records->items())->map(function ($alert) use ($rules, $receipts): array {
            $rule = $rules->firstWhere('id', $alert->stock_rule_id);

            return ['id' => $alert->id, 'blood_type' => $rule->blood_type, 'component' => $rule->component, 'location' => $rule->location, 'condition' => $alert->condition, 'available_count' => $alert->available_count, 'threshold' => $rule->threshold, 'open' => $alert->open, 'resolved_at' => $alert->resolved_at, 'updated_at' => $alert->updated_at, 'acknowledged_at' => $receipts->get($alert->id)];
        });

        $unacknowledged = StockAlert::whereIn('stock_rule_id', $rules->pluck('id'))->where('open', true)->whereNotExists(function ($query) use ($request): void {
            $query->selectRaw('1')->from('alert_receipts')->whereColumn('alert_receipts.stock_alert_id', 'stock_alerts.id')->where('user_id', $request->user()->id)->whereNotNull('acknowledged_at');
        })->count();

        return response()->json(['data' => $data, 'meta' => ['total' => $records->total(), 'current_page' => $records->currentPage(), 'last_page' => $records->lastPage()], 'configured_scopes' => $rules->count(), 'unacknowledged_open_count' => $unacknowledged]);
    }

    public function acknowledge(Request $request, int $alert): JsonResponse
    {
        abort_unless($request->user()->hasPermission('inventory.read'), 403);
        $record = StockAlert::findOrFail($alert);
        $rule = StockRule::findOrFail($record->stock_rule_id);
        abort_unless($rule->active && in_array($request->user()->id, $rule->recipient_ids, true), 404);
        DB::transaction(function () use ($request, $record): void {
            AlertReceipt::updateOrCreate(['stock_alert_id' => $record->id, 'user_id' => $request->user()->id], ['acknowledged_at' => now()]);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'stock.alert.acknowledged', 'subject_id' => (string) $record->id, 'changed_fields' => ['acknowledged_at'], 'request_id' => $request->attributes->get('request_id')]);
        });

        return response()->json(['data' => ['acknowledged' => true]]);
    }

    public function reconcile(Request $request, StockEvaluator $evaluator): JsonResponse
    {
        abort_unless($request->user()->hasPermission('alerts.manage'), 403);
        $evaluator->evaluate();

        return response()->json(['data' => ['reconciled_at' => now()->toISOString()]]);
    }
}
