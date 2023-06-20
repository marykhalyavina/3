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
 * lib.php - Contains Plagiarism plugin specific functions called by Modules.
 * @package   plagiarism_plagkhal
 * @copyright 2023 plagkhal
 * @author    Masha Khalyavina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Constants.
define('PLAGIARISM_plagkhal_DEFAULT_MODULE_CMID', 0);
$clsupportedsubmissiontypes = array('file', 'text_content', 'forum_post', 'quiz_answer');
define('PLAGIARISM_plagkhal_SUPPORTED_SUBMISSION_TYPES', $clsupportedsubmissiontypes);


// Max file size 25mb.
define('PLAGIARISM_plagkhal_MAX_FILE_UPLOAD_SIZE', 26214400);
define('PLAGIARISM_plagkhal_CRON_QUERY_LIMIT', 100);
define('PLAGIARISM_plagkhal_CRON_MAX_DATA_LOOP', 256);
define('PLAGIARISM_plagkhal_MAX_FILENAME_LENGTH', 180);
define('PLAGIARISM_plagkhal_LOGS_PREFIX', 'log_');



// plagkhal support file types.
$plagkhalacceptedfiles = array(
    // Textual.
    '.html', '.txt', '.csv', '.rtf', '.xml', '.htm',
    // Non-Textual.
    '.pdf', '.docx', '.doc', '.pptx', '.ppt', '.odt',
    '.chm', '.epub', '.odp', '.ppsx', '.pages', '.xlsx',
    '.xls', '.LaTeX',
    // Source code.
    '.py', '.go', '.cs', '.c', '.h', '.idc', '.cpp', '.hpp',
    '.cpp', '.hpp', '.cc', '.hh', '.java', '.js', '.swift',
    '.rb', '.pl', '.php', '.sh', '.m', '.scala',
    // OCR.
    '.gif', '.png', '.bmp', '.jpg', '.jpeg'
);
define('PLAGIARISM_plagkhal_ACCEPTED_FILES', $plagkhalacceptedfiles);
define('DEFAULT_DATABASE_plagkhalDB_ID', 'DEFAULT_DATABASE_plagkhalDB_ID');

// plagkhal retry array in seconds.
define('PLAGIARISM_plagkhal_RETRY', array(0, 2.5, 3, 5, 10));
define('PLAGIARISM_plagkhal_MAX_RETRY', 5);
define('PLAGIARISM_plagkhal_DEFUALT_EULA_VERSION', '2023032700');
define('PLAGIARISM_plagkhal_EULA_FIELD_NAME', 'plagiarism_plagkhal_latesteulaversion ');
