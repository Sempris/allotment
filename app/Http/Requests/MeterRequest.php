<?php

namespace App\Http\Requests;

use App\Enums\MeterType;
use App\Support\Milli;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class MeterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MeterType::class)],
            'serial_number' => ['required', 'string', 'max:40', 'unique:meters,serial_number'],
            'installed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'initial_value' => ['required', 'string', 'max:20'],
            'replaces_meter_id' => ['nullable', 'integer', Rule::exists('meters', 'id')->where('plot_id', $this->route('plot')->id)->whereNull('removed_on')],
            'final_value' => ['nullable', 'string', 'max:20'],
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

                try {
                    if ($this->finalValueMilli() !== null && ! $this->filled('replaces_meter_id')) {
                        $validator->errors()->add('final_value', 'Ein Endstand ist nur beim Zählertausch möglich. Bitte den ersetzten Zähler auswählen.');
                    }
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('final_value', 'Bitte einen Endstand wie 1234,567 eingeben (höchstens drei Nachkommastellen).');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type' => 'Zählerart', 'serial_number' => 'Zählernummer', 'installed_on' => 'Einbaudatum',
            'initial_value' => 'Anfangsstand', 'replaces_meter_id' => 'ersetzter Zähler', 'final_value' => 'Endstand',
        ];
    }

    /** Final value of the replaced meter, or null if the field was left empty. */
    public function finalValueMilli(): ?int
    {
        $input = trim((string) $this->input('final_value'));

        return $input === '' ? null : Milli::parse($input);
    }
}
