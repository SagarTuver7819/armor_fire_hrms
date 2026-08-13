<?php
/**
 * Masters Config - all 10 HRMS masters
 * Used by hub + shared CRUD core.
 */

function getMastersConfig()
{
    return [
        'designations' => [
            'key'         => 'designations',
            'title'       => 'Designation Master',
            'singular'    => 'Designation',
            'table'       => 'designations',
            'folder'      => 'designations',
            'icon'        => 'fa-id-badge',
            'color'       => '#5B6CFF',
            'name_field'  => 'name',
            'list_columns'=> [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'name', 'label' => 'Designation'],
                ['key' => 'sort_order', 'label' => 'Sort'],
            ],
            'fields' => [
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => false, 'span' => 1],
                ['name' => 'name', 'label' => 'Designation Name', 'type' => 'text', 'required' => true, 'span' => 2],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'span' => 3],
                ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number', 'required' => false, 'span' => 1, 'default' => 0],
            ],
        ],

        'departments' => [
            'key'         => 'departments',
            'title'       => 'Department Master',
            'singular'    => 'Department',
            'table'       => 'departments',
            'folder'      => 'departments',
            'icon'        => 'fa-building',
            'color'       => '#F58220',
            'name_field'  => 'department_name',
            'list_columns'=> [
                ['key' => 'department_name', 'label' => 'Department'],
                ['key' => 'icon_class', 'label' => 'Icon'],
                ['key' => 'icon_color', 'label' => 'Color'],
                ['key' => 'sort_order', 'label' => 'Sort'],
            ],
            'fields' => [
                ['name' => 'department_name', 'label' => 'Department Name', 'type' => 'text', 'required' => true, 'span' => 2],
                ['name' => 'icon_class', 'label' => 'Icon Class (Font Awesome)', 'type' => 'text', 'required' => false, 'span' => 1, 'default' => 'fa-building', 'help' => 'e.g. fa-users'],
                ['name' => 'icon_color', 'label' => 'Icon Color', 'type' => 'color', 'required' => false, 'span' => 1, 'default' => '#F58220'],
                ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number', 'required' => false, 'span' => 1, 'default' => 0],
            ],
        ],

        'shifts' => [
            'key'         => 'shifts',
            'title'       => 'Shift Master',
            'singular'    => 'Shift',
            'table'       => 'shifts',
            'folder'      => 'shifts',
            'icon'        => 'fa-clock',
            'color'       => '#3498DB',
            'name_field'  => 'name',
            'list_columns'=> [
                ['key' => 'name', 'label' => 'Shift'],
                ['key' => 'shift_type', 'label' => 'Type'],
                ['key' => 'start_time', 'label' => 'Start'],
                ['key' => 'end_time', 'label' => 'End'],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Shift Name', 'type' => 'text', 'required' => true, 'span' => 2],
                ['name' => 'shift_type', 'label' => 'Shift Type', 'type' => 'select', 'required' => true, 'span' => 1,
                    'options' => ['Day' => 'Day', 'Night' => 'Night']],
                ['name' => 'start_time', 'label' => 'Start Time', 'type' => 'time', 'required' => false, 'span' => 1],
                ['name' => 'end_time', 'label' => 'End Time', 'type' => 'time', 'required' => false, 'span' => 1],
                ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'required' => false, 'span' => 3],
            ],
        ],

        'leaves' => [
            'key'         => 'leaves',
            'title'       => 'Leave Master',
            'singular'    => 'Leave Type',
            'table'       => 'leave_types',
            'folder'      => 'leaves',
            'icon'        => 'fa-umbrella-beach',
            'color'       => '#2ECC71',
            'name_field'  => 'leave_type',
            'list_columns'=> [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'leave_type', 'label' => 'Leave Type'],
                ['key' => 'days_allowed', 'label' => 'Days'],
                ['key' => 'is_paid', 'label' => 'Paid'],
            ],
            'fields' => [
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => false, 'span' => 1],
                ['name' => 'leave_type', 'label' => 'Leave Type', 'type' => 'text', 'required' => true, 'span' => 2],
                ['name' => 'days_allowed', 'label' => 'Days Allowed', 'type' => 'number', 'required' => false, 'span' => 1, 'default' => 0],
                ['name' => 'is_paid', 'label' => 'Paid Leave', 'type' => 'select', 'required' => true, 'span' => 1,
                    'options' => ['Yes' => 'Yes', 'No' => 'No'], 'default' => 'Yes'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'span' => 3],
            ],
        ],

        'holidays' => [
            'key'         => 'holidays',
            'title'       => 'Holiday / Week-Off Master',
            'singular'    => 'Holiday / Week-Off',
            'table'       => 'holidays',
            'folder'      => 'holidays',
            'icon'        => 'fa-calendar-days',
            'color'       => '#E85D75',
            'name_field'  => 'title',
            'list_columns'=> [
                ['key' => 'title', 'label' => 'Title'],
                ['key' => 'holiday_type', 'label' => 'Type'],
                ['key' => 'holiday_date', 'label' => 'Date'],
                ['key' => 'week_day', 'label' => 'Week Day'],
            ],
            'fields' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'span' => 2],
                ['name' => 'holiday_type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'span' => 1,
                    'options' => ['Holiday' => 'Holiday', 'Week-Off' => 'Week-Off']],
                ['name' => 'holiday_date', 'label' => 'Holiday Date', 'type' => 'date', 'required' => false, 'span' => 1, 'help' => 'For Holiday type'],
                ['name' => 'week_day', 'label' => 'Week Day', 'type' => 'select', 'required' => false, 'span' => 1,
                    'options' => [
                        '' => '— Select —',
                        'Sunday' => 'Sunday', 'Monday' => 'Monday', 'Tuesday' => 'Tuesday',
                        'Wednesday' => 'Wednesday', 'Thursday' => 'Thursday',
                        'Friday' => 'Friday', 'Saturday' => 'Saturday',
                    ], 'help' => 'For Week-Off type'],
                ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'required' => false, 'span' => 3],
            ],
        ],

        'salary' => [
            'key'         => 'salary',
            'title'       => 'Salary Master',
            'singular'    => 'Salary Component',
            'table'       => 'salary_components',
            'folder'      => 'salary',
            'icon'        => 'fa-indian-rupee-sign',
            'color'       => '#16A085',
            'name_field'  => 'component_name',
            'list_columns'=> [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'component_name', 'label' => 'Component'],
                ['key' => 'component_type', 'label' => 'Type'],
                ['key' => 'calculation', 'label' => 'Calc'],
                ['key' => 'default_value', 'label' => 'Default'],
            ],
            'fields' => [
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => false, 'span' => 1],
                ['name' => 'component_name', 'label' => 'Component Name', 'type' => 'text', 'required' => true, 'span' => 2],
                ['name' => 'component_type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'span' => 1,
                    'options' => ['Earning' => 'Earning', 'Deduction' => 'Deduction']],
                ['name' => 'calculation', 'label' => 'Calculation', 'type' => 'select', 'required' => true, 'span' => 1,
                    'options' => ['Fixed' => 'Fixed', 'Percentage' => 'Percentage']],
                ['name' => 'default_value', 'label' => 'Default Value', 'type' => 'number', 'required' => false, 'span' => 1, 'default' => 0, 'step' => '0.01'],
            ],
        ],

        'documents' => [
            'key'         => 'documents',
            'title'       => 'Document Master',
            'singular'    => 'Document',
            'table'       => 'document_types',
            'folder'      => 'documents',
            'icon'        => 'fa-file-lines',
            'color'       => '#9B59B6',
            'name_field'  => 'document_name',
            'list_columns'=> [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'document_name', 'label' => 'Document'],
                ['key' => 'is_mandatory', 'label' => 'Mandatory'],
                ['key' => 'validity_days', 'label' => 'Validity Days'],
            ],
            'fields' => [
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => false, 'span' => 1],
                ['name' => 'document_name', 'label' => 'Document Name', 'type' => 'text', 'required' => true, 'span' => 2],
                ['name' => 'is_mandatory', 'label' => 'Mandatory', 'type' => 'select', 'required' => true, 'span' => 1,
                    'options' => ['Yes' => 'Yes', 'No' => 'No'], 'default' => 'No'],
                ['name' => 'validity_days', 'label' => 'Validity Days', 'type' => 'number', 'required' => false, 'span' => 1, 'default' => 0],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'span' => 3],
            ],
        ],

        'assets' => [
            'key'         => 'assets',
            'title'       => 'Asset Allocation Master',
            'singular'    => 'Asset',
            'table'       => 'assets',
            'folder'      => 'assets',
            'icon'        => 'fa-laptop',
            'color'       => '#E67E22',
            'name_field'  => 'asset_name',
            'list_columns'=> [
                ['key' => 'asset_code', 'label' => 'Code'],
                ['key' => 'asset_name', 'label' => 'Asset'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'condition_status', 'label' => 'Condition'],
            ],
            'fields' => [
                ['name' => 'asset_code', 'label' => 'Asset Code', 'type' => 'text', 'required' => false, 'span' => 1],
                ['name' => 'asset_name', 'label' => 'Asset Name', 'type' => 'text', 'required' => true, 'span' => 2],
                ['name' => 'category', 'label' => 'Category', 'type' => 'text', 'required' => false, 'span' => 1],
                ['name' => 'serial_no', 'label' => 'Serial No', 'type' => 'text', 'required' => false, 'span' => 1],
                ['name' => 'condition_status', 'label' => 'Condition', 'type' => 'select', 'required' => true, 'span' => 1,
                    'options' => [
                        'New' => 'New', 'Good' => 'Good', 'Fair' => 'Fair',
                        'Needs Repair' => 'Needs Repair', 'Scrap' => 'Scrap',
                    ], 'default' => 'Good'],
                ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'required' => false, 'span' => 3],
            ],
        ],

        'reports' => [
            'key'         => 'reports',
            'title'       => 'Report Master',
            'singular'    => 'Report',
            'table'       => 'report_catalog',
            'folder'      => 'reports',
            'icon'        => 'fa-chart-simple',
            'color'       => '#1ABC9C',
            'name_field'  => 'report_name',
            'list_columns'=> [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'report_name', 'label' => 'Report'],
                ['key' => 'module_name', 'label' => 'Module'],
            ],
            'fields' => [
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => false, 'span' => 1],
                ['name' => 'report_name', 'label' => 'Report Name', 'type' => 'text', 'required' => true, 'span' => 2],
                ['name' => 'module_name', 'label' => 'Module', 'type' => 'text', 'required' => false, 'span' => 1],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'span' => 3],
            ],
        ],

        'products' => [
            'key'         => 'products',
            'title'       => 'Product Master',
            'singular'    => 'Product',
            'table'       => 'products',
            'folder'      => 'products',
            'icon'        => 'fa-box',
            'color'       => '#C0392B',
            'name_field'  => 'product_name',
            'list_columns'=> [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'product_name', 'label' => 'Product'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'unit', 'label' => 'Unit'],
            ],
            'fields' => [
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => false, 'span' => 1],
                ['name' => 'product_name', 'label' => 'Product Name', 'type' => 'text', 'required' => true, 'span' => 2],
                ['name' => 'category', 'label' => 'Category', 'type' => 'text', 'required' => false, 'span' => 1],
                ['name' => 'unit', 'label' => 'Unit', 'type' => 'text', 'required' => false, 'span' => 1, 'default' => 'Nos'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'span' => 3],
            ],
        ],
    ];
}

/**
 * Get one master config by key
 */
function getMasterConfig($key)
{
    $all = getMastersConfig();
    return $all[$key] ?? null;
}
