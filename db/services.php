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
 * External function definitions for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_coassemble_update_course' => [
        'classname' => 'mod_coassemble\external\update_course',
        'methodname' => 'execute',
        'description' => 'Persist the Coassemble course id/title reported by the builder embed.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/coassemble:author',
    ],
    'mod_coassemble_update_progress' => [
        'classname' => 'mod_coassemble\external\update_progress',
        'methodname' => 'execute',
        'description' => 'Mirror the current user\'s authoritative Coassemble tracking into Moodle.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/coassemble:view',
    ],
];
