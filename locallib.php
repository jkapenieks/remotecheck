<?php
// Main submission plugin implementation.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/assign/submissionplugin.php');


use assignsubmission_remotecheck\local\remote_repository;
use assignsubmission_remotecheck\local\formula_evaluator;


class assign_submission_remotecheck extends assign_submission_plugin {

    public function get_name(): string {
        return get_string('pluginname', 'assignsubmission_remotecheck');
    }

    public function is_configurable(): bool { return true; }

    public function get_settings(MoodleQuickForm $mform) {
    $mform->addElement('header', 'remotecheckhdr', get_string('paramlabel', 'assignsubmission_remotecheck'));

    // Existing settings...
    $mform->addElement('advcheckbox', 'assignsubmission_remotecheck_enabled', get_string('enabled', 'assignsubmission_remotecheck'));
    $mform->setDefault('assignsubmission_remotecheck_enabled', 1);

    $mform->addElement('text', 'assignsubmission_remotecheck_formula', get_string('formula', 'assignsubmission_remotecheck'));
    $mform->addHelpButton('assignsubmission_remotecheck_formula', 'formula', 'assignsubmission_remotecheck');
    $mform->setType('assignsubmission_remotecheck_formula', PARAM_RAW_TRIMMED);
    $mform->setDefault('assignsubmission_remotecheck_formula', $this->get_config('formula'));

    $mform->addElement('text', 'assignsubmission_remotecheck_tolabs', get_string('tolabs', 'assignsubmission_remotecheck'));
    $mform->setType('assignsubmission_remotecheck_tolabs', PARAM_FLOAT);
    $mform->setDefault('assignsubmission_remotecheck_tolabs', 0.01);

    $mform->addElement('text', 'assignsubmission_remotecheck_tolpct', get_string('tolpct', 'assignsubmission_remotecheck'));
    $mform->setType('assignsubmission_remotecheck_tolpct', PARAM_FLOAT);
    $mform->setDefault('assignsubmission_remotecheck_tolpct', 0.0);

    $mform->addElement('advcheckbox', 'assignsubmission_remotecheck_compare_remote_result', get_string('compare_remote_result', 'assignsubmission_remotecheck'));
    $mform->setDefault('assignsubmission_remotecheck_compare_remote_result', 0);

    // // ✅ New: custom labels.
    // $mform->addElement('header', 'remotechecklabelshdr', get_string('paramlabels', 'assignsubmission_remotecheck'));
    // $mform->addHelpButton('remotechecklabelshdr', 'paramlabels', 'assignsubmission_remotecheck');

    for ($i = 1; $i <= 9; $i++) {
        $elname = 'assignsubmission_remotecheck_label' . $i;
        $mform->addElement('text', $elname, get_string('labeln', 'assignsubmission_remotecheck', $i));
        $mform->setType($elname, PARAM_TEXT);
        // Show previously saved value or sensible default.
        $mform->setDefault($elname, $this->get_config('label'.$i) ?: get_string('paramn', 'assignsubmission_remotecheck', $i));
    }

    $mform->addElement('text', 'assignsubmission_remotecheck_resultlabel', get_string('resultlabel', 'assignsubmission_remotecheck'));
    $mform->setType('assignsubmission_remotecheck_resultlabel', PARAM_TEXT);
    $mform->setDefault('assignsubmission_remotecheck_resultlabel', $this->get_config('resultlabel') ?: get_string('result', 'assignsubmission_remotecheck'));


    // ---- New: per-assignment table name override ----
    $mform->addElement(
        'text',
        'assignsubmission_remotecheck_table',
        get_string('table', 'assignsubmission_remotecheck')
    );
    $mform->setType('assignsubmission_remotecheck_table', PARAM_RAW_TRIMMED);

    // Default = assignment-level value if set, otherwise fall back to site-level admin setting.
    $perinstance = $this->get_config('table');
    $sitewide    = get_config('assignsubmission_remotecheck', 'table'); // default fallback
    $mform->setDefault('assignsubmission_remotecheck_table', $perinstance !== false ? $perinstance : $sitewide);

    $mform->addHelpButton('assignsubmission_remotecheck_table', 'table', 'assignsubmission_remotecheck');


    return true;
}


