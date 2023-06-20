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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Contains Plagiarism plugin specific functions called by Modules.
 * @package   plagiarism_plagkhal
 * @copyright 2023 plagkhal
 * @author    Masha Khalyavina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Get global class.
global $CFG;
require_once($CFG->dirroot . '/plagiarism/lib.php');

// Get helper methods.
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_pluginconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_moduleconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/constants/plagiarism_plagkhal.constants.php');

require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_assignmodule.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_utils.class.php');

require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/exceptions/plagiarism_plagkhal_authexception.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/exceptions/plagiarism_plagkhal_exception.class.php');

require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_submissiondisplay.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_logs.class.php');
/**
 * Contains Plagiarism plugin specific functions called by Modules.
 */
class plagiarism_plugin_plagkhal extends plagiarism_plugin {
    /**
     * hook to allow plagiarism specific information to be displayed beside a submission
     * @param array $linkarray contains all relevant information for the plugin to generate a link
     * @return string displayed output
     */
    public function get_links($linkarray) {
        return plagiarism_plagkhal_submissiondisplay::output($linkarray);
    }

    /**
     * hook to save plagiarism specific settings on a module settings page
     * @param stdClass $data form data
     */
    public function save_form_elements($data) {
        // Check if plugin is configured and enabled.
        if (empty($data->modulename) || !plagiarism_plagkhal_pluginconfig::is_plugin_configured('mod_' . $data->modulename)) {
            return;
        }

        // Save settings to plagkhal.
        $cl = new plagiarism_plagkhal_comms();
        $updatedata = array(
            'tempCourseModuleId' => isset($data->plagiarism_plagkhal_tempcmid) ? $data->plagiarism_plagkhal_tempcmid : null,
            'courseModuleId' => $data->coursemodule,
            'name' => $data->name,
            'moduleName' => $data->modulename,
        );
        $cl->upsert_course_module($updatedata);

        try {
            // Get plagkhal api course module settings.
            $cl = new plagiarism_plagkhal_comms();

            plagiarism_plagkhal_moduleconfig::set_module_config(
                $data->coursemodule,
                $data->plagiarism_plagkhal_enable,
                isset($data->plagiarism_plagkhal_draftsubmit) ? $data->plagiarism_plagkhal_draftsubmit : 0,
                isset($data->plagiarism_plagkhal_reportgen) ? $data->plagiarism_plagkhal_reportgen : 0,
                $data->plagiarism_plagkhal_allowstudentaccess
            );
        } catch (plagiarism_plagkhal_exception $ex) {
            $errormessage = get_string('clfailtosavedata', 'plagiarism_plagkhal');
            plagiarism_plagkhal_logs::add($errormessage . ': ' . $ex->getMessage(), 'API_ERROR');
            throw new moodle_exception($errormessage);
        } catch (plagiarism_plagkhal_auth_exception $ex) {
            throw new moodle_exception(get_string('clinvalidkeyorsecret', 'plagiarism_plagkhal'));
        }
    }

