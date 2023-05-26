require(['mod_assign/grading_review_panel'], function (ReviewPanelManager) {
    var panelManager = new ReviewPanelManager();

    var panel = panelManager.getReviewPanel('assignfeedback_exapdf');
});

(function () {
    var dakora_started = false;
    var $iframe;

    // only initialize once
    window.start_dakora = window.start_dakora || function (files, data) {
        // var url = 'https://dakoraplus.eu/dakora-plus/learning-plans?contextid='
        // var url = 'http://dakoraplus.eu:3000/learning-plans?fileid='
        var url = 'http://dakoraplus.eu/feature/exapdf/learning-plans?fileid='
            + data.fileid + '&submission_file=' + data.submission_file;
        console.log('start_dakora, url:', url, data);

        if (!dakora_started) {
            $iframe = $('<iframe style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; border: 0; width: 100%; height: 100%;"></iframe>');
            $iframe.appendTo('[data-region="review-panel-content"]');
        }
        $iframe.prop('src', url);

        // test output
        // if (!dakora_started) {
        //     var $container = $('<div style="background: yellow;">' + data.output + '</div>');
        //     $container.prependTo('[data-region="grade-panel"]');
        // }

        $('#exapdf-file-list').remove();
        var $container = $('<div style="border: 3px solid green;" id="exapdf-file-list"></div>')
            .prependTo('[data-region="grade-panel"]');

        function mark_file_changed(fileid) {
            $('[data-fileid="' + fileid + '"]')
                .find('.changed').show();
        }

        files.forEach(function (file) {
            $('<div style="cursor: pointer" data-fileid="' + file.fileid + '">' + file.name + ' <span class="changed" style="display: none; background: yellow;">geändert</span></div>')
                .click(function () {
                    window.start_dakora(files, file);
                })
                .appendTo($container);

            if (file.annotations_changed) {
                mark_file_changed(file.fileid);
            }
        });

        if (!dakora_started) {
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
                            // hide moodle save buttons
                            // $('button[name="savechanges"], button[name="saveandshownext"]').prop('disabled', true);
                            mark_file_changed(data.fileid);
                        }
                        // if (data.type == 'document_saved') {
                        //     // hide moodle save buttons
                        //     $('button[name="savechanges"], button[name="saveandshownext"]').prop('disabled', false);
                        // }
                        if (data.type == 'savechanges' || data.type == 'saveandshownext') {
                            // trigger click on original button
                            console.log($buttons.filter('[name="' + data.type + '"]'));

                            // trigger action
                            $buttons.filter('[name="' + data.type + '"]')[0].click();

                            // restore original state
                            $('button[name="savechanges"], button[name="saveandshownext"], button[name="resetbutton"]').filter('.cloned').remove();
                            $buttons.show();
                        }

                        if (data.type == 'dakoraplus_loaded') {
                            // first remove old clones, if there are any
                            $('button[name="savechanges"], button[name="saveandshownext"], button[name="resetbutton"]').filter('.cloned').remove();

                            $buttons.hide();

                            $clones = $buttons
                                .clone()
                                .show()
                                .addClass('cloned')
                                .css('background-color', 'green')
                                .insertAfter($buttons[0])
                                // fix some styling issues
                                .css('margin-right', '4px')

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
                                    $clones && $clones.remove();
                                    $buttons.show();
                                }
                            });
                        }
                    }
                },
                false
            );
        }

        dakora_started = true;
    };
})();
