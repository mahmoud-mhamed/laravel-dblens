<?php

namespace MahmoudMhamed\DbLens\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTableRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*.name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'columns.*.type' => ['required', 'string', 'max:128'],
            'columns.*.nullable' => ['sometimes', 'boolean'],
            'columns.*.default' => ['nullable', 'string', 'max:255'],
            'columns.*.primary' => ['sometimes', 'boolean'],
            'columns.*.auto_increment' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Returns the normalised column definitions ready for TableEditor::create().
     *
     * @return array<int,array{name:string,type:string,nullable:bool,default:?string,primary:bool,auto_increment:bool}>
     */
    public function columnDefinitions(): array
    {
        $out = [];
        foreach ((array) $this->input('columns', []) as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            $type = trim((string) ($c['type'] ?? ''));
            if ($name === '' || $type === '') continue;
            $out[] = [
                'name' => $name,
                'type' => $type,
                'nullable' => ! empty($c['nullable']),
                'default' => $c['default'] ?? null,
                'primary' => ! empty($c['primary']),
                'auto_increment' => ! empty($c['auto_increment']),
            ];
        }
        return $out;
    }
}
