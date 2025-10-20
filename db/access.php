<?php
// No new capabilities are strictly required for this plugin, but a view capability can be defined if needed.

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'assignsubmission/remotecheck:viewvalidation' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

     'assignsubmission/remotecheck:managedata' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM, // Manage at site level.
        'archetypes'   => [
            'manager'         => CAP_ALLOW,
            'editingteacher'  => CAP_PREVENT, // Tight by default; you can relax per site.
            'teacher'         => CAP_PREVENT,
        ],
        'riskbitmask'  => RISK_DATALOSS | RISK_CONFIG
    ],
];



