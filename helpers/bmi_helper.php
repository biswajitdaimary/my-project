<?php

function bmi_validate_measurements($heightCm, $weightKg): ?string
{
    if (!is_numeric($heightCm) || !is_numeric($weightKg)) {
        return 'Please enter valid numeric values for height and weight.';
    }

    $height = (float) $heightCm;
    $weight = (float) $weightKg;

    if ($height < 50 || $height > 300) {
        return 'Height must be between 50 cm and 300 cm.';
    }

    if ($weight < 10 || $weight > 500) {
        return 'Weight must be between 10 kg and 500 kg.';
    }

    return null;
}

function bmi_validate_demographics($age, $gender): ?string
{
    if (!is_numeric($age)) {
        return 'Please provide a valid age.';
    }

    $ageValue = (int) $age;
    if ($ageValue < 5 || $ageValue > 120) {
        return 'Age must be between 5 and 120 years.';
    }

    if (!in_array($gender, ['male', 'female', 'other'], true)) {
        return 'Please select a valid gender.';
    }

    return null;
}

function bmi_format_age($age): string
{
    if ($age === null || $age === '' || !is_numeric($age)) {
        return '-';
    }

    $ageValue = (int) $age;
    return $ageValue > 0 ? $ageValue . ' yrs' : '-';
}

function bmi_format_gender($gender): string
{
    if (!is_string($gender) || $gender === '') {
        return '-';
    }

    $normalizedGender = strtolower(trim($gender));
    if (!in_array($normalizedGender, ['male', 'female', 'other'], true)) {
        return '-';
    }

    return ucfirst($normalizedGender);
}

function bmi_calculate_value(float $heightCm, float $weightKg): float
{
    $heightM = $heightCm / 100;
    return round($weightKg / ($heightM * $heightM), 1);
}

function bmi_get_meta(float $bmi): array
{
    if ($bmi < 18.5) {
        return [
            'category' => 'Underweight',
            'color_class' => 'text-info',
            'badge_class' => 'bg-info text-dark',
            'tip' => 'Focus on strength training and nutrient-dense meals to build healthy weight gradually.',
            'range' => 'Below 18.5'
        ];
    }

    if ($bmi < 25) {
        return [
            'category' => 'Normal',
            'color_class' => 'text-success',
            'badge_class' => 'bg-success',
            'tip' => 'You are in a healthy range. Keep up a balanced diet, steady sleep, and regular movement.',
            'range' => '18.5 - 24.9'
        ];
    }

    if ($bmi < 30) {
        return [
            'category' => 'Overweight',
            'color_class' => 'text-warning',
            'badge_class' => 'bg-warning text-dark',
            'tip' => 'A small calorie deficit, more daily steps, and consistent workouts can help bring BMI down safely.',
            'range' => '25.0 - 29.9'
        ];
    }

    return [
        'category' => 'Obese',
        'color_class' => 'text-danger',
        'badge_class' => 'bg-danger',
        'tip' => 'Consider working with a trainer or doctor on a structured plan for sustainable weight reduction.',
        'range' => '30.0 and above'
    ];
}
?>
