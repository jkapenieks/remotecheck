<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by the
// Free Software Foundation, either version 3 of the License, or (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Assign submission subplugin: Remote Check
 *
 * @package    assignsubmission_remotecheck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

use assignsubmission_remotecheck\local\remote_repository;
use assignsubmission_remotecheck\local\formula_evaluator;

class assign_submission_remotecheck extends assign_submission_plugin {

    public function get_name(): string {
        return get_string('pluginname', 'assignsubmission_remotecheck');
    }

    public function is_configurable(): bool {
        return true;
    }

    public function get_settings(MoodleQuickForm $mform) {
        $mform->addElement('header', 'remotecheckhdr', get_string('paramlabel', 'assignsubmission_remotecheck'));

        // Existing settings.
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

        // Random item selection (teacher setting).
        $mform->addElement(
            'advcheckbox',
            'assignsubmission_remotecheck_randomitem',
            get_string('randomitem', 'assignsubmission_remotecheck'),
            get_string('randomitem_desc', 'assignsubmission_remotecheck')
        );
        $mform->setDefault('assignsubmission_remotecheck_randomitem', (int)$this->get_config('randomitem') === 1 ? 1 : 0);

        // Optional custom labels for parameters (1..9) and result.
        for ($i = 1; $i <= 9; $i++) {
            $elname = 'assignsubmission_remotecheck_label' . $i;
            $mform->addElement('text', $elname, get_string('labeln', 'assignsubmission_remotecheck', $i));
            $mform->setType($elname, PARAM_TEXT);
            $mform->setDefault($elname, $this->get_config('label' . $i) ?: get_string('paramn', 'assignsubmission_remotecheck', $i));
        }
        $mform->addElement('text', 'assignsubmission_remotecheck_resultlabel', get_string('resultlabel', 'assignsubmission_remotecheck'));
        $mform->setType('assignsubmission_remotecheck_resultlabel', PARAM_TEXT);
        $mform->setDefault('assignsubmission_remotecheck_resultlabel', $this->get_config('resultlabel') ?: get_string('result', 'assignsubmission_remotecheck'));

        // Per-assignment table override.
        $mform->addElement('text', 'assignsubmission_remotecheck_table', get_string('table', 'assignsubmission_remotecheck'));
        $mform->setType('assignsubmission_remotecheck_table', PARAM_RAW_TRIMMED);
        $perinstance = $this->get_config('table');
        $sitewide = get_config('assignsubmission_remotecheck', 'table');
        $mform->setDefault('assignsubmission_remotecheck_table', $perinstance !== false ? $perinstance : $sitewide);
        $mform->addHelpButton('assignsubmission_remotecheck_table', 'table', 'assignsubmission_remotecheck');




    
        $mform->addElement('html', html_writer::empty_tag('hr'));

        // We only show the link once there is a Course Module (i.e., when editing an existing assignment).
        $cm = $this->assignment->get_course_module(false); // false = don't throw if missing
        if ($cm && !empty($cm->id)) {
            $context = context_module::instance($cm->id);

            // Show the link only to users who have management rights for this plugin.
            // if (has_capability('assignsubmission/remotecheck:manage', $context)) {
                $url = new moodle_url('/mod/assign/submission/remotecheck/manage.php', [
                    'id'      => $cm->id,    // cmid - manage.php should accept this
                    'table' => trim((string)$this->get_config('table')), // pass the table name
                    'sesskey' => sesskey()   // guard your actions inside manage.php with require_sesskey()
                ]);

                $linkhtml = html_writer::link(
                    $url,
                    get_string('managedata', 'assignsubmission_remotecheck'),
                    ['class' => 'btn btn-secondary', 'target' => '_blank', 'rel' => 'noopener']
                );

                $mform->addElement(
                    'static',
                    'assignsubmission_remotecheck_managelink',
                    get_string('remotedata', 'assignsubmission_remotecheck'), // left-hand label (optional)
                    $linkhtml
                );
          //  }
        } else {
            // When adding a new assignment (no CM yet), show a hint to save first.
            $hint = html_writer::span(
                get_string('managedata_hint_savefirst', 'assignsubmission_remotecheck'),
                'text-muted'
            );
            $mform->addElement('static', 'assignsubmission_remotecheck_managelink_hint', '', $hint);
        }






        return true;
    }

