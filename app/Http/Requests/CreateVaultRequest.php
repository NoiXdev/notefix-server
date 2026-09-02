<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateVaultRequest extends FormRequest
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
            'kdfParams' => ['required', 'array'],
            'dekWrapped' => ['required', 'string', 'max:4096'],
            'dekCheck' => ['required', 'string', 'max:4096'],
            'recovery.recoverySalt' => ['required', 'string', 'max:256'],
            'recovery.dekWrappedRecovery' => ['required', 'string', 'max:4096'],
            'recovery.dekCheck' => ['required', 'string', 'max:4096'],
        ];
    }
}
