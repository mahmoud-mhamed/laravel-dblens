<?php

namespace MahmoudMhamed\DbLens\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddForeignKeyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'column' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'foreign_table' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'foreign_column' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'on_update' => ['nullable', 'in:CASCADE,SET NULL,NO ACTION,RESTRICT'],
            'on_delete' => ['nullable', 'in:CASCADE,SET NULL,NO ACTION,RESTRICT'],
        ];
    }
}
