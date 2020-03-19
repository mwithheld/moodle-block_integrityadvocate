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
// Put this string first for re-use below.
$string['pluginname'] = 'Integrity Advocate';

// Other strings go here.
$string['button_overview'] = 'Overview';
$string['completion_not_enabled'] = 'Completion tracking is not enabled on this site.';
$string['completion_not_enabled_course'] = 'Completion tracking is not enabled in this course.';
$string['config_apikey'] = 'Api Key';
$string['config_appid'] = 'Application Id';
$string['config_default_title'] = $string['pluginname'];
$string['disabled_editingmode'] = 'Course editing mode is enabled, so video monitoring is disabled';
$string['disabled_haseditcap'] = 'Disabled for non-students';
$string['disabled_notenrolled'] = 'Not available - you are not enrolled in this course';

/*
 * You can use one of these template strings and they will be substituted with the relevant info:
 * [[wwwroot]]
 * [[site_fullname]]
 * [[course_id]]
 * [[course_shortname]]
 * [[course_fullname]]
 */
$string['email_greeting'] = '<p>Dear {$a},</p>';
$string['email_invalid_flags'] = '<p>{$a->FlagName} {$a->FlagId})</p>' . '<p>{$a->FlagDetails}</p>' . '<p><img src="https:{$a->ImageUrl}" style="height:240px;width:320px" /></p><br /><br />';
$string['email_invalid_id_body_bottom'] = '<p><strong>Please ensure you address any problem listed above and click the link below to provide your identification again.</strong></p><p><a href="{$a->IDResubmitUrl}" target="_blank" rel="noreferrer noopener">{$a->IDResubmitUrl}</a></p>';
$string['email_invalid_id_body_top'] = '<p>Your recent submission for {$a} on [[site_fullname]] was marked invalid due to the following issue(s) with the confirmation of your identity:</p>';
$string['email_invalid_id_subject'] = 'IMPORTANT: Please re-submit your photo ID';
$string['email_invalid_rules_body_bottom'] = '<p><strong>Unfortunately, this means your completion of {$a->Application} was not valid and you will need to purchase and repeat the activity again. Please contact our office for more information.</strong></p>';
$string['email_invalid_rules_body_top'] = '<p>Your recent submission for {$a} on [[site_fullname]] was marked invalid due to the following issue(s) with the confirmation of your identity and/or your participation:</p>';
$string['email_invalid_rules_subject'] = 'IMPORTANT: Your submission was invalid';
$string['email_signoff'] = '<p>Thank you,</p><p><strong>' . $string['pluginname'] . '</strong><br />{$a->email}</p>';
$string['email_template'] = "<html style='height: 100%;'><head>

<title>{$string['pluginname']} Results</title>
<style>
* {padding:0px;margin:0px}
a {color:#4fbee0}
#mailcontent p{margin-bottom:20px}
</style>
</head>
<body style='height: 100%;'>
<div id='container' style='font-family:sans-serif;width:700px;margin:0px auto;min-height:100%;height:auto!important;height:100%;position:relative;background-color:#FFF'>
<div id='header' style='padding:5px 20px 5px 5px;font-size:18px'>
<a href='[[wwwroot]]' target='_blank' rel='noreferrer noopener'>[[site_fullname]]</a>
</div>
<div id='mailcontent' style='padding:10px 20px'>
[[mailcontent]]
</div><!-- mailcontent -->
</div><!-- container -->
</body></html>";
$string['email_valid_body_bottom'] = '';
$string['email_valid_body_top'] = '<p><strong>No action is required on your part. This is only a notification.</strong></p><p>Your recent submission for {$a} on [[site_fullname]] was marked valid.</p>';
$string['email_valid_subject'] = 'Thank you! Your identification has been verified.';
$string['end_time'] = 'Activity completed';
$string['error_curlcloseia'] = 'Curl error closing the IA session';
$string['error_curlnoremoteinfo'] = 'Error: Got no remote IA participant info - check the API key and app id are valid';
$string['error_invalidappid'] = "Invalid {$string['config_appid']} - it is a code that looks a bit like this: c56a4180-65aa-42ec-a945-5fd21dec0538";
$string['error_noapikey'] = "No {$string['config_apikey']} is set";
$string['error_noappid'] = "No {$string['config_appid']} is set";
$string['error_notenrolled'] = "You are not enrolled in this course";
$string['error_quiz_showblocks'] = "This quiz is configured with &quot;Show blocks during quiz attempts&quot; = No.  To fix this, edit the quiz settings > Appearance > Show more...";
$string['flag_details'] = 'Details';
$string['flag_errorcode'] = 'Error code';
$string['fullname'] = 'Full course name';
$string['integrityadvocate:addinstance'] = "Add new {$string['pluginname']} block";
$string['integrityadvocate:addinstance'] = 'Add a new ' . $string['pluginname'] . ' block';
$string['integrityadvocate:myaddinstance'] = "Add a new {$string['pluginname']} block to the desired page";
$string['integrityadvocate:myaddinstance'] = 'Add a ' . $string['pluginname'] . ' block to My home page';
$string['integrityadvocate:overview'] = 'View course overview of Integrity Advocate for all students';
$string['lastaccess'] = 'Last in course';
$string['no_activities_config_message'] = 'There are no activities found with activity completion enabled which have the ' . $string['pluginname'] . ' block set up.';
$string['no_activities_message'] = 'No activities found.';
$string['no_enrollment'] = 'This user is not enrolled in this course';
$string['no_blocks'] = 'No ' . $string['pluginname'] . ' blocks are set up for your courses.';
$string['no_ia_block'] = "No active {$string['pluginname']} block instance found for this activity";
$string['no_local_participants'] = 'No course participants found';
$string['no_remote_participants'] = 'No IA-side participants found';
$string['no_course'] = 'No matching course found';
$string['no_user'] = 'No matching user found';
$string['no_visible_activities_message'] = 'No visible activities found.';
$string['not_all_expected_set'] = 'Not all activities with completion have an "{$a}" date set.';
$string['now_indicator'] = 'NOW';
$string['overview'] = $string['pluginname'] . ' Overview';
$string['overview_flags'] = 'Flags';
$string['overview_user_status'] = 'Latest status';
$string['privacy:metadata'] = 'The ' . $string['pluginname'] . ' block only displays existing completion data.';
$string['process_integrityadvocate'] = "{$string['pluginname']} - Process";
$string['progress'] = '# proctor sessions';
$string['column_iadata'] = $string['pluginname'] . ' data';
$string['column_iaphoto'] = $string['pluginname'] . ' photo';
$string['resubmit_link'] = 'Resubmit your ID';
$string['shortname'] = 'Short course name';
$string['showallinfo'] = 'Show all info';
$string['start_time'] = 'First seen';
$string['status_in_progress'] = 'In progress';
$string['status_invalid_id'] = 'Invalid (ID)';
$string['status_invalid_rules'] = 'Invalid (Rules)';
$string['status_valid'] = 'Valid';
$string['submitted'] = 'Submitted';
$string['time_expected'] = 'Expected';
$string['overview_view_details'] = 'View details';
$string['integrityadvocate:selfview'] = 'View own ' . $string['pluginname'] . ' results';
