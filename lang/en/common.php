<?php

return [
    // Shared actions
    'edit' => 'Edit',
    'delete' => 'Delete',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'yes' => 'Yes',
    'no' => 'No',
    'import' => 'Import',
    'template_csv' => 'CSV Template',

    // Generic words
    'status' => 'Status',
    'active' => 'Active',
    'all' => 'All',
    'theme_light' => 'Light Mode',
    'theme_dark' => 'Dark Mode',
    'switch_light_mode' => 'Switch to light mode',
    'switch_dark_mode' => 'Switch to dark mode',

    // Navigation
    'nav_dashboard' => 'Dashboard',
    'nav_units' => 'Unit Data/QR',
    'nav_assets' => 'Assets',
    'nav_loans' => 'Loans',
    'nav_users' => 'Manage Users',
    'nav_locations' => 'Locations',
    'nav_classes' => 'Classes',
    'nav_students' => 'Students',
    'nav_settings' => 'Settings',
    'nav_about' => 'About App',
    'nav_location_data' => 'Data per Location',
    'nav_activity_logs' => 'Activity Logs',
    'nav_operational_logs' => 'Operational Logs',
    'nav_my_activity_logs' => 'My Activity History',
    'nav_categories' => 'Asset Categories',
    'nav_funding_sources' => 'Funding Sources',
    'nav_procurements' => 'Incoming Stock',
    'nav_bulk_stocks' => 'Bulk Loan Containers',
    'nav_consumables' => 'Consumables',
    'activity_log_label' => 'Activity Log',
    'log_time' => 'Time',
    'log_actor' => 'Actor',
    'log_action' => 'Action',
    'log_description' => 'Description',
    'log_subject' => 'Subject',

    // Navigation groups
    'nav_group_transactions' => 'Transactions',
    'nav_group_master' => 'Master Data',
    'nav_group_reports' => 'Reports',
    'nav_group_system' => 'System',

    // About page
    'about_version' => 'Version :version',
    'about_body' => 'Inventory, incoming stock, consumables, and equipment loan management for Computer Network and Telecommunication Engineering (TJKT) at SMK Walisongo Pecangaan.',

    // Loan abilities (owned by the Loans domain elsewhere; labels live here
    // because Acl.php belongs to this workstream)
    'ability_loan_view_any' => 'View Loans',
    'ability_loan_create' => 'Create loan',
    'ability_loan_update' => 'Update / return loan',
    'ability_loan_delete' => 'Delete loan history',
    'ability_loan_approve' => 'Approve / verify loans',
    'ability_activity_log_view_any' => 'View Activity Logs',
];
