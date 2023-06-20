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
 * plugin configurations helpers methods
 * @package   plagiarism_plagkhal
 * @copyright 2023 plagkhal
 * @author    Masha Khalyavina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * plugin configurations helpers methods
 */
class plagiarism_plagkhal_pluginconfig {
    /**
     * Check module configuration settings for the plagkhal plagiarism plugin
     * @param string $modulename
     * @return bool if plugin is configured and enabled return true, otherwise false.
     */
    public static function is_plugin_configured($modulename) {
        $config = self::admin_config();

        if (
            empty($config->plagiarism_plagkhal_key) ||
            empty($config->plagiarism_plagkhal_apiurl) ||
            empty($config->plagiarism_plagkhal_secret)
        ) {
            // Plugin not configured.
            return false;
        }

        $moduleconfigname = 'plagiarism_plagkhal_' . $modulename;
        if (!isset($config->$moduleconfigname) || $config->$moduleconfigname !== '1') {
            // Plugin not enabled for this module.
            return false;
        }

        return true;
    }

    /**
     * Get the admin config settings for the plugin
     * @return mixed plagkhal plugin admin configurations
     */
    public static function admin_config() {
        return get_config('plagiarism_plagkhal');
    }

    /**
     * get admin config saved database properties
     * @return array admin config properties for plagkhal plugin
     */
    public static function admin_config_properties() {
        return array(
            "version",
            "enabled",
            "plagkhal_use",
            "plagiarism_plagkhal_apiurl",
            "plagiarism_plagkhal_key",
            "plagiarism_plagkhal_secret",
            "plagiarism_plagkhal_jwttoken",
            "plagiarism_plagkhal_mod_assign",
            "plagiarism_plagkhal_mod_forum",
            "plagiarism_plagkhal_mod_workshop",
            "plagiarism_plagkhal_mod_quiz",
            'plagiarism_plagkhal_studentdisclosure'
        );
    }

    /**
     * Set a config property value for the plugin admin settings.
     * @param stdClass $data
     * @param string $prop property name
     */
    public static function set_admin_config($data, $prop) {
        if (strpos($prop, 'plagkhal')) {
            $dbfield = $prop;
        } else {
            $dbfield = "plagiarism_plagkhal_" . $prop;
        }

        if (isset($data->$prop)) {
            set_config($dbfield, $data->$prop, 'plagiarism_plagkhal');
        }
    }
}