    public function save_settings(stdClass $data) {
        $this->set_config('enabled', !empty($data->assignsubmission_remotecheck_enabled) ? 1 : 0);
        $this->set_config('formula', !empty($data->assignsubmission_remotecheck_formula) ? $data->assignsubmission_remotecheck_formula : '');
        $this->set_config('tolabs', isset($data->assignsubmission_remotecheck_tolabs) ? (float)$data->assignsubmission_remotecheck_tolabs : 0.01);
        $this->set_config('tolpct', isset($data->assignsubmission_remotecheck_tolpct) ? (float)$data->assignsubmission_remotecheck_tolpct : 0.0);
        $this->set_config('compare_remote_result', !empty($data->assignsubmission_remotecheck_compare_remote_result) ? 1 : 0);


        // ✅ New: labels.
        for ($i = 1; $i <= 9; $i++) {
            $k = 'assignsubmission_remotecheck_label' . $i;
            $this->set_config('label'.$i, trim($data->$k ?? ''));
        }
        $this->set_config('resultlabel', trim($data->assignsubmission_remotecheck_resultlabel ?? ''));


        // ---- New: store per-assignment table ----
        $table = trim($data->assignsubmission_remotecheck_table ?? '');
        $this->set_config('table', $table);


        return true;
    }

    private function repo_override(): array {
        $override = [];
        $tab = trim((string)$this->get_config('table'));
        if ($tab !== '') { $override['table'] = $tab; }
        return $override;
    }

    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data): bool {
        if (!$this->is_enabled()) { return false; }

        //$repo = new remote_repository();
        $override = $this->repo_override();
        $repo = new remote_repository($override);

        $addresses = $repo->list_addresses();
        $mform->addElement('select', 'remotecheck_buildingid', get_string('building', 'assignsubmission_remotecheck'), $addresses);
        $mform->addRule('remotecheck_buildingid', null, 'required', null, 'client');


        // ✅ Labels for param1..param9
        for ($i = 1; $i <= 9; $i++) {
            $label = trim((string)$this->get_config('label'.$i));
            if ($label === '') {
                $label = get_string('paramn', 'assignsubmission_remotecheck', $i);
            }
            $mform->addElement('text', 'remotecheck_param' . $i, $label);
            $mform->setType('remotecheck_param' . $i, PARAM_FLOAT);
        }


        // ✅ Label for result
        $reslabel = trim((string)$this->get_config('resultlabel')) ?: get_string('result', 'assignsubmission_remotecheck');
        $mform->addElement('text', 'remotecheck_result', $reslabel);
        $mform->setType('remotecheck_result', PARAM_FLOAT);



        // for ($i=1; $i<=9; $i++) {
        //     $mform->addElement('text', 'remotecheck_param'.$i, get_string('paramn', 'assignsubmission_remotecheck', $i));
        //     $mform->setType('remotecheck_param'.$i, PARAM_FLOAT);
        // }
        // $mform->addElement('text', 'remotecheck_result', get_string('result', 'assignsubmission_remotecheck'));
        // $mform->setType('remotecheck_result', PARAM_FLOAT);







        // ✅ Load previously saved values (if this submission already exists).
        if (!empty($submission->id)) {
            global $DB;
            if ($rec = $DB->get_record('assignsubmission_remotecheck',
                    ['submission' => $submission->id])) {
                $data->remotecheck_buildingid = $rec->buildingid;
                for ($i = 1; $i <= 9; $i++) {
                    $data->{'remotecheck_param' . $i} = $rec->{'param' . $i};
                }
                $data->remotecheck_result = $rec->studentresult;

                // (Optional) also set explicit defaults on the form:
                $mform->setDefault('remotecheck_buildingid', $data->remotecheck_buildingid);
                for ($i = 1; $i <= 9; $i++) {
                    $mform->setDefault('remotecheck_param' . $i, $data->{'remotecheck_param' . $i});
                }
                $mform->setDefault('remotecheck_result', $data->remotecheck_result);
            }
        }


        return true;
    }

    public function save(stdClass $submission, stdClass $data): bool {
        global $DB, $USER, $CFG, $PAGE;
        if (!$this->is_enabled()) { return true; }

        $rec = new stdClass();
        $rec->assignment = $this->assignment->get_instance()->id;
        $rec->submission = $submission->id;
        $rec->buildingid = isset($data->remotecheck_buildingid) ? (int)$data->remotecheck_buildingid : null;
        $rec->timecreated = time();
        $rec->timemodified = time();

        $params = [];
        for ($i=1; $i<=9; $i++) {
            $key = 'remotecheck_param'.$i;
            $val = isset($data->$key) ? (float)$data->$key : null;
            $rec->{'param'.$i} = $val;
            $params[$i] = $val;
        }
        $rec->studentresult = isset($data->remotecheck_result) ? (float)$data->remotecheck_result : null;

        // Validate against remote row and formula.
        $validation = $this->validate_submission($rec, $params);
        $rec->isvalid = $validation['isvalid'] ? 1 : 0;
        $rec->validationjson = json_encode($validation, JSON_PRETTY_PRINT);

        $existing = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id]);
        if ($existing) {
            $rec->id = $existing->id;
            $rec->timecreated = $existing->timecreated;
            $DB->update_record('assignsubmission_remotecheck', $rec);
        } else {
            $DB->insert_record('assignsubmission_remotecheck', $rec);
        }

        return true;
    }

    private function validate_submission(stdClass $rec, array $params): array {
        $override = $this->repo_override();
        $repo = new remote_repository($override);
        //$repo = new remote_repository();
        $tolabs = (float)$this->get_config('tolabs');
        $tolpct = (float)$this->get_config('tolpct');
        $formula = (string)$this->get_config('formula');
        $compareRemote = (bool)$this->get_config('compare_remote_result');

        $out = [
            'paramchecks' => [],
            'resultcheck' => null,
            'remotecompare' => null,
            'isvalid' => false,
        ];

        $allok = true;
        $remote = $repo->get_row_by_id($rec->buildingid);
        if (!$remote) {
            $out['error'] = get_string('remotedbnotready', 'assignsubmission_remotecheck');
            $allok = false;
        } else {
            // Parameter checks.
            for ($i=1; $i<=9; $i++) {
                $student = (float)($rec->{'param'.$i} ?? 0);
                $expected = isset($remote['param'.$i]) ? (float)$remote['param'.$i] : 0.0;
                $absdiff = abs($student - $expected);
                $allow = max($tolabs, ($tolpct>0 ? abs($expected) * $tolpct/100.0 : 0));
                $ok = $absdiff <= $allow;
                $out['paramchecks']['p'.$i] = [
                    'student' => $student,
                    'expected' => $expected,
                    'absdiff' => $absdiff,
                    'allowed' => $allow,
                    'ok' => $ok,
                ];
                if (!$ok) { $allok = false; }
            }

            // Formula result check.
            $calc = formula_evaluator::evaluate($formula, $params);
            if ($calc === null) {
                $out['resultcheck'] = [ 'error' => 'Formula invalid or unsafe' ];
                $allok = false;
            } else {
                $studentres = (float)($rec->studentresult ?? 0);
                $absdiff = abs($studentres - $calc);
                $allow = max($tolabs, ($tolpct>0 ? abs($calc) * $tolpct/100.0 : 0));
                $ok = $absdiff <= $allow;
                $out['resultcheck'] = [
                    'student' => $studentres,
                    'expected' => $calc,
                    'absdiff' => $absdiff,
                    'allowed' => $allow,
                    'ok' => $ok,
                ];
                if (!$ok) { $allok = false; }
            }

            if ($compareRemote && isset($remote['calcresult'])) {
                $studentres = (float)($rec->studentresult ?? 0);
                $expected = (float)$remote['calcresult'];
                $absdiff = abs($studentres - $expected);
                $allow = max($tolabs, ($tolpct>0 ? abs($expected) * $tolpct/100.0 : 0));
                $ok = $absdiff <= $allow;
                $out['remotecompare'] = [
                    'student' => $studentres,
                    'remote' => $expected,
                    'absdiff' => $absdiff,
                    'allowed' => $allow,
                    'ok' => $ok,
                ];
                if (!$ok) { $allok = false; }
            }
        }

        $out['isvalid'] = $allok;
        $out['building'] = $remote ? ['id' => $remote['id'], 'address' => $remote['address']] : null;
        return $out;
    }

    public function view_summary(stdClass $submission, & $showviewlink) {
        global $DB;
        $showviewlink = false;
        $rec = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id]);
        if (!$rec) { return get_string('nosubmission', 'assignsubmission_remotecheck'); }
        $status = $rec->isvalid ? get_string('valid', 'assignsubmission_remotecheck') : get_string('invalid', 'assignsubmission_remotecheck');
        return get_string('summary', 'assignsubmission_remotecheck', $status);
    }

    public function view(stdClass $submission) {
        global $DB, $OUTPUT;
        $rec = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id]);
        if (!$rec) { return ''; }
        $val = json_decode($rec->validationjson, true) ?: [];
        $o = html_writer::start_tag('div', ['class' => 'remotecheck-summary']);
        $o .= html_writer::tag('div', get_string('building', 'assignsubmission_remotecheck').': '.s($rec->buildingaddress ?? '')); // address may not be saved; we show id.
        $o .= html_writer::tag('div', get_string('validity', 'assignsubmission_remotecheck').': '.($rec->isvalid ? get_string('valid', 'assignsubmission_remotecheck') : get_string('invalid', 'assignsubmission_remotecheck')));
        if (!empty($val['paramchecks'])) {
            $o .= html_writer::tag('h4', get_string('paramchecks', 'assignsubmission_remotecheck'));
            $o .= html_writer::start_tag('ul');
            foreach ($val['paramchecks'] as $name => $res) {
                $line = s($name).": ".$res['student']." vs ".$res['expected']." | ".($res['ok'] ? get_string('ok', 'assignsubmission_remotecheck') : get_string('mismatch', 'assignsubmission_remotecheck'));
                $o .= html_writer::tag('li', $line);
            }
            $o .= html_writer::end_tag('ul');
        }
        if (!empty($val['resultcheck'])) {
            if (empty($val['resultcheck']['error'])) {
                $r = $val['resultcheck'];
                $o .= html_writer::tag('div', get_string('result', 'assignsubmission_remotecheck').": ".$r['student']." vs expected ".$r['expected']." (".($r['ok']?get_string('ok', 'assignsubmission_remotecheck'):get_string('mismatch', 'assignsubmission_remotecheck')).")");
            } else {
                $o .= html_writer::tag('div', get_string('formulaerror', 'assignsubmission_remotecheck').': '.s($val['resultcheck']['error']));
            }
        }
        $o .= html_writer::end_tag('div');
        return $o;
    }

    /**
 * Decide if the current POST data is an empty submission (before save).
 * If this returns true AND all other enabled plugins also return true,
 * Moodle will show “Nothing was submitted”.
 */
