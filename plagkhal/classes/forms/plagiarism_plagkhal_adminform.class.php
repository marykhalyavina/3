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
 * plagkhal_setupform.class.php - Plugin setup form for plagiarism_plagkhal component
 * @package   plagiarism_plagkhal
 * @copyright 2023 plagkhal
 * @author    Masha Khalyavina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/plagiarism/plagkhal/lib.php');
require_once($CFG->dirroot . '/lib/formslib.php');

require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_pluginconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_moduleconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/exceptions/plagiarism_plagkhal_authexception.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/exceptions/plagiarism_plagkhal_exception.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/exceptions/plagiarism_plagkhal_ratelimitexception.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/exceptions/plagiarism_plagkhal_undermaintenanceexception.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_logs.class.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/constants/plagiarism_plagkhal.constants.php');
require_once($CFG->dirroot . '/plagiarism/plagkhal/classes/plagiarism_plagkhal_utils.class.php');

/**
 * plagkhal admin setup form
 */
class plagiarism_plagkhal_adminform extends moodleform {
    /**
     * Define the form
     * */
    public function definition() {
        global $CFG;
        $mform = &$this->_form;

        // Plugin Configurations.
        $mform->addElement(
            'header',
            'plagiarism_plagkhal_adminconfigheader',
            get_string('cladminconfig', 'plagiarism_plagkhal', null, true)
        );
        $mform->addElement(
            'html',
            get_string('clpluginintro', 'plagiarism_plagkhal')
        );

        // Get all modules that support plagiarism plugin.
        $plagiarismmodules = array_keys(core_component::get_plugin_list('mod'));
        $supportedmodules = array('assign', 'forum', 'workshop', 'quiz');
        foreach ($plagiarismmodules as $module) {
            // For now we only support assignments.
            if (in_array($module, $supportedmodules) && plugin_supports('mod', $module, FEATURE_PLAGIARISM)) {
                array_push($supportedmodules, $module);
                $mform->addElement(
                    'advcheckbox',
                    'plagiarism_plagkhal_mod_' . $module,
                    get_string('clenablemodulefor', 'plagiarism_plagkhal', ucfirst($module == 'assign' ? 'Assignment' : $module))
                );
            }
        }

        $mform->addElement(
            'textarea',
            'plagiarism_plagkhal_studentdisclosure',
            get_string('clstudentdisclosure', 'plagiarism_plagkhal')
        );
        $mform->addHelpButton(
            'plagiarism_plagkhal_studentdisclosure',
            'clstudentdisclosure',
            'plagiarism_plagkhal'
        );

        // plagkhal Account Configurations.
        $mform->addElement(
            'header',
            'plagiarism_plagkhal_accountconfigheader',
            get_string('claccountconfig', 'plagiarism_plagkhal')
        );
        $mform->setExpanded('plagiarism_plagkhal_accountconfigheader');
        // Thos settings will be save on Moodle database.
        $mform->addElement(
            'text',
            'plagiarism_plagkhal_apiurl',
            get_string('clapiurl', 'plagiarism_plagkhal')
        );
        $mform->setType('plagiarism_plagkhal_apiurl', PARAM_TEXT);
        $mform->addElement(
            'text',
            'plagiarism_plagkhal_key',
            get_string('claccountkey', 'plagiarism_plagkhal')
        );
        $mform->setType('plagiarism_plagkhal_key', PARAM_TEXT);
        $mform->addElement(
            'passwordunmask',
            'plagiarism_plagkhal_secret',
            get_string('claccountsecret', 'plagiarism_plagkhal')
        );

        if (\plagiarism_plagkhal_comms::test_plagkhal_connection('admin_settings_page')) {
            $btn = plagiarism_plagkhal_utils::get_plagkhal_settings_button_link(null, true);
            $mform->addElement('html', $btn);
        }

        $this->add_action_buttons();
    }