    /**
     * If plugin is enabled then Show the plagkhal settings form.
     *
     * TODO: This code needs to be moved for 4.3 as the method will be completely removed from core.
     * See https://tracker.moodle.org/browse/MDL-67526
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $context
     * @param string $modulename
     */
    public function get_form_elements_module($mform, $context, $modulename = "") {
        global $DB, $CFG;
        // This is a bit of a hack and untidy way to ensure the form elements aren't displayed,
        // twice. This won't be needed once this method goes away.
        // TODO: Remove once this method goes away.
        static $settingsdisplayed;
        if ($settingsdisplayed) {
            return;
        }

        if (has_capability('plagiarism/plagkhal:enable', $context)) {

            // Return no form if the plugin isn't configured or not enabled.
            if (empty($modulename) || !plagiarism_plagkhal_pluginconfig::is_plugin_configured($modulename)) {
                return;
            }

            // plagkhal Settings.
            $mform->addElement(
                'header',
                'plagiarism_plagkhal_defaultsettings',
                get_string('clscoursesettings', 'plagiarism_plagkhal')
            );

            // Database settings.
            $mform->addElement(
                'advcheckbox',
                'plagiarism_plagkhal_enable',
                get_string('clenable', 'plagiarism_plagkhal')
            );

            // Add draft submission properties only if exists.
            if ($mform->elementExists('submissiondrafts')) {
                $mform->addElement(
                    'advcheckbox',
                    'plagiarism_plagkhal_draftsubmit',
                    get_string("cldraftsubmit", "plagiarism_plagkhal")
                );
                $mform->addHelpButton(
                    'plagiarism_plagkhal_draftsubmit',
                    'cldraftsubmit',
                    'plagiarism_plagkhal'
                );
                $mform->disabledIf(
                    'plagiarism_plagkhal_draftsubmit',
                    'submissiondrafts',
                    'eq',
                    0
                );
            }

            // Add due date properties only if exists.
            if ($mform->elementExists('duedate')) {
                $genoptions = array(
                    0 => get_string('clgenereportimmediately', 'plagiarism_plagkhal'),
                    1 => get_string('clgenereportonduedate', 'plagiarism_plagkhal')
                );
                $mform->addElement(
                    'select',
                    'plagiarism_plagkhal_reportgen',
                    get_string("clreportgenspeed", "plagiarism_plagkhal"),
                    $genoptions
                );
            }

            $mform->addElement(
                'advcheckbox',
                'plagiarism_plagkhal_allowstudentaccess',
                get_string('clallowstudentaccess', 'plagiarism_plagkhal')
            );

            $cmid = optional_param('update', null, PARAM_INT);
            $savedvalues = $DB->get_records_menu('plagiarism_plagkhal_config', array('cm' => $cmid), '', 'name,value');
            if (count($savedvalues) > 0) {
                // Add check for a new Course Module (for lower versions).
                $mform->setDefault(
                    'plagiarism_plagkhal_enable',
                    isset($savedvalues['plagiarism_plagkhal_enable']) ? $savedvalues['plagiarism_plagkhal_enable'] : 0
                );

                $draftsubmit = isset($savedvalues['plagiarism_plagkhal_draftsubmit']) ?
                    $savedvalues['plagiarism_plagkhal_draftsubmit'] : 0;

                $mform->setDefault('plagiarism_plagkhal_draftsubmit', $draftsubmit);
                if (isset($savedvalues['plagiarism_plagkhal_reportgen'])) {
                    $mform->setDefault('plagiarism_plagkhal_reportgen', $savedvalues['plagiarism_plagkhal_reportgen']);
                }
                if (isset($savedvalues['plagiarism_plagkhal_allowstudentaccess'])) {
                    $mform->setDefault(
                        'plagiarism_plagkhal_allowstudentaccess',
                        $savedvalues['plagiarism_plagkhal_allowstudentaccess']
                    );
                }
            } else {
                $mform->setDefault('plagiarism_plagkhal_enable', false);
                $mform->setDefault('plagiarism_plagkhal_draftsubmit', 0);
                $mform->setDefault('plagiarism_plagkhal_reportgen', 0);
                $mform->setDefault('plagiarism_plagkhal_allowstudentaccess', 0);
            }

            $settingslinkparams = "?";
            $addparam = optional_param('add', null, PARAM_TEXT);
            $courseid = optional_param('course', 0, PARAM_INT);
            $isnewactivity = isset($addparam) && $addparam != "0";
            if ($isnewactivity) {
                $cmid = plagiarism_plagkhal_utils::get_plagkhal_temp_course_module_id("$courseid");
                $mform->addElement(
                    'hidden',
                    'plagiarism_plagkhal_tempcmid',
                    "$cmid"

                );
                // Need to set type for Moodle's older version.
                $mform->setType('plagiarism_plagkhal_tempcmid', PARAM_INT);
                $settingslinkparams = $settingslinkparams . "isnewactivity=$isnewactivity&courseid=$courseid&";
            }

            $settingslinkparams = $settingslinkparams . "cmid=$cmid&modulename=$modulename";

            $btn = plagiarism_plagkhal_utils::get_plagkhal_settings_button_link($settingslinkparams, false, $cmid);
            $mform->addElement('html', $btn);

            $settingsdisplayed = true;
        }
    }

