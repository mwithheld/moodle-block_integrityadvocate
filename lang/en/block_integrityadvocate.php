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
 * IntegrityAdvocate block English language translation
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Integrity Advocate';
$string['button_overview'] = 'Overview';
$string['cachedef_perrequest'] = 'Remember values during a single request';
$string['column_iadata'] = 'IntegrityAdvocate data';
$string['column_iaphoto'] = 'IntegrityAdvocate photo';
$string['completion_not_enabled'] = 'Completion tracking is not enabled on this site.';
$string['completion_not_enabled_course'] = 'Completion tracking is not enabled in this course.';
$string['config_apikey'] = 'Api Key';
$string['config_appid'] = 'Application Id';
$string['config_default_title'] = 'IntegrityAdvocate';
$string['created'] = 'First seen';
$string['disabled_editingmode'] = 'Course editing mode is enabled, so video monitoring is disabled';
$string['disabled_haseditcap'] = 'Disabled for non-students';
$string['disabled_notenrolled'] = 'Not available - you are not enrolled in this course';

$string['error_curlcloseia'] = 'Curl error closing the IA session';
$string['error_curlnoremoteinfo'] = 'Error: Got no remote IA participant info - check the API key and app id are valid';
$string['error_invalidappid'] = "Invalid Application Id - it is a code that looks a bit like this: c56a4180-65aa-42ec-a945-5fd21dec0538";
$string['error_noapikey'] = "No Api key is set";
$string['error_noappid'] = "No Application Id is set";
$string['error_notenrolled'] = "You are not enrolled in this course";
$string['error_quiz_showblocks'] = "This quiz is configured with &quot;Show blocks during quiz attempts&quot; = No.  To fix this, edit the quiz settings > Appearance > Show more...";

$string['flag_comment'] = 'Details';
$string['flag_capture'] = 'Captured photo';
$string['flag_errorcode'] = 'Error code';
$string['flag_type'] = 'Flag';
$string['flags_none'] = 'None';

$string['fullname'] = 'Full course name';

$string['integrityadvocate:addinstance'] = "Add new IntegrityAdvocate block";
$string['integrityadvocate:addinstance'] = 'Add a new IntegrityAdvocate block';
$string['integrityadvocate:myaddinstance'] = "Add a new IntegrityAdvocate block to the desired page";
$string['integrityadvocate:myaddinstance'] = 'Add a IntegrityAdvocate block to My home page';
$string['integrityadvocate:overview'] = 'View course overview of Integrity Advocate for all students';
$string['integrityadvocate:selfview'] = 'View own IntegrityAdvocate results';

$string['last_modified'] = 'Last modified';
$string['lastaccess'] = 'Last in course';
$string['no_blocks'] = 'No IntegrityAdvocate blocks are set up for your courses.';
$string['no_course'] = 'No matching course found';
$string['no_enrollment'] = 'This user is not enrolled in this course';
$string['no_ia_block'] = "No active IntegrityAdvocate block instance found for this activity";
$string['no_local_participants'] = 'No course participants found';
$string['no_modules_config_message'] = 'There are no modules found with activity completion enabled which have the IntegrityAdvocate block set up.';
$string['no_modules_message'] = 'No modules found.';
$string['no_remote_participants'] = 'No IA-side participants found';
$string['no_user'] = 'No matching user found';
$string['not_all_expected_set'] = 'Not all activities with completion have an "{$a}" date set.';
$string['now_indicator'] = 'NOW';

$string['overview'] = 'IntegrityAdvocate Overview';
$string['overview_flags'] = 'Flags';
$string['overview_session'] = '<h4>{$a}</h4>';
$string['overview_sessions'] = '<h3>Sessions for this user</h3>';
$string['overview_user_status'] = 'Latest status';
$string['overview_view_details'] = 'View details';

$string['privacy:metadata'] = 'The IntegrityAdvocate block only displays existing completion data.';
$string['privacy:metadata:block_integrityadvocate'] = 'This plugin stores no data in Moodle.  In order to integrate with a remote service, user data needs to be exchanged with that service.  See <a href="https://integrityadvocate.com/Home/Privacy?lang=en">Integrity Advocate Privacy</a> for more information.';
$string['privacy:metadata:block_integrityadvocate:cmid'] = 'Id number of the course module.';
$string['privacy:metadata:block_integrityadvocate:courseid'] = 'Id number of the course.';
$string['privacy:metadata:block_integrityadvocate:email'] = 'Your email address.';
$string['privacy:metadata:block_integrityadvocate:fullname'] = 'Your full name.';
$string['privacy:metadata:block_integrityadvocate:identification_card'] = 'A picture of your government-issued ID.';
$string['privacy:metadata:block_integrityadvocate:override_date'] = 'If applicable, the date the IntegrityAdvocate status is overridden by an instructor.';
$string['privacy:metadata:block_integrityadvocate:override_fullname'] = 'If applicable, the full name of the instrucor doing the override.';
$string['privacy:metadata:block_integrityadvocate:override_reason'] = 'If applicable, the instrucor reason for doing the override.';
$string['privacy:metadata:block_integrityadvocate:override_status'] = 'If applicable, the IntegrityAdvocate status applied by the override.';
$string['privacy:metadata:block_integrityadvocate:session_end'] = 'The time your proctoring session session ends.';
$string['privacy:metadata:block_integrityadvocate:session_start'] = 'The time your proctoring session session starts.';
$string['privacy:metadata:block_integrityadvocate:user_video'] = 'A video (with audio) recording of you completing an activity.';
$string['privacy:metadata:block_integrityadvocate:userid'] = 'Your database user id number.';

$string['process_integrityadvocate'] = 'IntegrityAdvocate - Process';
$string['progress'] = '# proctor sessions';
$string['resubmit_link'] = 'Resubmit your ID';
$string['session_start'] = 'Start';
$string['session_end'] = 'End';
$string['session_status'] = 'Status';
$string['shortname'] = 'Short course name';
$string['showallinfo'] = 'Show all info';

$string['status_in_progress'] = 'In progress';
$string['status_invalid_id'] = 'Invalid (ID)';
$string['status_invalid_rules'] = 'Invalid (Rules)';
$string['status_valid'] = 'Valid';

$string['submitted'] = 'Submitted';
$string['time_expected'] = 'Expected';
