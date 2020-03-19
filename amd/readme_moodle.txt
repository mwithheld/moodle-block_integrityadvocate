------
Description of DataTables third-party code included in this plugin.
------

The source files in the <this_plugin_dir>/amd/src directory are downloaded from
https://datatables.net.

They are built using a BitBucket pipeline within this repository.

-----
Config
-----
- init.js contains an options array where defaults are set.
- These are partially overridden in overview.php in a block like this:

    $PAGE->requires->js_call_amd('block_integrityadvocate/init', 'init',
            //DataTable options ref https://datatables.net/reference/option/
            array('.datatable', array(
                    'autoWidth' => false,
                    'paging' => false,
                    'searching' => true,
                    'language' => array(
                        //Language options ref https://datatables.net/reference/option/language
                        'search' => get_string('filter') . '&nbsp;'
                    )
    )));


------
To update the Javascript files:
------

- Edit <this_plugin_dir>/Makefile and change the version number in the url (https://cdn.datatables.net/...).
  - This url comes from https://datatables.net/download/ with:
    - Styling = Bootstrap 4
    - Packages = DataTables
    - Extensions = <none>
    - Choose the Download tab and click the "Download files" button at the bottom.

- On a linux machine, install requirements:

cd <moodle_root>/
sudo apt-get install -y nodejs npm
sudo npm install
sudo npm install -g grunt-cli


cd <this_plugin_dir>
make get #Download new JS files from the URL above.
make grunt #Run convert.pl to minify files into amd/build/ folder.

- Output files go into <this_plugin_dir>/amd/build/
- Update thirdpartylibs.xml with the correct DataTables version
- Upload to the server and make sure you purge caches or set $CFG->cachejs=false in config.php


------
Why is there no DataTables css?
------

We are only using the Datatables search functionality, and I didn't like or need 
the styles it came with (version 1.10.20). E.g. by default the search box is 
pushed to the far right of the page.


------
Refs
------
- https://learn1.open.ac.uk/mod/oublog/pluginfile.php/15/mod_oublog/attachment/164813/moodle-javascript-amd.pdf?forcedownload=1
- https://docs.moodle.org/dev/Javascript_Modules
