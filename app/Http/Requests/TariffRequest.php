<?php

namespace App\Http\Requests;

use App\Enums\MeterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TariffRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'meter_type' => ['required', Rule::enum(MeterType::class)],
            'price_per_unit' => ['required', 'regex:/^\d{1,4}([,.]\d{1,2})?$/'],
            'base_fee' => ['required', 'regex:/^\d{1,4}([,.]\d{1,2})?$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'price_per_unit.regex' => 'Bitte einen Preis wie 2,35 eingeben.',
            'base_fee.regex' => 'Bitte eine Grundgebühr wie 12,00 eingeben.',
        ];
    }

    public static function toCents(string $euro): int
    {
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $euro), 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }
}
