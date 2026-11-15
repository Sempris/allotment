<?php

namespace App\Http\Requests;

use App\Support\Milli;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class MeterUpdateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'max:40', Rule::unique('meters', 'serial_number')->ignore($this->route('meter'))],
            'installed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'initial_value' => ['required', 'string', 'max:20'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                try {
                    Milli::parse((string) $this->input('initial_value'));
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('initial_value', 'Bitte einen Anfangsstand wie 0 oder 12,5 eingeben.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['serial_number' => 'Zählernummer', 'installed_on' => 'Einbaudatum', 'initial_value' => 'Anfangsstand'];
    }

    public function installedOn(): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', $this->validated('installed_on'));
    }

    public function initialValueMilli(): int
    {
        return Milli::parse($this->validated('initial_value'));
    }
}
