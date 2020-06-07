------
Description of DataTables third-party code included in this plugin.
------

Datatables is used to add sorting and filtering to the Overview page participants table.

------
To update the Javascript files:
------

- Visit https://datatables.net/download/#dt/dt-1.10.21
  - This url comes from https://datatables.net/download/ with:
    - Styling = Bootstrap 4
    - Packages = DataTables
    - Extensions = <none>
    - Choose the Download tab and click the "Download files" button at the bottom.

- Update thirdpartylibs.xml with the correct DataTables version
- Upload to the server and make sure you purge caches or set $CFG->cachejs=false in config.php


------
Why is there no DataTables css?
------

We are only using the Datatables search functionality, and I didn't like or need
the styles it came with (version 1.10.21). E.g. by default the search box is
pushed to the far right of the page.


------
Refs
------
- https://learn1.open.ac.uk/mod/oublog/pluginfile.php/15/mod_oublog/attachment/164813/moodle-javascript-amd.pdf?forcedownload=1
- https://docs.moodle.org/dev/Javascript_Modules
