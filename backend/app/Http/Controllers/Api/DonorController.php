<?php

namespace App\Http\Controllers\Api;

use App\Actions\DonorWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDonorRequest;
use App\Http\Requests\UpdateDonorRequest;
use App\Http\Resources\DonorResource;
use App\Models\Donor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DonorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Donor::class);
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'blood_type' => ['nullable', Rule::in(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'])],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Donor::visibleTo($request->user());

        if (! empty($input['q'])) {
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $input['q']);
            $query->where(function ($query) use ($term): void {
                $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$term.'%'])
                    ->orWhereRaw("municipality LIKE ? ESCAPE '!'", ['%'.$term.'%'])
                    ->orWhere('id', $term);
            });
        }

        if (! empty($input['blood_type'])) {
            $query->where('blood_type', $input['blood_type']);
        }

        return DonorResource::collection($query->withCount(['donations as completed_donation_count' => fn ($q) => $q->where('status', 'completed')])->with(['assignments' => fn ($q) => $q->where('user_id', $request->user()->id)->where('active', true)])->orderByDesc('created_at')->orderBy('id')->paginate($input['per_page'] ?? 10)->withQueryString());
    }

    public function show(Request $request, string $donor): DonorResource
    {
        Gate::authorize('viewAny', Donor::class);
        $record = Donor::visibleTo($request->user())->findOrFail($donor);
        Gate::authorize('view', $record);

        return new DonorResource($record);
    }

    public function store(StoreDonorRequest $request, DonorWriter $writer): JsonResponse
    {
        Gate::authorize('create', Donor::class);
        [$donor, $created] = $writer->create($request->user(), $request->validated(), $request->header('Idempotency-Key'), $request->attributes->get('request_id'));

        return (new DonorResource($donor))->response()->setStatusCode($created ? 201 : 200);
    }

    public function update(UpdateDonorRequest $request, string $donor, DonorWriter $writer): DonorResource
    {
        $record = Donor::visibleTo($request->user())->findOrFail($donor);
        Gate::authorize('update', $record);

        return new DonorResource($writer->update($request->user(), $record, $request->validated(), $request->attributes->get('request_id')));
    }
}
