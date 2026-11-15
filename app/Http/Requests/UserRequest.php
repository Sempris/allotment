<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isNew = $this->route('user') === null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['required', Rule::enum(Role::class)],
            'plot_id' => ['nullable', 'required_if:role,'.Role::Tenant->value, 'integer', Rule::exists('plots', 'id')],
            'active' => ['boolean'],
            // On edit, an empty field keeps the current password.
            'password' => [$isNew ? 'required' : 'nullable', 'string', Password::min(10)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['plot_id.required_if' => 'Für Pächter bitte die Parzelle auswählen.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'Name', 'email' => 'E-Mail', 'role' => 'Rolle', 'plot_id' => 'Parzelle', 'active' => 'Zugang aktiv', 'password' => 'Passwort'];
    }

    /**
     * @return array{name: string, email: string, role: Role, plot_id: int|null, active: bool, password: string|null}
     */
    public function userData(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => Role::from($data['role']),
            'plot_id' => isset($data['plot_id']) ? (int) $data['plot_id'] : null,
            'active' => $this->boolean('active'),
            'password' => $data['password'] ?? null,
        ];
    }
}
