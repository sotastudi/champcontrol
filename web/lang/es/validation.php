<?php

return [
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'before_or_equal' => ':Attribute debe ser una fecha anterior o igual a :date.',
    'between' => ['numeric' => ':Attribute debe estar entre :min y :max.'],
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'date' => ':Attribute no es una fecha válida.',
    'email' => ':Attribute debe ser un correo electrónico válido.',
    'enum' => 'El valor seleccionado en :attribute no es válido.',
    'exists' => 'El valor seleccionado en :attribute no es válido.',
    'gte' => ['numeric' => ':Attribute debe ser mayor o igual que :value.'],
    'in' => 'El valor seleccionado en :attribute no es válido.',
    'integer' => ':Attribute debe ser un número entero.',
    'max' => [
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'string' => ':Attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'numeric' => ':Attribute debe ser al menos :min.',
        'string' => ':Attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => ':Attribute debe ser un número.',
    'password' => ['min' => ':Attribute debe tener al menos :min caracteres.'],
    'required' => 'El campo :attribute es obligatorio.',
    'string' => ':Attribute debe ser texto.',
    'unique' => ':Attribute ya está en uso.',
    'attributes' => [
        'name' => 'el nombre', 'email' => 'el correo electrónico', 'password' => 'la contraseña',
        'discipline' => 'la disciplina', 'description' => 'la descripción', 'starts_on' => 'la fecha',
        'venue' => 'el lugar', 'status' => 'el estado', 'modality' => 'la modalidad', 'sex' => 'el sexo',
        'min_age' => 'la edad mínima', 'max_age' => 'la edad máxima', 'min_weight' => 'el peso mínimo',
        'max_weight' => 'el peso máximo', 'level' => 'el nivel', 'bronze_rule' => 'la regla del bronce',
        'first_name' => 'el nombre', 'last_name' => 'los apellidos', 'club' => 'el club',
        'birth_date' => 'la fecha de nacimiento', 'weight' => 'el peso', 'phone' => 'el teléfono',
        'category_id' => 'la categoría', 'notes' => 'las notas', 'winner_id' => 'el ganador',
    ],
];