public function submission_is_empty(stdClass $data): bool {
    // Must have a building AND at least one numeric param or a result.
    if (empty($data->remotecheck_buildingid)) {
        return true;
    }

    $hasparam = false;
    for ($i = 1; $i <= 9; $i++) {
        $k = 'remotecheck_param' . $i;
        if (isset($data->$k) && $data->$k !== '' && $data->$k !== null) {
            $hasparam = true;
            break;
        }
    }

    $hasresult = (isset($data->remotecheck_result) && $data->remotecheck_result !== '' && $data->remotecheck_result !== null);

    return !($hasparam || $hasresult);
}

/**
 * Copy the plugin-specific submission data from a previous submission
 * to a new submission record (used for new attempts).
 */
public function copy_submission(stdClass $oldsubmission, stdClass $submission) {
    global $DB;
    $old = $DB->get_record('assignsubmission_remotecheck', ['submission' => $oldsubmission->id]);
    if (!$old) {
        return true; // Nothing to copy; not an error.
    }

    $new = new stdClass();
    $new->assignment     = $this->assignment->get_instance()->id;
    $new->submission     = $submission->id;
    $new->buildingid     = $old->buildingid;
    $new->buildingaddress= $old->buildingaddress;
    for ($i = 1; $i <= 9; $i++) {
        $new->{'param'.$i} = $old->{'param'.$i};
    }
    $new->studentresult  = $old->studentresult;
    $new->isvalid        = $old->isvalid;
    $new->validationjson = $old->validationjson;
    $new->timecreated    = time();
    $new->timemodified   = time();

    $DB->insert_record('assignsubmission_remotecheck', $new);
    return true;
}


