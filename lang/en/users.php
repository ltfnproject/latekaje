<?php

return [
    'model_label' => 'User',
    'model_plural' => 'Users',

    // Form
    'section_account' => 'Login Account & Role',
    'section_borrower_profile' => 'Borrower Profile (Student / Teacher / Employee)',
    'section_borrower_profile_desc' => 'Automatically synced with Master Members so borrower category and group are always complete.',
    'section_acl' => 'Custom Permissions (ACL)',
    'link_member_label' => 'Link Existing Member (optional)',
    'link_member_placeholder' => 'Leave empty to automatically create a new Member record',
    'link_member_helper' => 'If already registered in Master Members, pick here to auto-fill profile fields.',
    'name_label' => 'Name',
    'email_label' => 'Email',
    'code_label' => 'Member ID / NIS / NIP',
    'code_placeholder' => 'e.g.: 1001 (student NIS or staff NIP)',
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
    'password_helper_borrower_create' => 'May be left empty if Birth Date or Member ID is filled — automatically uses DDMMYYYY format (must change on first login).',

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
