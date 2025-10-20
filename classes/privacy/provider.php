<?php
namespace assignsubmission_remotecheck\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\localequest\writer;
use core_privacy\localequestpproved_contextlist;

defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\provider, \core_privacy\localequest\plugin_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('assignsubmission_remotecheck', [
            'assignment' => 'privacy:metadata:assignment',
            'submission' => 'privacy:metadata:submission',
            'buildingid' => 'privacy:metadata:buildingid',
            'buildingaddress' => 'privacy:metadata:buildingaddress',
            'param1' => 'privacy:metadata:param',
            'param2' => 'privacy:metadata:param',
            'param3' => 'privacy:metadata:param',
            'param4' => 'privacy:metadata:param',
            'param5' => 'privacy:metadata:param',
            'param6' => 'privacy:metadata:param',
            'param7' => 'privacy:metadata:param',
            'param8' => 'privacy:metadata:param',
            'param9' => 'privacy:metadata:param',
            'studentresult' => 'privacy:metadata:studentresult',
            'isvalid' => 'privacy:metadata:isvalid',
            'validationjson' => 'privacy:metadata:validationjson',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:table');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid) {
        return new \core_privacy\localequest\contextlist();
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        // Implementation omitted for brevity in this sample.
    }

    public static function delete_data_for_all_users_in_context(\context $context) {}
    public static function delete_data_for_user(approved_contextlist $contextlist) {}
}
