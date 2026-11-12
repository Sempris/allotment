<?php

namespace App\Http\Requests;

use App\Support\Milli;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class ReadingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'read_on' => ['required', 'date_format:Y-m-d'],
            'value' => ['required', 'string', 'max:20'],
            'note' => [$this->routeIs('readings.correct.store') ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                try {
                    Milli::parse((string) $this->input('value'));
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('value', 'Bitte einen Zählerstand wie 1234,567 eingeben (höchstens drei Nachkommastellen).');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['read_on' => 'Ablesedatum', 'value' => 'Zählerstand', 'note' => 'Begründung'];
    }

    public function readOn(): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', $this->validated('read_on'));
    }

    public function valueMilli(): int
    {
        return Milli::parse($this->validated('value'));
    }
}
