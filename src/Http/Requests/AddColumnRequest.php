<?php

namespace MahmoudMhamed\DbLens\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddColumnRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'type' => ['required', 'string', 'max:128'],
            'nullable' => ['sometimes', 'boolean'],
            'default' => ['nullable', 'string', 'max:255'],
            'after' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'comment' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function columnDefinition(): array
    {
        return [
            'type' => $this->input('type'),
            'nullable' => $this->boolean('nullable'),
            'default' => $this->input('default'),
            'after' => $this->input('after'),
            'comment' => $this->input('comment'),
        ];
    }
}
