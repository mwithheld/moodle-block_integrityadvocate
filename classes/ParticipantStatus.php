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
 * IntegrityAdvocate class to represent participant status (valid, in progress, invalid id, invalid rules).
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

use block_integrityadvocate\MoodleUtility as ia_mu;

class PaticipantStatus {

    /** @var string String the IA API uses for a proctor session that is complete and valid. */
    const VALID = 'Valid';
    const VALID_INT = 0;

    /** @var string String the IA API uses for a proctor session that is started but not yet complete. */
    const INPROGRESS = 'In Progress';
    const INPROGRESS_INT = -1;

    /** @var string String the IA API uses for a proctor session that is complete but the presented ID card is invalid. */
    const INVALID_ID = 'Invalid (ID)';
    const INVALID_ID_INT = 1;

    /** @var string String the IA API uses for an overridden session status. */
    const INVALID_OVERRIDE = 'Invalid (Override)';
    const INVALID_OVERRIDE_INT = 3;

    /** @var string String the IA API uses for a proctor session that is complete but in participating the user broke 1+ rules.
     * See IA flags for details. */
    const INVALID_RULES = 'Invalid (Rules)';
    const INVALID_RULES_INT = 2;

    /**
     * Parse the IA participants status code against a whitelist of IntegrityAdvocate_Participant_Status::* constants.
     *
     * @param string $statusstring The status string from the API e.g. Valid, In Progress, etc.
     * @return int An integer representing the status matching one of the IntegrityAdvocate_Paticipant_Status::* constants.
     * @throws InvalidValueException
     */
    public static function parse_status_string(string $statusstring): int {
        $statusstringcleaned = \clean_param($statusstring, PARAM_TEXT);
        switch ($statusstringcleaned) {
            case self::INPROGRESS:
                $status = self::INPROGRESS_INT;
                break;
            case self::VALID:
                $status = self::VALID_INT;
                break;
            case self::INVALID_ID:
                $status = self::INVALID_ID_INT;
                break;
            case self::INVALID_OVERRIDE:
                $status = self::IINVALID_OVERRIDE_INT;
                break;
            case self::INVALID_RULES:
                $status = self::INVALID_RULES_INT;
                break;
            default:
                $error = 'Invalid participant review status value=' . serialize($statusstring);
                ia_mu::log($error);
                throw new \InvalidValueException($error);
        }

        return $status;
    }

    /**
     * Return if the status integer value is a valid one.
     *
     * @param int $statusint The integer value to check.
     * @return true if is a valid status integer representing In progress, Valid, Invalid ID, Invalid Rules.
     */
    public static function is_status_int(int $statusint): bool {
        return in_array(
                $statusint,
                array_keys(self::get_statuses()),
                true
        );
    }

    /**
     * Get an array of all statuses; key=int representing the status; value=language string representing the status.
     * Note the language string != string representation of value - see get_status_string() vs get_status_lang().
     *
     * @return array of all statuses
     */
    public static function get_statuses(): array {
        return array(
            self::INPROGRESS_INT => self::get_status_lang(self::INPROGRESS_INT),
            self::VALID_INT => self::get_status_lang(self::VALID_INT),
            self::INVALID_ID_INT => self::get_status_lang(self::INVALID_ID_INT),
            self::INVALID_RULES_INT => self::get_status_lang(self::INVALID_RULES_INT),
            self::INVALID_OVERRIDE_INT => self::get_status_lang(self::INVALID_OVERRIDE_INT),
        );
    }

    /**
     * Get an array of statuses that may be overridden; key=int representing the status; value=language string representing the status.
     * Note the language string != string representation of value - see get_status_string() vs get_status_lang().
     *
     * @return array of overridable statuses
     */
    public static function get_overriddable(): array {
        return array(
            self::VALID_INT => self::get_status_lang(self::VALID_INT),
            self::INVALID_OVERRIDE_INT => self::get_status_lang(self::INVALID_OVERRIDE_INT),
        );
    }

    /**
     * Get an array of statuses that are considered invalid; key=int representing the status; value=language string representing the status.
     * Note the language string != string representation of value - see get_status_string() vs get_status_lang().
     *
     * @return array of overridable statuses
     */
    public static function get_invalids(): array {
        $invalids = array();
        foreach (self::get_statuses() as $key => $val) {
            if (stripos($key, 'INVALID_') !== false) {
                $invalids[$key] = $val;
            }
        }
        return $invalids;
    }

    /**
     * Get the IA status constant (not the language string) representing the integer status.
     *
     * @param int $statusint The integer value to get the string for.
     * @return string The IA status constant representing the integer status
     * @throws \InvalidArgumentException
     * @throws \InvalidValueException
     */
    public static function get_status_string(int $statusint): string {
        switch ($statusint) {
            case self::INPROGRESS_INT:
                $status = self::INPROGRESS;
                break;
            case self::VALID_INT:
                $status = self::VALID;
                break;
            case self::INVALID_ID_INT:
                $status = self::INVALID_ID;
                break;
            case self::INVALID_OVERRIDE_INT:
                $status = self::IINVALID_OVERRIDE;
                break;
            case self::INVALID_RULES_INT:
                $status = self::INVALID_RULES;
                break;
            default:
                $error = 'Invalid participant review status value=' . $statusint;
                ia_mu::log($error);
                throw new \InvalidValueException($error);
        }

        return $status;
    }

    /**
     * Get the lang string representing the integer status.
     *
     * @param int $statusint The integer value to get the string for.
     * @return string The lang string representing the integer status.
     * @throws \InvalidArgumentException
     * @throws \InvalidValueException
     */
    public static function get_status_lang(int $statusint): string {
        switch ($statusint) {
            case self::INPROGRESS_INT:
                $status = \get_string('status_in_progress', \INTEGRITYADVOCATE_BLOCK_NAME);
                break;
            case self::VALID_INT:
                $status = \get_string('status_valid', \INTEGRITYADVOCATE_BLOCK_NAME);
                break;
            case self::INVALID_ID_INT:
                $status = \get_string('status_invalid_id', \INTEGRITYADVOCATE_BLOCK_NAME);
                break;
            case self::INVALID_OVERRIDE_INT:
                $status = \get_string('status_invalid_override', \INTEGRITYADVOCATE_BLOCK_NAME);
                break;
            case self::INVALID_RULES_INT:
                $status = \get_string('status_invalid_rules', \INTEGRITYADVOCATE_BLOCK_NAME);
                break;
            default:
                $error = 'Invalid participant review status value=' . $statusint;
                ia_mu::log($error);
                throw new \InvalidValueException($error);
        }

        return $status;
    }

}
