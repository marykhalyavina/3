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
 * settings.php - allows the admin to configure the plugin
 * @package   plagiarism_plagkhal
 * @author    Masha Khalyavina
 * @copyright 2023 plagkhal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/plagiarismlib.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/lib.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/forms/plagiarism_plagkhal_adminform.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_logs.class.php');

require_login();

admin_externalpage_setup('plagiarismplagkhal');

$context = context_system::instance();

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

$qpselectedtabid = optional_param('tab', "plagkhalconfiguration", PARAM_ALPHA);
$qpdate = optional_param('date', null, PARAM_ALPHANUMEXT);

$plagkhalsetupform = new plagiarism_plagkhal_adminform();

if ($plagkhalsetupform->is_cancelled()) {
    redirect(new moodle_url('/admin/category.php?category=plagiarism'));
}

$pagetabs = array();
$pagetabs[] = new tabobject(
    'plagkhalconfiguration',
    'settings.php',
    get_string('clpluginconfigurationtab', 'plagiarism_plagkhal'),
    get_string('clpluginconfigurationtab', 'plagiarism_plagkhal'),
    false
);

$pagetabs[] = new tabobject(
    'plagkhallogs',
    'settings.php?tab=plagkhallogs',
    get_string('cllogstab', 'plagiarism_plagkhal'),
    get_string('cllogstab', 'plagiarism_plagkhal'),
    false
);

switch ($qpselectedtabid) {
    case 'plagkhallogs':
        if (!is_null($qpdate)) {
            plagiarism_plagkhal_logs::displaylogs($qpdate);
        } else {
            echo $OUTPUT->header();
            $pagetabs[1]->selected = true;
            echo $OUTPUT->tabtree($pagetabs);
            echo $OUTPUT->heading(get_string('cllogsheading', 'plagiarism_plagkhal'));
            plagiarism_plagkhal_logs::displaylogs();
        }
        break;
    default:
        echo $OUTPUT->header();
        $pagetabs[0]->selected = true;
        echo $OUTPUT->tabtree($pagetabs);
        // Form data save flow.
        if (($data = $plagkhalsetupform->get_data()) && confirm_sesskey()) {
            $plagkhalsetupform->save($data);
            $output = $OUTPUT->notification(get_string('cladminconfigsavesuccess', 'plagiarism_plagkhal'), 'notifysuccess');
        }

        // Init form data.
        $plagkhalsetupform->init_form_data();

        echo $plagkhalsetupform->display();
        break;
}

echo $OUTPUT->footer();