    public function save_settings(stdClass $data) {
        $this->set_config('enabled', !empty($data->assignsubmission_remotecheck_enabled) ? 1 : 0);
        $this->set_config('formula', !empty($data->assignsubmission_remotecheck_formula) ? $data->assignsubmission_remotecheck_formula : '');
        $this->set_config('tolabs', isset($data->assignsubmission_remotecheck_tolabs) ? (float)$data->assignsubmission_remotecheck_tolabs : 0.01);
        $this->set_config('tolpct', isset($data->assignsubmission_remotecheck_tolpct) ? (float)$data->assignsubmission_remotecheck_tolpct : 0.0);
        $this->set_config('compare_remote_result', !empty($data->assignsubmission_remotecheck_compare_remote_result) ? 1 : 0);
        $this->set_config('randomitem', !empty($data->assignsubmission_remotecheck_randomitem) ? 1 : 0);

        for ($i = 1; $i <= 9; $i++) {
            $k = 'assignsubmission_remotecheck_label' . $i;
            $this->set_config('label' . $i, trim($data->$k ?? ''));
        }
        $this->set_config('resultlabel', trim($data->assignsubmission_remotecheck_resultlabel ?? ''));

        $table = trim($data->assignsubmission_remotecheck_table ?? '');
        $this->set_config('table', $table);

        return true;
    }

