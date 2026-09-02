<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RotateVaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'generation' => ['required', 'integer', 'min:2'],
            'keys' => ['required', 'array', 'min:1'],
            'keys.*.userId' => ['required', 'integer'],
            'keys.*.kdfParams' => ['required', 'array'],
            'keys.*.dekWrapped' => ['required', 'string', 'max:4096'],
            'keys.*.dekCheck' => ['required', 'string', 'max:4096'],
            'recovery' => ['nullable', 'array'],
            'recovery.recoverySalt' => ['required_with:recovery', 'string', 'max:256'],
            'recovery.dekWrappedRecovery' => ['required_with:recovery', 'string', 'max:4096'],
            'recovery.dekCheck' => ['required_with:recovery', 'string', 'max:4096'],
        ];
    }
}
