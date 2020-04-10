# IntegrityAdvocate Moodle block

The Integrity Advocate block does identity verification & participation monitoring in Moodle activities.

## Requirements for installation

 - Purchase an API key and App ID from https://integrityadvocate.com/
 - Moodle 3.4 and above.
 - Completion must be enabled at the site level and course level.
 - Moodle cron must be running often, ideally every minute or two.  Ref https://docs.moodle.org//en/Cron

## Requirements for end-users

 - A recent browser version.
   - Disable all browser ad blockers (e.g. uBlock) and privacy plugins (e.g. Privacy Badger).

## Installation

Login to your Moodle site as an admin, navigate to Site administration > Plugins > Install plugins, upload the zip file and install it.

**or**

1. Copy the integrityadvocate directory into the blocks/ directory of your Moodle instance;
2. Browse to the Moodle admin notifications page and step through the installer.

For more information visit
http://docs.moodle.org/en/Installing_contributed_modules_or_plugins

## Setup
Once installed, you can use it as follows:

1. Turn editing on.
2. Create your activities/resources as normal.
3. Set completion settings for each activity you want to appear in the IntegrityAdvocate overview.
4. Add the IntegrityAdvocate block to an *activity page*, configure the block with the API key and App ID.
   For a quiz:
     - Edit the quiz settings > Appearance section > Show more... and set "Show blocks during quiz attempts" to Yes.
     - Turn on course editing, click into the quiz, click Attempt quiz, then add the IntegrityAdvocate block to that page.  Then configure the block, set the API key and AppID, and make sure "Where this block appears" > "Display on page types" is set to "Attempt quiz page".

You can place the IntegrityAdvocate block on the course page if you want.  It won't cause the IntegrityAdvocate proctoring popup to show up anywhere.
Instructors will only see the Overview button.  Students will see a summary of their IntegrityAdvocate session info.
  - Ref https://docs.moodle.org/38/en/Course_homepage#Blocks

You can also place the IntegrityAdvocate block on the student profile page if you want; it'll only show the Overview button, and only to instructors.  It'll show a summary of the student's IntegrityAdvocate info.
  - Ref https://docs.moodle.org/38/en/User_profiles#Default_profile_page

When Students view the activity page they will see the video proctoring user interface.
Admins/instructors will not see the video proctoring interface -- they see an Overview button leading to a view of student IntegrityAdvocate sessions.

## How to restrict access to an activity depending on Integrity Advocate results
Ref https://docs.moodle.org/en/Restrict_access_settings
- Your site administrator will need to enable the use of restrict access sitewide (https://docs.moodle.org/en/Restrict_access_settings#Enabling_the_use_of_restrict_access_sitewide).
- In the quiz / external tool / etc module, setup the IntegrityAdvocate block.
- In the certificate module:
  - Scroll down and expand the Restrict Access section.
  - Click the "Add restriction..." button, then on the popup click the "Activity Completion" button.
  - Under the Choose... option, select the quiz / external tool / etc module that uses the IntegrityAdvocate block.
  - At the bottom of the form, click "Save and return to course".
  - The IntegrityAdvocate block marks the quiz / external tool / etc module as complete once the proctoring is successful.
  - Students will not be allowed to access the certificate until this is the case.