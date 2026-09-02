<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PutRecoveryWrapRequest extends FormRequest
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
            'generation' => ['required', 'integer', 'min:1'],
            'recoverySalt' => ['required', 'string', 'max:256'],
            'dekWrappedRecovery' => ['required', 'string', 'max:4096'],
            'dekCheck' => ['required', 'string', 'max:4096'],
        ];
    }
}