/**
 * Delete this plugin's data when a student removes their submission.
 *
 * @param stdClass $submission The core assign_submission row whose data is being removed.
 * @return bool
 */
public function remove(stdClass $submission): bool {
    global $DB;

    // Delete detailed error rows first (if you use the optional table).
    if ($rec = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id], 'id')) {
        $DB->delete_records('assignsubmission_remotecheck_errs', ['remotecheckid' => $rec->id]);
    }

    // Delete the main submission record for this plugin.
    $DB->delete_records('assignsubmission_remotecheck', ['submission' => $submission->id]);

    return true;
}

/**
 * The assignment instance is being deleted; remove all plugin data for it.
 *
 * @return bool
 */
public function delete_instance(): bool {
    global $DB;

    $assignmentid = $this->assignment->get_instance()->id;

    // Fetch all plugin rows for this assignment to delete child rows first.
    $records = $DB->get_records('assignsubmission_remotecheck', ['assignment' => $assignmentid], '', 'id');
    if ($records) {
        list($insql, $params) = $DB->get_in_or_equal(array_keys($records));
        $DB->delete_records_select('assignsubmission_remotecheck_errs', 'remotecheckid ' . $insql, $params);
    }

    // Now delete the main rows.
    $DB->delete_records('assignsubmission_remotecheck', ['assignment' => $assignmentid]);

    return true;
}

/**
 * Decide if the saved submission record is empty (after save).
 */
public function is_empty(stdClass $submission): bool {
    global $DB;
    $rec = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id]);
    if (!$rec) {
        return true;
    }
    if (empty($rec->buildingid)) {
        return true;
    }
    // Consider non-empty if at least one param or the studentresult is present.
    for ($i = 1; $i <= 9; $i++) {
        $v = $rec->{'param' . $i} ?? null;
        if ($v !== null && $v !== '') {
            return false;
        }
    }
    return !($rec->studentresult !== null && $rec->studentresult !== '');
}

    public function is_enabled(): bool {
        return !empty($this->get_config('enabled'));
    }
}
