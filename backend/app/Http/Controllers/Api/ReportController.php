<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BloodUnit;
use App\Models\Donation;
use App\Models\Donor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    private function query(Request $request, string $kind): Builder
    {
        abort_unless(in_array($kind, ['inventory', 'expiry', 'donations'], true), 404);
        abort_unless($request->user()->hasPermission($kind === 'donations' ? 'donors.read' : 'reports.read'), 403);
        $input = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])], 'blood_type' => ['nullable', Rule::in(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'])], 'location' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        if ($kind === 'donations') {
            return Donation::whereIn('donor_id', Donor::visibleTo($request->user())->select('id'))
                ->when($input['from'] ?? null, fn ($q, $v) => $q->where('donated_on', '>=', $v))
                ->when($input['to'] ?? null, fn ($q, $v) => $q->where('donated_on', '<=', $v))
                ->when($input['location'] ?? null, fn ($q, $v) => $q->where('location', $v))
                ->select('donated_on', 'status')->selectRaw('COUNT(*) as units')->groupBy('donated_on', 'status')->orderBy('donated_on')->orderBy('status');
        }
        $query = BloodUnit::query()->when($input['blood_type'] ?? null, fn ($q, $v) => $q->where('blood_type', $v))->when($input['location'] ?? null, fn ($q, $v) => $q->where('location', $v));
        if ($kind === 'expiry') {
            $query->whereNotIn('status', ['released', 'discarded']);
        }
        $column = $kind === 'expiry' ? 'expires_at' : 'collected_at';
        if (! empty($input['from'])) {
            $query->where($column, '>=', CarbonImmutable::parse($input['from'], 'Asia/Manila')->startOfDay()->utc());
        }
        if (! empty($input['to'])) {
            $query->where($column, '<', CarbonImmutable::parse($input['to'], 'Asia/Manila')->addDay()->startOfDay()->utc());
        }
        $state = "CASE WHEN status IN ('released', 'discarded') THEN status WHEN expires_at <= ? THEN 'expired' WHEN status = 'available' AND (donation_id IS NULL OR verified_at IS NULL OR NOT EXISTS (SELECT 1 FROM donations WHERE donations.id = blood_units.donation_id AND donations.status = 'completed')) THEN 'unverified' ELSE status END";

        return $query->select('blood_type', 'component', 'location')->selectRaw($state.' as stock_state', [now()])->selectRaw('COUNT(*) as units')->groupBy('blood_type', 'component', 'location', 'stock_state')->orderBy('blood_type')->orderBy('component')->orderBy('location')->orderBy('stock_state');
    }

    public function index(Request $request, string $kind): JsonResponse
    {
        $rows = $this->query($request, $kind)->paginate(25);

        return response()->json(['data' => $rows->items(), 'meta' => ['total' => $rows->total(), 'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage()], 'generated_at' => now()->toISOString(), 'scope' => $kind === 'donations' ? 'Assigned donor records only' : 'Inventory aggregates; no donor or clinical details']);
    }

    public function export(Request $request, string $kind): JsonResponse
    {
        $rows = $this->query($request, $kind)->limit(5001)->get()->map->toArray();
        abort_if($rows->count() > 5000, 422, 'Narrow the export filters.');
        $headers = $kind === 'donations' ? ['donated_on', 'status', 'units'] : ['blood_type', 'component', 'location', 'stock_state', 'units'];
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(function ($key) use ($row): string {
                $value = (string) $row[$key];

                return preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
            }, $headers), ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'report.'.$kind.'.exported', 'changed_fields' => $headers, 'request_id' => $request->attributes->get('request_id')]);

        return response()->json(['csv' => $csv, 'filename' => $kind.'-report.csv', 'row_count' => $rows->count()]);
    }
}