    private function repo_override(): array {
        $override = [];
        $tab = trim((string)$this->get_config('table'));
        if ($tab !== '') {
            $override['table'] = $tab;
        }
        return $override;
    }

    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data): bool {
        if (!$this->is_enabled()) {
            return false;
        }
        

        $override = $this->repo_override();
        $repo     = new remote_repository($override);
        $addresses = $repo->list_addresses();               // [id => 'Item label', ...]

        // Use the broader 'Item' label for the UI.
        $mform->addElement('select', 'remotecheck_buildingid', get_string('item', 'assignsubmission_remotecheck'), $addresses);
        $mform->setType('remotecheck_buildingid', PARAM_INT);
        $mform->addRule('remotecheck_buildingid', null, 'required', null, 'client');

        $random = $this->random_item_enabled();
        if ($random) {
            // Compute which value to show/post (existing submission or deterministic).
            $value = null;
            if (!empty($submission->id)) {
                global $DB;
                if ($rec = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id], 'buildingid')) {
                    $value = (int)$rec->buildingid;
                }
            }
            if ($value === null) {
                $assignid = $this->assignment->get_instance()->id;
                $userid   = optional_param('userid', null, PARAM_INT) ?? $GLOBALS['USER']->id;
                $value    = $this->deterministic_building_for_user($assignid, $userid, $addresses);
            }
            if ($value !== null) {
                $mform->setDefault('remotecheck_buildingid', $value);
            }

            // Show read-only item text.
            $text = isset($addresses[$value]) ? s($addresses[$value]) : '';
            $mform->addElement('static', 'remotecheck_item_static', get_string('item', 'assignsubmission_remotecheck'), $text);

            // Replace the editable select with a hidden element that carries the value.
            $mform->removeElement('remotecheck_buildingid');
            $mform->addElement('hidden', 'remotecheck_buildingid', $value);
            $mform->setType('remotecheck_buildingid', PARAM_INT);
        }

        // Param 1..9.
        for ($i = 1; $i <= 9; $i++) {
            $label = trim((string)$this->get_config('label' . $i));
            if ($label === '') {
                $label = get_string('paramn', 'assignsubmission_remotecheck', $i);
            }
            $mform->addElement('text', 'remotecheck_param' . $i, $label);
            $mform->setType('remotecheck_param' . $i, PARAM_FLOAT);
        }

        // Result label.
        $reslabel = trim((string)$this->get_config('resultlabel')) ?: get_string('result', 'assignsubmission_remotecheck');
        $mform->addElement('text', 'remotecheck_result', $reslabel);
        $mform->setType('remotecheck_result', PARAM_FLOAT);

        // Load previously saved values (if this submission already exists).
        if (!empty($submission->id)) {
            global $DB;
            if ($rec = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id])) {
                $data->remotecheck_buildingid = $rec->buildingid;
                for ($i = 1; $i <= 9; $i++) {
                    $data->{'remotecheck_param' . $i} = $rec->{'param' . $i};
                }
                $data->remotecheck_result = $rec->studentresult;

                // Apply defaults to form elements present (works for hidden too).
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
        global $DB;
        // Ignore edits while locked (submitted for grading).
        if ($this->is_locked($submission)) {
            return true;
        }

        if (!$this->is_enabled()) {
            return true;
        }

        $rec = new stdClass();
        $rec->assignment   = $this->assignment->get_instance()->id;
        $rec->submission   = $submission->id;
        $rec->timecreated  = time();
        $rec->timemodified = time();

        $random = $this->random_item_enabled();
        if ($random) {
            // Keep existing allocation if present; else compute deterministic one.
            $existing = $DB->get_record('assignsubmission_remotecheck',
                ['submission' => $submission->id], 'id, buildingid', IGNORE_MISSING);

            if (!empty($existing) && !empty($existing->buildingid)) {
                $rec->buildingid = (int)$existing->buildingid;
            } else {
                $override  = $this->repo_override();
                $repo      = new remote_repository($override);
                $addresses = $repo->list_addresses();
                $assignid  = $this->assignment->get_instance()->id;
                $rec->buildingid = $this->deterministic_building_for_user($assignid, $submission->userid, $addresses);
                if ($rec->buildingid === null) {
                    // Let validation flag the issue if no items are available.
                    $rec->buildingid = null;
                }
            }
        } else {
            $rec->buildingid = isset($data->remotecheck_buildingid) ? (int)$data->remotecheck_buildingid : null;
        }

        // Params and result.
        $params = [];
        for ($i = 1; $i <= 9; $i++) {
            $key = 'remotecheck_param' . $i;
            $val = isset($data->$key) ? (float)$data->$key : null;
            $rec->{'param' . $i} = $val;
            $params[$i] = $val;
        }
        $rec->studentresult = isset($data->remotecheck_result) ? (float)$data->remotecheck_result : null;

        // Validate.
        $validation = $this->validate_submission($rec, $params);
        $rec->isvalid        = $validation['isvalid'] ? 1 : 0;
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
        $repo     = new remote_repository($override);

        $tolabs   = (float)$this->get_config('tolabs');
        $tolpct   = (float)$this->get_config('tolpct');
        $formula  = (string)$this->get_config('formula');
        $compareRemote = (bool)$this->get_config('compare_remote_result');

        $out = [
            'paramchecks'   => [],
            'resultcheck'   => null,
            'remotecompare' => null,
            'isvalid'       => false,
        ];
        $allok = true;

        $remote = $repo->get_row_by_id($rec->buildingid);
        if (!$remote) {
            $out['error'] = get_string('remotedbnotready', 'assignsubmission_remotecheck');
            $allok = false;
        } else {
            // Parameter checks.
            for ($i = 1; $i <= 9; $i++) {
                $student  = (float)($rec->{'param' . $i} ?? 0);
                $expected = isset($remote['param' . $i]) ? (float)$remote['param' . $i] : 0.0;
                $absdiff  = abs($student - $expected);
                $allow    = max($tolabs, ($tolpct > 0 ? abs($expected) * $tolpct / 100.0 : 0));
                $ok       = $absdiff <= $allow;

                $out['paramchecks']['p' . $i] = [
                    'student' => $student,
                    'expected'=> $expected,
                    'absdiff' => $absdiff,
                    'allowed' => $allow,
                    'ok'      => $ok,
                ];
                if (!$ok) {
                    $allok = false;
                }
            }

            // Formula result check.
            $calc = formula_evaluator::evaluate($formula, $params);
            if ($calc === null) {
                $out['resultcheck'] = ['error' => 'Formula invalid or unsafe'];
                $allok = false;
            } else {
                $studentres = (float)($rec->studentresult ?? 0);
                $absdiff    = abs($studentres - $calc);
                $allow      = max($tolabs, ($tolpct > 0 ? abs($calc) * $tolpct / 100.0 : 0));
                $ok         = $absdiff <= $allow;

                $out['resultcheck'] = [
                    'student'  => $studentres,
                    'expected' => $calc,
                    'absdiff'  => $absdiff,
                    'allowed'  => $allow,
                    'ok'       => $ok,
                ];
                if (!$ok) {
                    $allok = false;
                }
            }

            // Optional: compare against remote calc result.
            if ($compareRemote && isset($remote['calcresult'])) {
                $studentres = (float)($rec->studentresult ?? 0);
                $expected   = (float)$remote['calcresult'];
                $absdiff    = abs($studentres - $expected);
                $allow      = max($tolabs, ($tolpct > 0 ? abs($expected) * $tolpct / 100.0 : 0));
                $ok         = $absdiff <= $allow;

                $out['remotecompare'] = [
                    'student' => $studentres,
                    'remote'  => $expected,
                    'absdiff' => $absdiff,
                    'allowed' => $allow,
                    'ok'      => $ok,
                ];
                if (!$ok) {
                    $allok = false;
                }
            }
        }

        $out['isvalid'] = $allok;
        $out['building'] = $remote ? ['id' => $remote['id'], 'address' => $remote['address']] : null;
        return $out;
    }

    // public function view_summary(stdClass $submission, &$showviewlink) {
    //     global $DB;
    //     $showviewlink = false;

    //     // Hide text until the student has submitted.
    //     $requirebutton = !empty($this->assignment->get_instance()->submissiondrafts);
    //     if (!$requirebutton || ($submission->status ?? '') !== 'submitted') {
    //         return ''; // Render nothing.
    //     }

    //     $rec = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id]);
    //     if (!$rec) {
    //         return get_string('nosubmission', 'assignsubmission_remotecheck');
    //     }
    //     $status = $rec->isvalid ? get_string('valid', 'assignsubmission_remotecheck') : get_string('invalid', 'assignsubmission_remotecheck');
    //     return get_string('summary', 'assignsubmission_remotecheck', $status);

    // }

