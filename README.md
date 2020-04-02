The Integrity Advocate block does identity verification & participation monitoring in Moodle activities.

To use this plugin you will need to purchase an API key and App ID from https://integrityadvocate.com/ 

## Installation

Use the Moodle interface's plugin installer.
or
1. Copy the integrityadvocate directory to the blocks/ directory of your Moodle instance;
2. Visit the notifications page.

For more information visit
http://docs.moodle.org/en/Installing_contributed_modules_or_plugins

Once the Integrity Advocate block is installed, you can use it in a course as follows:

1. Turn editing on.
2. Create your activities/resources as normal.
3. Set completion settings for each activity you want to appear in the IntegrityAdvocate overview.
4. Add the IntegrityAdvocate block to an activity page, configure the block with the API key and App ID.

Instructors will see an Overview button leading to a view of student Integrity Advocate sessions.
When Students view the activity page they will see the video proctoring user interface.

## How to restrict access to an activity depending on Integrity Advocate results
Ref https://docs.moodle.org/38/en/Restrict_access_settings
- Your site administrator will need to enable the use of restrict access sitewide (https://docs.moodle.org/38/en/Restrict_access_settings#Enabling_the_use_of_restrict_access_sitewide).
- In the quiz / external tool / etc module, setup the IntegrityAdvocate block.
- In the certificate module:
  - Scroll down and expand the Restrict Access section.
  - Click the "Add restriction..." button, then on the popup click the "Activity Completion" button.
  - Under the Choose... option, select the quiz / external tool / etc module that uses the IntegrityAdvocate block.
  - At the bottom of the form, click "Save and return to course".
  - The IntegrityAdvocate block marks the quiz / external tool / etc module as complete once the proctoring is successful.
  - Students will not be allowed to access the certificate until this is the case.