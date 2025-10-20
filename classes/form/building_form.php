<?php
namespace assignsubmission_remotecheck\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class building_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata ?? [];
        $labels = $customdata['labels'] ?? [];
        $addresslabel = $customdata['addresslabel'] ?? get_string('building', 'assignsubmission_remotecheck');

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'address', $addresslabel);
        $mform->setType('address', PARAM_TEXT);
        $mform->addRule('address', null, 'required', null, 'client');

        for ($i = 1; $i <= 9; $i++) {
            $label = $labels['p'.$i] ?? get_string('paramn', 'assignsubmission_remotecheck', $i);
            $mform->addElement('text', 'param'.$i, $label);
            $mform->setType('param'.$i, PARAM_FLOAT);
        }

        $resultlabel = $labels['result'] ?? get_string('result', 'assignsubmission_remotecheck');
        $mform->addElement('text', 'calcresult', $resultlabel);
        $mform->setType('calcresult', PARAM_FLOAT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}