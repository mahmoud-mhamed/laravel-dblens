<?php

namespace MahmoudMhamed\DbLens\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'unique' => ['sometimes', 'boolean'],
        ];
    }
}
