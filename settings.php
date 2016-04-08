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

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
        $settings = new admin_settingpage('local_reporting', get_string('pluginname', 'local_reporting'));

        $settings->add(new admin_setting_configtextarea(
                'local_reporting_header_text',
                new lang_string('setuptitle', 'local_reporting'),
                new lang_string('setupdescription', 'local_reporting'),
                ''
        ));

        $ADMIN->add('localplugins', $settings);
}

