M.block_integrityadvocate = {
    /* We do not need these ATM.
    init: function (YUIObject, instances, users) {
         var instance;
         var user;
     },

     addEvent: function (target, evt, func) {
         if (target.addEventListener) {
             target.removeEventListener(evt, func);
             target.addEventListener(evt, func);
         } else if (target.attachEvent) {
             target.detachEvent('on' + evt, func);
             target.attachEvent('on' + evt, func);
         }
     }
     */
};

// Sets up the DataGrid.
require(['core/first'], function() {
    require(['block_integrityadvocate/init'], function(dt) {
        dt.init('#mod-block-integrityadvocate-overview', {});
    });
});
