<?php
defined('MOODLE_INTERNAL') || die();

$definitions = [
    'remote_rows' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => (int) get_config('assignsubmission_remotecheck', 'cachettl') ?: 3600,
    ],
];
