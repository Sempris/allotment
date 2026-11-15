<?php

namespace App\Http\Requests;

use App\Support\Milli;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class MeterRetireRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'removed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'final_value' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                try {
                    $this->finalValueMilli();
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('final_value', 'Bitte einen Endstand wie 1234,567 eingeben (höchstens drei Nachkommastellen).');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['removed_on' => 'Ausbaudatum', 'final_value' => 'Endstand'];
    }

    public function removedOn(): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', $this->validated('removed_on'));
    }

    /** Null if the field was left empty (allowed when the meter was already read on that day). */
    public function finalValueMilli(): ?int
    {
        $input = trim((string) $this->input('final_value'));

        return $input === '' ? null : Milli::parse($input);
    }
}
