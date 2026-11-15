<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlotRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:20', Rule::unique('plots', 'number')->ignore($this->route('plot'))],
            'tenant_name' => ['required', 'string', 'max:120'],
            'tenant_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['number' => 'Parzellennummer', 'tenant_name' => 'Pächter/in', 'tenant_email' => 'E-Mail'];
    }
}
