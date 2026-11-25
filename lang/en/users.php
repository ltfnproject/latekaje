<?php

return [
    'model_label' => 'User',
    'model_plural' => 'Users',

    // Form
    'name_label' => 'Name',
    'email_label' => 'Email',
    'code_label' => 'Member ID',
    'code_placeholder' => 'Member accounts only',
    'role_label' => 'Role',
    'role_superadmin' => 'Super Admin (IT Team)',
    'role_admin' => 'Head of Lab (KKK)',
    'role_staff' => 'Lab Technician (Toolman)',
    'role_assistant' => 'Lab Admin (Intern)',
    'role_users' => 'Borrower (Student/Teacher/Staff)',
    'account_active_label' => 'Account active',
    'account_active_helper' => 'Turn off to block login without deleting the account.',
    'must_change_password_label' => 'Force password change on next login',
    'must_change_password_helper' => 'User will be redirected to Profile before accessing other pages.',
    'must_change_password_title' => 'Password change required',
    'must_change_password_body' => 'For account security, please change your initial password before continuing.',
    'perm_allow_label' => 'Extra rights: ALLOW (beyond role)',
    'perm_deny_label' => 'Extra rights: DENY (even if role allows)',
    'new_password_label' => 'New Password',
    'password_helper_edit' => 'Leave empty to keep unchanged.',

    // Table
    'member_data_label' => 'Member Data',
    'created_label' => 'Created',

    // Toggle-active action
    'activate' => 'Activate',
    'deactivate' => 'Deactivate',
    'activated_title' => 'Account activated',
    'deactivated_title' => 'Account deactivated + sessions kicked',

    // Pages
    'create_action' => 'Add User',

    // Abilities
    'ability_view_any' => 'Open Manage Users',
    'ability_create' => 'Create user',
    'ability_update' => 'Update user / password',
    'ability_delete' => 'Delete user',
];
