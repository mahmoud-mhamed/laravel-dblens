<?php

namespace MahmoudMhamed\DbLens\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModifyColumnRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:128'],
            'nullable' => ['sometimes', 'boolean'],
            'default' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function columnDefinition(): array
    {
        return [
            'type' => $this->input('type'),
            'nullable' => $this->boolean('nullable'),
            'default' => $this->input('default'),
            'comment' => $this->input('comment'),
        ];
    }
}
