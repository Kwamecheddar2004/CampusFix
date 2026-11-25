<?php
// Input validation helpers for CampusFix forms.

function clean_input(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function is_valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function is_valid_phone(string $phone): bool {
    // Ghana formats: 0241234567 or +233241234567
    return preg_match('/^(\+233|0)[2-5][0-9]{8}$/', $phone) === 1;
}

function validate_fault_report(array $data): array {
    $errors = [];

    if (strlen(trim($data['title'] ?? '')) < 5) {
        $errors['title'] = 'Title must be at least 5 characters.';
    }
    if (strlen(trim($data['description'] ?? '')) < 10) {
        $errors['description'] = 'Please describe the fault in more detail.';
    }

    $allowed = ['IT', 'Electrical', 'Plumbing', 'Furniture', 'Other'];
    if (!in_array($data['category'] ?? '', $allowed, true)) {
        $errors['category'] = 'Choose a valid category.';
    }

    return $errors;
}