    /**
     * hook to allow a disclosure to be printed notifying users what will happen with their submission
     * @param int $cmid - course module id
     * @return string
     */
    public function print_disclosure($cmid) {
        global $DB, $USER;

        // Get course module.
        $cm = get_coursemodule_from_id('', $cmid);

        // Get course module plagkhal settings.
        $clmodulesettings = $DB->get_records_menu(
            'plagiarism_plagkhal_config',
            array('cm' => $cmid),
            '',
            'name,value'
        );

        // Check if plagkhal plugin is enabled for this module.
        $moduleclenabled = plagiarism_plagkhal_pluginconfig::is_plugin_configured('mod_' . $cm->modname);
        if (empty($clmodulesettings['plagiarism_plagkhal_enable']) || empty($moduleclenabled)) {
            return "";
        }

        $config = plagiarism_plagkhal_pluginconfig::admin_config();

        $isuseragreed = plagiarism_plagkhal_dbutils::is_user_eula_uptodate($USER->id);

        if (!$isuseragreed) {
            if (isset($config->plagiarism_plagkhal_studentdisclosure)) {
                $clstudentdisclosure = $config->plagiarism_plagkhal_studentdisclosure;
            } else {
                $clstudentdisclosure = get_string('clstudentdisclosuredefault', 'plagiarism_plagkhal');
            }
        } else {
            $clstudentdisclosure = get_string('clstudentdagreedtoeula', 'plagiarism_plagkhal');
        }

        $contents = format_text($clstudentdisclosure, FORMAT_MOODLE, array("noclean" => true));
        if (!$isuseragreed) {
            $checkbox = "<input type='checkbox' id='cls_student_disclosure'>" .
                "<label for='cls_student_disclosure' class='plagkhal-student-disclosure-checkbox'>$contents</label>";
            $output = html_writer::tag('div', $checkbox, array('class' => 'plagkhal-student-disclosure '));
            $output .= html_writer::tag(
                'script',
                "(function disableInput() {" .
                    "setTimeout(() => {" .
                    "var checkbox = document.getElementById('cls_student_disclosure');" .
                    "var btn = document.getElementById('id_submitbutton');" .
                    "btn.disabled = true;" .
                    "var intrval = setInterval(() => {" .
                    "if(checkbox.checked){" .
                    "btn.disabled = false;" .
                    "}else{" .
                    "btn.disabled = true;" .
                    "}" .
                    "}, 1000)" .
                    "}, 500);" .
                    "}());",
                null
            );
        } else {
            $output = html_writer::tag('div', $contents, array('class' => 'plagkhal-student-disclosure'));
        }

        return $output;
    }

    /**
     * hook to allow status of submitted files to be updated - called on grading/report pages.
     * @param object $course - full Course object
     * @param object $cm - full cm object
     */
    public function update_status($course, $cm) {
        // Called at top of submissions/grading pages - allows printing of admin style links or updating status.
    }
}

/**
 * Add the plagkhal settings form to an add/edit activity page.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 * @return type
 */
/**
 * @var mixed $course
 */
function plagiarism_plagkhal_coursemodule_standard_elements($formwrapper, $mform) {
    $plagkhalplugin = new plagiarism_plugin_plagkhal();
    $course = $formwrapper->get_course();
    $context = context_course::instance($course->id);
    $modulename = $formwrapper->get_current()->modulename;

    $plagkhalplugin->get_form_elements_module(
        $mform,
        $context,
        isset($modulename) ? 'mod_' . $modulename : ''
    );
}

/**
 * Handle saving data from the plagkhal settings form.
 *
 * @param stdClass $data
 * @param stdClass $course
 */
function plagiarism_plagkhal_coursemodule_edit_post_actions($data, $course) {
    $plagkhalplugin = new plagiarism_plugin_plagkhal();

    $plagkhalplugin->save_form_elements($data);

    return $data;
}
