<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Activity instance form for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form.
 */
class mod_coassemble_mod_form extends moodleform_mod {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'coassembleheader', get_string('coassembleheader', 'mod_coassemble'));
        $mform->setExpanded('coassembleheader');

        $mform->addElement('static', 'authoringnote', '', get_string('authoringnote', 'mod_coassemble'));

        $flows = [
            '' => get_string('flow_scratch', 'mod_coassemble'),
            'ai' => get_string('flow_ai', 'mod_coassemble'),
            'document' => get_string('flow_document', 'mod_coassemble'),
            'presentation' => get_string('flow_presentation', 'mod_coassemble'),
        ];
        $mform->addElement('select', 'flow', get_string('flow', 'mod_coassemble'), $flows);
        $mform->addHelpButton('flow', 'flow', 'mod_coassemble');
        $mform->setDefault('flow', '');

        $this->standard_grading_coursemodule_elements();

        $grademethods = [
            0 => get_string('grademethod_progress', 'mod_coassemble'),
            1 => get_string('grademethod_score', 'mod_coassemble'),
        ];
        $grademethod = $mform->createElement(
            'select',
            'grademethod',
            get_string('grademethod', 'mod_coassemble'),
            $grademethods
        );
        if ($mform->elementExists('gradepass')) {
            $mform->insertElementBefore($grademethod, 'gradepass');
        } else {
            $mform->addElement($grademethod);
        }
        $mform->setDefault('grademethod', 0);
        $mform->addHelpButton('grademethod', 'grademethod', 'mod_coassemble');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Custom completion rules.
     *
     * @return array
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        // Moodle 4.3+ suffixes completion elements on the default-completion form.
        $suffix = method_exists($this, 'get_suffix') ? $this->get_suffix() : '';
        $mform->addElement(
            'checkbox',
            'completioncourse' . $suffix,
            '',
            get_string('completioncourse', 'mod_coassemble')
        );
        $mform->setDefault('completioncourse' . $suffix, 1);
        return ['completioncourse' . $suffix];
    }

    /**
     * Whether the custom completion rule is enabled in submitted form data.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = method_exists($this, 'get_suffix') ? $this->get_suffix() : '';
        return !empty($data['completioncourse' . $suffix]);
    }

    /**
     * Enrich defaults.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        if (empty($defaultvalues['flow'])) {
            $defaultvalues['flow'] = '';
        }
        if (!isset($defaultvalues['completioncourse'])) {
            $defaultvalues['completioncourse'] = 1;
        }
    }
}