    /**
     * form custom validations
     * @param mixed $data
     * @param mixed $files
     */
    public function validation($data, $files) {
        $newconfigsecret = $data["plagiarism_plagkhal_secret"];
        $newconfigkey = $data["plagiarism_plagkhal_key"];
        $newapiurl = $data["plagiarism_plagkhal_apiurl"];

        $config = plagiarism_plagkhal_pluginconfig::admin_config();
        if (
            isset($config->plagiarism_plagkhal_secret) &&
            isset($config->plagiarism_plagkhal_key) &&
            isset($config->plagiarism_plagkhal_apiurl)
        ) {
            $secret = $config->plagiarism_plagkhal_secret;
            $key = $config->plagiarism_plagkhal_key;
            $apiurl = $config->plagiarism_plagkhal_apiurl;

            if ($secret != $newconfigsecret || $key != $newconfigkey || $apiurl != $newapiurl) {
                try {
                    $cljwttoken = plagiarism_plagkhal_comms::login_to_plagkhal($newapiurl, $newconfigkey, $newconfigsecret, true);
                    if (isset($cljwttoken)) {
                        return array();
                    } else {
                        return (array)[
                            "plagiarism_plagkhal_secret" => get_string('clinvalidkeyorsecret', 'plagiarism_plagkhal')
                        ];
                    }
                } catch (plagiarism_plagkhal_exception $ex) {
                    switch ($ex->getCode()) {
                        case 404:
                            return (array)[
                                "plagiarism_plagkhal_secret" => get_string('clinvalidkeyorsecret', 'plagiarism_plagkhal')
                            ];
                            break;
                        case 0:
                            return (array)[
                                "plagiarism_plagkhal_apiurl" => $ex->getMessage()
                            ];
                            break;
                        default:
                            throw $ex;
                            break;
                    }
                } catch (plagiarism_plagkhal_auth_exception $ex) {
                    return (array)[
                        "plagiarism_plagkhal_secret" => get_string('clinvalidkeyorsecret', 'plagiarism_plagkhal')
                    ];
                }
            }
        } else {
            if (!isset($newconfigsecret) || !isset($newconfigkey) || empty($newconfigkey) || empty($newconfigsecret)) {
                return (array)[
                    "plagiarism_plagkhal_secret" => get_string('clinvalidkeyorsecret', 'plagiarism_plagkhal')
                ];
            }
        }
        return array();
    }

    /**
     * Init the form data form both DB and plagkhal API
     */
    public function init_form_data() {
        $cache = cache::make('core', 'config');
        $cache->delete('plagiarism_plagkhal');

        // Get moodle admin config.
        $plagiarismsettings = (array) plagiarism_plagkhal_pluginconfig::admin_config();

        if (
            !isset($plagiarismsettings['plagiarism_plagkhal_apiurl']) ||
            empty($plagiarismsettings['plagiarism_plagkhal_apiurl'])
        ) {
            $plagiarismsettings['plagiarism_plagkhal_apiurl'] = plagiarism_plagkhal_comms::plagkhal_api_url();
        }

        $cldbdefaultconfig = plagiarism_plagkhal_moduleconfig::get_modules_default_config();

        if (!isset($plagiarismsettings["plagiarism_plagkhal_studentdisclosure"])) {
            $plagiarismsettings["plagiarism_plagkhal_studentdisclosure"] =
                get_string('clstudentdisclosuredefault', 'plagiarism_plagkhal');
        }

        $this->set_data($plagiarismsettings);
    }

    /**
     * Display the form to admins
     */
    public function display() {
        ob_start();
        parent::display();
        $form = ob_get_contents();
        ob_end_clean();
        return $form;
    }

    /**
     * Save form data
     * @param stdClass $data
     */
    public function save(stdClass $data) {
        global $CFG;

        // Save admin settings.
        $configproperties = plagiarism_plagkhal_pluginconfig::admin_config_properties();
        foreach ($configproperties as $property) {
            plagiarism_plagkhal_pluginconfig::set_admin_config($data, $property);
        }

        // Check if plugin is enabled.
        $plagiarismmodules = array_keys(core_component::get_plugin_list('mod'));
        $pluginenabled = 0;
        foreach ($plagiarismmodules as $module) {
            if (plugin_supports('mod', $module, FEATURE_PLAGIARISM)) {
                $property = "plagiarism_plagkhal_mod_" . $module;
                $ismoduleenabled = (!empty($data->$property)) ? $data->$property : 0;
                if ($ismoduleenabled) {
                    $pluginenabled = 1;
                }
            }
        }

        // Set if plagkhal plugin is enabled.
        set_config('enabled', $pluginenabled, 'plagiarism_plagkhal');
        if ($CFG->branch < 39) {
            set_config('plagkhal_use', $pluginenabled, 'plagiarism');
        }

        $cache = cache::make('core', 'config');
        $cache->delete('plagiarism_plagkhal');
    }
}