public function view_summary(stdClass $submission, &$showviewlink) {
        global $DB;

        $showviewlink = false;

        $requirebutton = !empty($this->assignment->get_instance()->submissiondrafts);
        if (!$requirebutton || ($submission->status ?? '') !== 'submitted') {
            return ''; // Render nothing.
        }

        // Fetch the plugin row for this attempt.
        $rec = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id], '*', IGNORE_MISSING);
        if (!$rec) {
            return 'N/A';
        }

        // Decode JSON payload (built by validate_submission()).
        $val = json_decode($rec->validationjson ?? '', true);
        if (!is_array($val)) {
            $status = !empty($rec->isvalid)
                ? get_string('valid', 'assignsubmission_remotecheck')
                : get_string('invalid', 'assignsubmission_remotecheck');
            return get_string('summary', 'assignsubmission_remotecheck', $status);
        }

        // --- Build label map: p1..p9 -> human-readable label from assignment config.
        $paramlabels = [];
        for ($i = 1; $i <= 9; $i++) {
            $lbl = trim((string)$this->get_config('label' . $i));
            if ($lbl === '') {
                // Fallback like "Parameter 1", localized.
                $lbl = get_string('paramn', 'assignsubmission_remotecheck', $i);
            }
            $paramlabels[$i] = $lbl;
        }
        // Label for the formula "Result".
        $resultlabel = trim((string)$this->get_config('resultlabel'));
        if ($resultlabel === '') {
            $resultlabel = get_string('result', 'assignsubmission_remotecheck');
        }

        // --- Collect concise summary lines.
        $lines = [];

        // 1) Per-parameter checks from JSON: keys 'p1', 'p2', ...
        if (!empty($val['paramchecks']) && is_array($val['paramchecks'])) {
            foreach ($val['paramchecks'] as $pkey => $res) {
                // Extract index from pN
                if (!preg_match('/^p(\\d+)$/i', (string)$pkey, $m)) {
                    continue;
                }
                $idx = (int)$m[1];
                if ($idx < 1 || $idx > 9) {
                    continue;
                }
                $label    = $paramlabels[$idx];
                $ok       = !empty($res['ok']);
                $student  = isset($res['student'])  ? format_float($res['student'], 6)  : '';
                $expected = isset($res['expected']) ? format_float($res['expected'], 6) : '';
                $delta    = isset($res['absdiff'])  ? format_float($res['absdiff'], 6)  : '';
                $allowed  = isset($res['allowed'])  ? format_float($res['allowed'], 6)  : '';

                // Example: ✅ Flow rate: 12.30 vs 12.00 (Δ=0.30 ≤ 0.50)
                $lines[] = ($ok ? '✅ ' : '❌ ')
                    . s($label) . ': ' . $student . ' ' . get_string('vs', 'assignsubmission_remotecheck') . ' ' . $expected
                    . ' (' . get_string('deltaallowed', 'assignsubmission_remotecheck',
                            (object)['delta' => $delta, 'allowed' => $allowed]) . ')';
            }
        }

        // 2) Result (formula) check.
        if (!empty($val['resultcheck'])) {
            if (!empty($val['resultcheck']['error'])) {
                $lines[] = '❌ ' . s($resultlabel) . ': '
                    . get_string('formulaerror', 'assignsubmission_remotecheck') . ' — '
                    . s((string)$val['resultcheck']['error']);
            } else {
                $r        = $val['resultcheck'];
                $ok       = !empty($r['ok']);
                $student  = isset($r['student'])  ? format_float($r['student'], 6)  : '';
                $expected = isset($r['expected']) ? format_float($r['expected'], 6) : '';
                $delta    = isset($r['absdiff'])  ? format_float($r['absdiff'], 6)  : '';
                $allowed  = isset($r['allowed'])  ? format_float($r['allowed'], 6)  : '';

                $lines[] = ($ok ? '✅ ' : '❌ ')
                    . s($resultlabel) . ': ' . $student . ' ' . get_string('vs', 'assignsubmission_remotecheck') . ' ' . $expected
                    . ' (' . get_string('deltaallowed', 'assignsubmission_remotecheck',
                            (object)['delta' => $delta, 'allowed' => $allowed]) . ')';
            }
        }

        // 3) Optional remote comparison (if present in JSON).
        if (!empty($val['remotecompare'])) {
            $r        = $val['remotecompare'];
            $ok       = !empty($r['ok']);
            $student  = isset($r['student']) ? format_float($r['student'], 6) : '';
            $remote   = isset($r['remote'])  ? format_float($r['remote'], 6)  : '';
            $delta    = isset($r['absdiff']) ? format_float($r['absdiff'], 6) : '';
            $allowed  = isset($r['allowed']) ? format_float($r['allowed'], 6) : '';

            $lines[] = ($ok ? '✅ ' : '❌ ')
                . get_string('remotecompare', 'assignsubmission_remotecheck')
                . ': ' . $student . ' ' . get_string('vs', 'assignsubmission_remotecheck') . ' ' . $remote
                . ' (' . get_string('deltaallowed', 'assignsubmission_remotecheck',
                        (object)['delta' => $delta, 'allowed' => $allowed]) . ')';
        }

        if (empty($lines)) {
            // Fallback: legacy valid/invalid line.
            $status = !empty($rec->isvalid)
                ? get_string('valid', 'assignsubmission_remotecheck')
                : get_string('invalid', 'assignsubmission_remotecheck');
            return get_string('summary', 'assignsubmission_remotecheck', $status);
        }

        // Keep the summary compact; show a small number of bullets, reveal full in detail view.
        $maxlines = 20;
        if (count($lines) > $maxlines) {
            $lines = array_slice($lines, 0, $maxlines);
            $showviewlink = true;
        }

        $o = html_writer::start_tag('ul', ['class' => 'remotecheck-summarylist']);
        foreach ($lines as $line) {
            $o .= html_writer::tag('li', $line);
        }
        $o .= html_writer::end_tag('ul');

        return $o;
    }

    // public function view_summary(stdClass $submission, &$showviewlink) {
    //     global $DB;

    //     $showviewlink = false;
    //     $rec = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id]);
    //     if (!$rec) {
    //         return get_string('nosubmission', 'assignsubmission_remotecheck');
    //     }
    //     $status = $rec->isvalid ? get_string('valid', 'assignsubmission_remotecheck') : get_string('invalid', 'assignsubmission_remotecheck');
    //     return get_string('summary', 'assignsubmission_remotecheck', $status);
    // }

    public function view(stdClass $submission) {
        global $DB;

        $rec = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id]);
        if (!$rec) {
            return '';
        }
        $val = json_decode($rec->validationjson, true) ?: [];

        $o  = html_writer::start_tag('div', ['class' => 'remotecheck-summary']);
        // Display label as 'Item' (UI wording).
        $o .= html_writer::tag('div', get_string('item', 'assignsubmission_remotecheck') . ': ' . s($rec->buildingaddress ?? ''));
        $o .= html_writer::tag('div', get_string('validity', 'assignsubmission_remotecheck') . ': ' .
            ($rec->isvalid ? get_string('valid', 'assignsubmission_remotecheck') : get_string('invalid', 'assignsubmission_remotecheck')));

        if (!empty($val['paramchecks'])) {
            $o .= html_writer::tag('h4', get_string('paramchecks', 'assignsubmission_remotecheck'));
            $o .= html_writer::start_tag('ul');
            foreach ($val['paramchecks'] as $name => $res) {
                $line = s($name) . ': ' . $res['student'] . ' vs ' . $res['expected'] . ' ' .
                    ($res['ok'] ? get_string('ok', 'assignsubmission_remotecheck') : get_string('mismatch', 'assignsubmission_remotecheck'));
                $o .= html_writer::tag('li', $line);
            }
            $o .= html_writer::end_tag('ul');
        }

        if (!empty($val['resultcheck'])) {
            if (empty($val['resultcheck']['error'])) {
                $r = $val['resultcheck'];
                $o .= html_writer::tag('div',
                    get_string('result', 'assignsubmission_remotecheck') . ': ' . $r['student'] .
                    ' vs expected ' . $r['expected'] .
                    ' (' . ($r['ok'] ? get_string('ok', 'assignsubmission_remotecheck') : get_string('mismatch', 'assignsubmission_remotecheck')) . ')'
                );
            } else {
                $o .= html_writer::tag('div',
                    get_string('formulaerror', 'assignsubmission_remotecheck') . ': ' . s($val['resultcheck']['error']));
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
        // If random item is enabled, do not require the posted select value.
        if (!$this->random_item_enabled() && empty($data->remotecheck_buildingid)) {
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
     * Copy the plugin-specific submission data from a previous submission (new attempts).
     */
    public function copy_submission(stdClass $oldsubmission, stdClass $submission) {
        global $DB;

        $old = $DB->get_record('assignsubmission_remotecheck', ['submission' => $oldsubmission->id]);
        if (!$old) {
            return true; // Nothing to copy; not an error.
        }

        $new = new stdClass();
        $new->assignment      = $this->assignment->get_instance()->id;
        $new->submission      = $submission->id;
        $new->buildingid      = $old->buildingid;
        $new->buildingaddress = $old->buildingaddress;
        for ($i = 1; $i <= 9; $i++) {
            $new->{'param' . $i} = $old->{'param' . $i};
        }
        $new->studentresult   = $old->studentresult;
        $new->isvalid         = $old->isvalid;
        $new->validationjson  = $old->validationjson;
        $new->timecreated     = time();
        $new->timemodified    = time();

        $DB->insert_record('assignsubmission_remotecheck', $new);
        return true;
    }

    /**
     * Delete this plugin's data when a student removes their submission.
     */
    public function remove(stdClass $submission): bool {
        global $DB;

        if ($rec = $DB->get_record('assignsubmission_remotecheck', ['submission' => $submission->id], 'id')) {
            $DB->delete_records('assignsubmission_remotecheck_errs', ['remotecheckid' => $rec->id]);
        }
        $DB->delete_records('assignsubmission_remotecheck', ['submission' => $submission->id]);
        return true;
    }

    /**
     * The assignment instance is being deleted; remove all plugin data for it.
     */
    public function delete_instance(): bool {
        global $DB;

        $assignmentid = $this->assignment->get_instance()->id;
        $records = $DB->get_records('assignsubmission_remotecheck', ['assignment' => $assignmentid], '', 'id');
        if ($records) {
            list($insql, $params) = $DB->get_in_or_equal(array_keys($records));
            $DB->delete_records_select('assignsubmission_remotecheck_errs', 'remotecheckid ' . $insql, $params);
        }
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
        for ($i = 1; $i <= 9; $i++) {
            $v = $rec->{'param' . $i} ?? null;
            if ($v !== null && $v !== '') {
                return false;
            }
        }
        return !($rec->studentresult !== null && $rec->studentresult !== '');
    }

    /**
     * Choose a stable item per (assignment, user) without DB writes.
     * Returns the key from $addresses (or null if list is empty).
     */
    private function deterministic_building_for_user(int $assignid, int $userid, array $addresses): ?int {
        if (empty($addresses)) {
            return null;
        }
        $keys  = array_keys($addresses);
        $index = crc32($assignid . ':' . $userid) % count($keys);
        return (int)$keys[$index];
    }

    /**
     * (Optional) True-random pick if you prefer that approach.
     */
    private function allocate_random_building(array $addresses): ?int {
        if (empty($addresses)) {
            return null;
        }
        $keys = array_keys($addresses);
        $k    = array_rand($keys);
        return (int)$keys[$k];
    }

    /**
     * Is random item selection enabled for this assignment?
     * Reads the new key 'randomitem' and falls back to legacy 'randombuilding'.
     */
    private function random_item_enabled(): bool {
        $v = $this->get_config('randomitem');
        if ($v === false || $v === null) {
            $v = $this->get_config('randombuilding'); // Backward compatibility.
        }
        return (int)$v === 1;
    }

    public function is_enabled(): bool {
        return !empty($this->get_config('enabled'));
    }

    public function supports_locking(): bool { return true; }


    public function lock($submission, stdClass $flags) {
        // No plugin-specific action needed; acknowledging the lock is enough.
        return true; // Returning a value is fine; parent has no declared return type.
    }


    // Match core signature: unlock($submission, stdClass $flags)
    public function unlock($submission, stdClass $flags) {
        // No plugin-specific action needed for unlock.
        return true;
    }
 


    // public function lock(stdClass $submission): bool { return true; }     // no-op
    // public function unlock(stdClass $submission): bool { return true; }   // no-op

    private function is_locked(?stdClass $submission): bool {
            // Only lock when the submit-button workflow is enabled.
            $requirebutton = !empty($this->assignment->get_instance()->submissiondrafts);
            if (!$requirebutton) {
                return false; // Drafts OFF: never treat as locked, even if status='submitted'.
            }

            // Locked only after the student actually submits for grading.
            return !empty($submission)
                && !empty($submission->status)
                && $submission->status === 'submitted';
            // (If status becomes 'reopened', core intends it to be editable again.)
    }
}