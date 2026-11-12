<?php

// Only the rules this app uses. Attribute names come from the Form Requests.
return [
    'before_or_equal' => ':attribute darf nicht nach dem :date liegen.',
    'between' => ['numeric' => ':attribute muss zwischen :min und :max liegen.'],
    'date_format' => ':attribute muss ein gültiges Datum sein.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'enum' => 'Die Auswahl für :attribute ist ungültig.',
    'exists' => 'Die Auswahl für :attribute ist ungültig.',
    'file' => ':attribute muss eine Datei sein.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'max' => [
        'file' => ':attribute darf höchstens :max Kilobyte groß sein.',
        'string' => ':attribute darf höchstens :max Zeichen haben.',
    ],
    'mimes' => ':attribute muss eine Datei vom Typ :values sein.',
    'regex' => 'Das Format von :attribute ist ungültig.',
    'required' => ':attribute ist ein Pflichtfeld.',
    'string' => ':attribute muss ein Text sein.',
    'unique' => ':attribute ist bereits vergeben.',
    'attributes' => [],
];
