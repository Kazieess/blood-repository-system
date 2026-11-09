<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class UpdateDonorRequest extends StoreDonorRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('donors.update') ?? false;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['name', 'blood_type', 'municipality', 'version', 'reason']) as $field) {
                $validator->errors()->add($field, 'This field cannot be set by the client.');
            }
        }];
    }
}
