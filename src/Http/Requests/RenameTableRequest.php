<?php

namespace MahmoudMhamed\DbLens\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenameTableRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
        ];
    }
}
