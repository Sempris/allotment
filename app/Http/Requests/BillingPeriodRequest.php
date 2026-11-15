<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class BillingPeriodRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'key_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->keyDate()->year !== $this->integer('year')) {
                    $validator->errors()->add('key_date', "Der Stichtag muss im Abrechnungsjahr {$this->integer('year')} liegen.");
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['year' => 'Jahr', 'key_date' => 'Stichtag'];
    }

    public function keyDate(): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', (string) $this->input('key_date'));
    }
}
