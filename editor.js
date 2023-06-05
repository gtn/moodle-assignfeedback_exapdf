require(['mod_assign/grading_review_panel'], function (ReviewPanelManager) {
  var panelManager = new ReviewPanelManager();

  var panel = panelManager.getReviewPanel('assignfeedback_exapdf');
});

(function () {
  // was load_dakora already called?
  var dakora_inited = false;

  // was dakora already loaded?
  var dakora_loaded = false;

  // the dakora iframe
  var $iframe;

  // this file gets reloaded each time you press "Änderungen speichern"
  // -> only initialize once
  window.load_dakora = window.load_dakora || function (data) {
    // advanced: if dakora was already sarted, then load the file directly without reloading dakora
    if (dakora_loaded) {
      $iframe[0].contentWindow.postMessage({
        app: 'dakoraplus',
        type: 'load_file',
        fileid: data.fileid,
      }, '*');

      return;
    }

    if (!dakora_inited) {
      // add iframe only once to editor
      $iframe = $('<iframe style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; border: 0; width: 100%; height: 100%;"></iframe>');
      $iframe.appendTo('[data-region="review-panel-content"]');
    }
    $iframe.prop('src', data.dakoraurl);


    if (dakora_inited) {
      return;
    }
    dakora_inited = true;


    var $buttons = $('button[name="savechanges"], button[name="saveandshownext"], button[name="resetbutton"]').not('.cloned');
    var $clones;

    window.addEventListener(
      'message',
      function (event) {
        const data = event.data;
        if (data && data.app == 'dakoraplus') {
          console.log('moodle received message:', data);

          // handle
          if (data.type == 'document_changed') {
          }

          if (data.type == 'savechanges' || data.type == 'saveandshownext') {
            // trigger click on original button
            $buttons.filter('[name="' + data.type + '"]')[0].click();

            // restore original state
            // $('button[name="savechanges"], button[name="saveandshownext"], button[name="resetbutton"]').filter('.cloned').remove();
            // $buttons.show();
          }

          if (data.type == 'dakoraplus_loaded') {
            if (dakora_loaded) {
              return;
            }
            dakora_loaded = true;

            // first remove old clones, if there are any
            // $('button[name="savechanges"], button[name="saveandshownext"], button[name="resetbutton"]').filter('.cloned').remove();

            $buttons.hide();

            $clones = $buttons
              .clone()
              .show()
              .addClass('cloned')
              // .css('background-color', 'green')
              .insertAfter($buttons[0])
              // fix some styling issues
              .css('margin-right', '4px');

            $clones.filter('button[name="savechanges"], button[name="saveandshownext"]').on('click', function (e) {
              e.preventDefault();
              $iframe[0].contentWindow.postMessage({
                app: 'dakoraplus',
                type: $(this).attr('name')
              }, '*');

            });

            $clones.filter('button[name="resetbutton"]').on('click', function (e) {
              if (confirm('Wirklich zurücksetzen?')) {
                // trigger action
                $buttons.filter('[name="' + 'resetbutton' + '"]')[0].click();

                // restore original state
                // $clones && $clones.remove();
                // $buttons.show();
              }
            });
          }
        }
      },
      false
    );
  };
})();
