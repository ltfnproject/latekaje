<?php

return [
    'model_label' => 'Member',
    'model_plural' => 'Members',

    // Form
    'code_label' => 'Member ID',
    'code_placeholder' => 'e.g.: 1001 (may be empty)',
    'full_name_label' => 'Full Name',
    'name_label' => 'Name',
    'group_label' => 'Group',
    'group_placeholder' => 'e.g.: X TJKT 1',
    'member_type_label' => 'Member Category',
    'type_student' => 'Student',
    'type_teacher' => 'Teacher',
    'type_employee' => 'Employee',
    'birth_date_label' => 'Birth Date',
    'birth_date_helper' => 'Used as initial password in DDMMYYYY format (e.g. 17082008) when creating a login account.',

    // Table
    'active_label' => 'Active',
    'loans_label' => 'Loans',

    // Actions & notifications
    'create_action' => 'Add Member',
    'create_account' => 'Create Account',
    'account_created_title' => 'Login account created',
    'account_created_body' => 'ID: :code | Initial password: their ID (birth date not set). Member must change password on first login.',
    'account_created_body_dob' => 'ID: :code | Initial password: Birth date in DDMMYYYY format. Member must change password on first login.',

    // Importer columns
    'import_col_code' => 'Member ID',
    'import_col_name' => 'Name',
    'import_col_group' => 'Group',
    'import_col_member_type' => 'Category (student/teacher/employee)',
    'import_col_birth_date' => 'Birth Date (YYYY-MM-DD)',
    'import_col_password' => 'Password',
    'import_completed' => 'Member import finished: :success rows succeeded',
    'import_failed_suffix' => ', :failed rows failed',

    // Abilities
    'ability_view_any' => 'View Members',
    'ability_create' => 'Add member',
];
