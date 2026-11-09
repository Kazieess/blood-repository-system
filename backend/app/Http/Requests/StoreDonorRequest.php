<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDonorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('donors.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'blood_type' => ['required', Rule::in(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'])],
            'municipality' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! Str::isUuid($this->header('Idempotency-Key', ''))) {
                $validator->errors()->add('idempotency_key', 'A UUID Idempotency-Key header is required.');
            }

            foreach (array_diff(array_keys($this->all()), ['name', 'blood_type', 'municipality']) as $field) {
                $validator->errors()->add($field, 'This field cannot be set by the client.');
            }
        }];
    }
}
