<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use assignsubmission_remotecheck\local\remote_repository;
use assignsubmission_remotecheck\form\building_form;

require_login();
$sysctx = context_system::instance();

// Require plugin capability for all users of this page.
require_capability('assignsubmission/remotecheck:managedata', $sysctx);

// Use admin tree setup only for site admins (moodle/site:config).
if (has_capability('moodle/site:config', $sysctx)) {
   // admin_externalpage_setup('assignsubmission_remotecheck_manage');
} else {
    $PAGE->set_context($sysctx);
    $PAGE->set_url(new moodle_url('/mod/assign/submission/remotecheck/manage.php'));
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title(get_string('managebuildingdata', 'assignsubmission_remotecheck'));
    $PAGE->set_heading(get_string('managebuildingdata', 'assignsubmission_remotecheck'));
}

$action = optional_param('action', 'list', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);


// Find all distinct per-assignment table names configured in assign_plugin_config.
global $DB;
$sql = "SELECT DISTINCT TRIM(value) AS tableval
          FROM {assign_plugin_config}
         WHERE plugin = :plugin
           AND subtype = :subtype
           AND name = :name
           AND TRIM(value) <> ''";
$params = ['plugin' => 'remotecheck', 'subtype' => 'assignsubmission', 'name' => 'table'];
$rows = $DB->get_records_sql($sql, $params);

$tables = [];
foreach ($rows as $r) {
    $tables[] = $r->tableval;
}

// Include the site-level default as a fallback/option.
$defaulttable = get_config('assignsubmission_remotecheck', 'table'); // config_plugins
if (!empty($defaulttable) && !in_array($defaulttable, $tables, true)) {
    $tables[] = $defaulttable;
}
sort($tables);

// Handle selection from the URL (?table=...).
$selected = optional_param('table', '', PARAM_RAW_TRIMMED);
if (empty($tables)) {
    // Nothing configured anywhere: show a gentle prompt and exit early.
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('remotedbnotready', 'assignsubmission_remotecheck'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}
if ($selected === '' || !in_array($selected, $tables, true)) {
    $selected = reset($tables); // default to first enumerated table
}



// Instead of: $repo = new remote_repository();
$repo = new assignsubmission_remotecheck\local\remote_repository(['table' => $selected]);

// $repo = new remote_repository();
if (!$repo->ready()) {
    print_error('remotedbnotready', 'assignsubmission_remotecheck');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managebuildingdata', 'assignsubmission_remotecheck'), 3);

$back = new moodle_url('/mod/assign/submission/remotecheck/manage.php');

if ($action === 'delete' && $id) {
    require_sesskey();
    if (!$repo->delete_row((int)$id)) {
        \core\notification::error('Delete failed: ' . $repo->last_error());
    } else {
        \core\notification::success(get_string('deleted'));
    }
    echo $OUTPUT->continue_button($back);
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'add' || ($action === 'edit' && $id)) {
    $labels = ['result' => get_string('result', 'assignsubmission_remotecheck')];
    for ($i = 1; $i <= 9; $i++) {
        $labels['p' . $i] = get_string('paramn', 'assignsubmission_remotecheck', $i);
    }

    $mform = new building_form(null, ['labels' => $labels]);

    if ($mform->is_cancelled()) {
        redirect($back);
    } else if ($data = $mform->get_data()) {
        require_sesskey();
        $row = [
            'address' => $data->address,
            'calcresult' => ($data->calcresult === '' ? null : (float)$data->calcresult)
        ];
        for ($i = 1; $i <= 9; $i++) {
            $k = 'param' . $i;
            $row[$k] = ($data->$k === '' ? null : (float)$data->$k);
        }

        if ($data->action === 'edit' && $data->id) {
            if (!$repo->update_row((int)$data->id, $row)) {
                \core\notification::error('Update failed: ' . $repo->last_error());
                $mform->set_data($data);
                $mform->display();
                echo $OUTPUT->footer();
                exit;
            }
            redirect($back, get_string('changessaved'), 2);
        } else {
            if (!$repo->create_row($row)) {
                \core\notification::error('Insert failed: ' . $repo->last_error());
                $mform->set_data($data);
                $mform->display();
                echo $OUTPUT->footer();
                exit;
            }
            redirect($back, get_string('changessaved'), 2);
        }
    } else {
        if ($action === 'edit' && $id) {
            if ($row = $repo->get_row_by_id((int)$id)) {
                $defaults = (object)[
                    'action' => 'edit',
                    'id' => $row['id'],
                    'address' => $row['address'],
                    'calcresult' => $row['calcresult']
                ];
                for ($i = 1; $i <= 9; $i++) {
                    $defaults->{'param' . $i} = $row['param' . $i];
                }
                $mform->set_data($defaults);
            }
        } else {
            $mform->set_data((object)['action' => 'add']);
        }
        $mform->display();
        echo $OUTPUT->footer();
        exit;
    }
}

// Render a dropdown to switch between tables.
$choices = array_combine($tables, $tables);
echo html_writer::div(
    $OUTPUT->single_select($PAGE->url, 'table', $choices, $selected, null),
    'mb-3'
);

// Optional: show a badge what table is active
echo html_writer::div(
    get_string('table', 'assignsubmission_remotecheck') . ': ' . s($selected),
    'mb-2 text-muted'
);

// List view.
$table = new html_table();
$table->head = [
    get_string('id', 'assignsubmission_remotecheck'),
    get_string('building', 'assignsubmission_remotecheck'),
    'p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8', 'p9',
    get_string('result', 'assignsubmission_remotecheck'),
    get_string('actions')
];

$rows = $repo->list_addresses();
if ($rows) {
    foreach ($rows as $rid => $addr) {
        $full = $repo->get_row_by_id((int)$rid);
        $actions = [];
        $actions[] = html_writer::link(new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $rid]), get_string('edit'));
        $actions[] = html_writer::link(new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $rid, 'sesskey' => sesskey()]), get_string('delete'), [
            'onclick' => "return confirm('" . get_string('confirmdelete') . "');"
        ]);
        $table->data[] = [
            s($rid), s($addr),
            s($full['param1'] ?? ''), s($full['param2'] ?? ''), s($full['param3'] ?? ''), s($full['param4'] ?? ''), s($full['param5'] ?? ''), s($full['param6'] ?? ''), s($full['param7'] ?? ''), s($full['param8'] ?? ''), s($full['param9'] ?? ''),
            s($full['calcresult'] ?? ''), implode(' | ', $actions)
        ];
    }
}

echo html_writer::div(html_writer::link(new moodle_url($PAGE->url, ['action' => 'add']), get_string('add')), 'mb-3');
echo html_writer::table($table);

echo $OUTPUT->footer();