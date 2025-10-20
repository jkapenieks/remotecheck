<?php
// Admin settings for Remote Check submission plugin.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('assignsubmission_remotecheck', get_string('pluginname', 'assignsubmission_remotecheck'));

    // Remote DB connection.
    $settings->add(new admin_setting_heading('assignsubmission_remotecheck/conn', get_string('remotedb', 'assignsubmission_remotecheck'), ''));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/dbhost', get_string('dbhost', 'assignsubmission_remotecheck'), '', 'localhost'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/dbport', get_string('dbport', 'assignsubmission_remotecheck'), '', '3306'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/dbname', get_string('dbname', 'assignsubmission_remotecheck'), '', ''));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/dbuser', get_string('dbuser', 'assignsubmission_remotecheck'), '', ''));
    $settings->add(new admin_setting_configpasswordunmask('assignsubmission_remotecheck/dbpass', get_string('dbpass', 'assignsubmission_remotecheck'), '', ''));

    // Remote table + column mapping.
    $settings->add(new admin_setting_heading('assignsubmission_remotecheck/cols', get_string('remotecols', 'assignsubmission_remotecheck'), ''));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/table', get_string('table', 'assignsubmission_remotecheck'), '', 'buildings'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/idcol', get_string('idcol', 'assignsubmission_remotecheck'), '', 'id'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/addresscol', get_string('addresscol', 'assignsubmission_remotecheck'), '', 'address'));
    
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/param1', get_string('paramcol', 'assignsubmission_remotecheck', 1), '', 'param1'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/param2', get_string('paramcol', 'assignsubmission_remotecheck', 2), '', 'param2'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/param3', get_string('paramcol', 'assignsubmission_remotecheck', 3), '', 'param3'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/param4', get_string('paramcol', 'assignsubmission_remotecheck', 4), '', 'param4'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/param5', get_string('paramcol', 'assignsubmission_remotecheck', 5), '', 'param5'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/param6', get_string('paramcol', 'assignsubmission_remotecheck', 6), '', 'param6'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/param7', get_string('paramcol', 'assignsubmission_remotecheck', 7), '', 'param7'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/param8', get_string('paramcol', 'assignsubmission_remotecheck', 8), '', 'param8'));
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/param9', get_string('paramcol', 'assignsubmission_remotecheck', 9), '', 'param9'));

    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/resultcol', get_string('resultcol', 'assignsubmission_remotecheck'), '', 'result'));

    // Cache TTL.
    $settings->add(new admin_setting_configtext('assignsubmission_remotecheck/cachettl', get_string('cachettl', 'assignsubmission_remotecheck'), get_string('cachettl_desc', 'assignsubmission_remotecheck'), 3600));


    
    $ADMIN->add('modsettings', $settings);



    

}


// Register the external page outside $hassiteconfig, guard access via capability.
$ADMIN->add('modsettings', new admin_externalpage(
    'assignsubmission_remotecheck_manage',
    get_string('managebuildingdata', 'assignsubmission_remotecheck'),
    new moodle_url('/mod/assign/submission/remotecheck/manage.php'),
    'assignsubmission/remotecheck:managedata',
    false,
    \context_system::instance()
));


