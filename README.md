Overview
--------

Moodle proctoring and identity verification brought to you by [Integrity Advocate](https://www.integrityadvocate.com/partners/moodle). The Integrity Advocate proctoring plugin for Moodle installs in minutes and saves you hours of support emails. Using our proprietary hybrid AI and human powered participation monitoring, we deliver a fast, easy and secure environment that is truly GDPR compliant because it doesn't store any unnecessary data.

Integrity Advocate is for online proctoring, identity verification and participation monitoring. You can use it to confirm learner identity, to ensure learner participation during course viewing and/or as an online exam proctor. Integrity Advocate is activated by the learner accepting a privacy policy, participation rules (customizable), giving access to their webcam/mic as well as to the monitoring of their screen activity (optional).

Use of this plugin requires purchasing a paid service - please visit [Integrity Advocate](https://www.integrityadvocate.com/partners/moodle) to get the credentials needed to be able to use this plugin.

The Integrity Advocate plugins allow Moodle to show a proctoring interface to students, interact with the Integrity Advocate service, and show monitoring results to instructors.

This plugin, [Integrity Advocate block plugin](https://moodle.org/plugins/block_integrityadvocate) can be added to Moodle activities and makes the Integrity Advocate proctoring interface show up for students, and gives instructors a way to view Integrity Advocate results. See also [What are Moodle blocks?](https://docs.moodle.org/en/Blocks)

The accompanying plugin, [Integrity Advocate restrict access plugin](https://moodle.org/plugins/availability_integrityadvocate) can be added to a Moodle activities and prevents access to activities and resources depending on the Integrity Advocate results in another activity. It requires the [Integrity Advocate block plugin](https://moodle.org/plugins/block_integrityadvocate). See also [What are activity restrictions?](https://docs.moodle.org/38/en/Using_restrict_access)

Support
-------

Please visit [Support for Integrity Advocate](https://support.integrityadvocate.com/hc/en-us).

Integrity Advocate provides both administrator ([support@integrityadvocate.com](mailto:%73upp%6f%72%74@in%74%65%67r%69t%79a%64%76%6fca%74%65%2e%63%6f%6d)) and end-user (learner) support 24/7/365 via chat, email, phone and through an [online support portal](https://support.integrityadvocate.com/hc/en-us).

A nice feature is that the Integrity Advocate App will automatically change its language to that of the user's browser settings (over 64 languages supported).

Bugs / Issue Tracker
--------------------

We welcome reports of bugs, code contributions via the repos for [block\_integrityadvocate](https://github.com/mwithheld/moodle-block_integrityadvocate) and [availability\_integrityadvocate](https://github.com/mwithheld/moodle-availability_integrityadvocate/issues).

We take privacy and security seriously. Any security issues can most responsibly be disclosed to admin@integrityadvocate.com

Privacy
-------

This plugin does not store any data in Moodle. In order to function properly, this plugin sends data to the Integrity Advocate API. This data includes:

*   User: full name, email, Moodle user id number;
*   Enrolment: course-module id;
*   Video session: identification card image, session start, session end, video of the user completing the activity;
*   Override: override date, overrider full name, override reason, override status.

This info is sent using 256-bit encryption (the same used by major financial institutions), meaning your data is kept safe and secure. Integrity Advocate also restricts access by insecure web browsers to ensure data security.

Please see the [Integrity Advocate Privacy](https://www.integrityadvocate.com/privacy-policy-for-end-users) statement for more info. The full privacy policy details and security standards can be provided upon request.

Accessibility
-------------

We are proud to offer a solution that accommodates all users, regardless of ability.

*   WCAG 2.0 AA compliant
*   User-friendly and intuitive interface
*   No scheduling required
*   Flexible rule setting
*   Violation override available
*   Continuously tested to ensure accessibility

Requirements for installation
-----------------------------

*   Purchase an API key and App ID from [Integrity Advocate](https://www.integrityadvocate.com/partners/moodle).
*   PHP 7.2 or higher - see [Moodle PHP doc](https://docs.moodle.org/35/en/PHP).  
    
*   Moodle 3.5 and above - see [What version of Moodle am I using?](https://docs.moodle.org/en/Moodle_version#What_version_of_Moodle_am_I_using)
*   You need administrator privileges in your Moodle instance.
*   Completion must be enabled at the site level and course level - see [Enabling course completion](https://docs.moodle.org/en/Course_completion_settings#Enabling_course_completion).
*   Moodle cron must be running often, ideally every minute or two - see [Setting up cron on your system](https://docs.moodle.org/en/Cron#Setting_up_cron_on_your_system).

Requirements for students
-------------------------

*   A camera-equipped device with an updated browser (all common browser types supported). An additional benefit is that Integrity Advocate will work on all device types that the Moodle content will work on (laptop, tablet, phone etc).
*   Disable all browser ad blockers (e.g. uBlock) and privacy plugins (e.g. Privacy Badger) - see [How to Disable AdBlock on Chrome, Safari, Firefox, Edge or Opera](https://www.softwarehow.com/disable-adblock/).

Download
--------

There are **two components** to download and install:

The [block](https://moodle.org/plugins/block_integrityadvocate) can be added to Moodle activities and makes the Integrity Advocate proctoring interface show up for learners, and shows instructors an overview button so they can view IA results. See also [What are Moodle blocks?](https://docs.moodle.org/en/Blocks)

The optional [availability restriction (condition)](https://moodle.org/plugins/availability_integrityadvocate) prevents access to a course module depending on the Integrity Advocate results in another module.

Potential privacy issues  

---------------------------

Integrity Advocate, when appropriately applied, can mitigate the majority of the privacy concerns that organizations can face when using monitoring technology. Please see the Privacy section in the Description and/or contact [Integrity Advocate](https://www.integrityadvocate.com/partners/moodle) for more details.

Useful links
------------

*   [More documentation on this plugin](https://iapartners.zendesk.com/hc/en-ca/sections/360012118873-Moodle)
*   [Website URL]([https://www.integrityadvocate.com](https://github.com/mwithheld/moodle-block_integrityadvocate))
*   [Source control URL](https://github.com/mwithheld/moodle-block_integrityadvocate)
*   [Bug tracker](https://github.com/mwithheld/moodle-block_integrityadvocate/issues)
*   [Accessibility management reading](https://www.integrityadvocate.com/blog/three-ways-your-online-proctoring-software-isnt-meeting-accessibility-requirements)
