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
 * plagkhal Plagiarism Plugin - Handle Resubmit Files
 * @package   plagiarism_plagkhal
 * @copyright 2022 plagkhal
 * @author    Gil Cohen <gilc@plagkhal.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_plagkhal\task;

use stdClass;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/plagiarism/plagkhal/constants/plagiarism_plagkhal.constants.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/enums/plagiarism_plagkhal_enums.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_logs.class.php');

/**
 * plagkhal Plagiarism Plugin - Handle Resubmit Files
 */
class plagiarism_plagkhal_resubmittedreports extends \core\task\scheduled_task {
    /**
     * Get scheduler name, this will be shown to admins on schedulers dashboard.
     */
    public function get_name() {
        return get_string('clsendresubmissionsfiles', 'plagiarism_plagkhal');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_submissions.class.php');
        require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_comms.class.php');
        require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_assignmodule.class.php');

        $this->handle_resubmitted_files();
    }

    /**
     * Handle and change the score of resubmitted files.
     */
    private function handle_resubmitted_files() {
        global $DB;

        $plagkhalcomms = new \plagiarism_plagkhal_comms();
        $cursor = '';
        $canloadmoredata = true;

        while ($canloadmoredata) {
            if (!\plagiarism_plagkhal_comms::test_plagkhal_connection('scheduler_task')) {
                return;
            }

            $succeedids = [];
            $response = $plagkhalcomms->get_resubmit_reports_ids($cursor);
            if (!is_object($response) || !isset($response->resubmitted) || count($response->resubmitted) == 0) {
                break;
            }

            $cursor = $response->cursor;
            $resubmittedmodel = $response->resubmitted;
            $canloadmoredata = $response->canLoadMore;
            $oldids = array_column($resubmittedmodel, 'oldScanId');

            $currentdbresults = [];

            /* Get all the scans from db with the ids of the 'response' old ids */
            $dbrecordset = $DB->get_recordset_list('plagiarism_plagkhal_files', 'externalid', $oldids);
            if (!$dbrecordset->valid()) {
                break;
            }

            /* Getting the result by the consition the all the external ids must contains in $oldids */
            foreach ($dbrecordset as $result) {
                $currentdbresults[] = $result;
            }

            $dbrecordset->close();

            if (count($currentdbresults) == 0) {
                break;
            }

            $timestamp = time();

            /* For each db result - Replace the new data */
            foreach ($currentdbresults as $currentresult) {
                if ($currentresult->externalid == null) {
                    continue;
                }

                /* Get the plagkhal db entity with the old id */
                $curr = new stdClass();
                foreach ($resubmittedmodel as $element) {
                    if ($element->oldScanId == $currentresult->externalid) {
                        $curr = $element;
                        break;
                    }
                }

                if (!isset($curr->oldScanId) || $curr->oldScanId == null) {
                    continue;
                }

                $isupdated = false;
                if ($curr->status == \plagiarism_plagkhal_reportstatus::SCORED) {
                    $currentresult->externalid = $curr->newScanId;
                    $currentresult->similarityscore = $curr->plagiarismScore;
                    $currentresult->lastmodified = $timestamp;
                    $isupdated = true;
                    /* Update in the DB */
                } else if ($curr->status == \plagiarism_plagkhal_reportstatus::ERROR) {
                    $currentresult->similarityscore = null;
                    $currentresult->statuscode = "error";
                    $currentresult->errormsg = $curr->errorMessage;
                    $isupdated = true;
                }
                if ($isupdated) {
                    if (!$DB->update_record('plagiarism_plagkhal_files',  $currentresult)) {
                        \plagiarism_plagkhal_logs::add(
                            "Update resubmitted failed (old scan id: " . $curr->oldScanId . ", new scan id: "
                                . $curr->newScanId . "with status of " . $curr->statusCode . ") - ",
                            "UPDATE_RECORD_FAILED"
                        );
                    } else {
                        array_push($succeedids,  $curr->oldScanId);
                    }
                }
            }
            /* Send request with ids who successfully changed in moodle db to deletion in the Google data store */
            if (count($succeedids) > 0) {
                $plagkhalcomms->delete_resubmitted_ids($succeedids);
            }
        }
    }
}
