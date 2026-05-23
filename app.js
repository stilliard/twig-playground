jQuery(function($) {

    // theme select
    $('#theme').change(function () {
        $('form').trigger('submit', [true]);
    });

    // track codemirror editors
    var editors = [];

    // get data from codemirror editors
    function getData() {
        var data = {};
        $.each(editors, function(index, editor) {
            data[editor.getTextArea().name] = editor.getValue();
        });
        return data;
    }

    // get current theme
    var theme = $('#theme').val();
    if (theme == '') {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            theme = 'dark';
        } else {
            theme = 'light';
        }
    }
    // code mirror theme
    var codemirrorTheme = theme == 'dark' ? 'material-darker' : 'default';

    // Add file
    $('#add-file-btn').click(function () {
        var $this = $(this),
            $newFileName = $('#new-file-name'),
            newFileNameText = $newFileName.val().replace(/[^A-z0-9\.\-_]/ig, '');

        if (newFileNameText=='') return false;

        $this.parent().before('<li class="list-group-item"><a href="#file-' + newFileNameText.replace(/\./g, '☺') + '">' + newFileNameText + '</a></li>');
        $('.file-contents').append('<div id="file-' + newFileNameText.replace(/\./g, '☺') + '" class="file-content"><textarea name="files[' + newFileNameText + ']" class="form-control file-input"></textarea></div>');

        // clear the input ready for the next new file
        $newFileName.val('');

        // open the new tab
        $('a[href="#file-' + newFileNameText.replace(/\./g, '☺') + '"]').click();

        return false;
    });
    // hijact enter key to do the above too
    $('#new-file-name').keypress(function (e) {
        if (e.which == 13) {
            $('#add-file-btn').click();
            return false;
        }
    });

    // tabs
    $('.file-names-list').on('click', 'a[href]', function () {

        var $tab;

        // hide current tabs
        $('.file-names-list li.active, .file-content.active').removeClass('active');

        // show this tab
        $(this).parent().addClass('active');
        $tab = $($(this).attr('href')).addClass('active');

        // put cursor into textarea
        $tab.find('textarea').focus();

        // detect if codemirror is not yet setup
        if ( ! $tab.find('textarea ~ .CodeMirror').length) {
            // init codemirror editor
            editors.push(CodeMirror.fromTextArea($('.file-content.active textarea')[0], {
                mode: { name: "jinja2", htmlMode: true },
                viewportMargin: Infinity,
                theme: codemirrorTheme,
            }));
        }

        return false;
    });

    // delete file
    $('.file-names-list').on('click', '.delete-file-btn', function () {
        var $this = $(this),
            $parent = $this.parent().parent(),
            $tab = $($parent.find('input[name="old-file-name"]').val());

        $parent.remove();
        $tab.remove();

        // show first tab
        $('.file-names-list li:first-child > a').click();
        return false;
    });

    // rename file
    $('.file-names-list').on('dblclick', 'a[href]', function () {

        var $this = $(this),
            text = $this.text()
            href = $this.attr('href'),
            html = '';

        html = '\
        <div class="rename-file">\
            <input type="hidden" name="old-file-name" value="'+ href +'">\
            <input type="text" name="new-file-name" value="'+ text +'" title="Press enter to save">';

        // should we offer a delete btn? (don't allow delete if there's only 1 file left)
        if ($('.file-names-list li').length > 2) {
            html += '<a class="delete-file-btn">x</a>';
        }

        html += '</div>';

        $this.replaceWith(html);

        $this.find('input[name="new-file-name"]').focus();

        return false;

    }).on('keypress', 'input', function (e) {

        // watch for submit of this (by enter key)
        var $input, newFileName, oldFileName, $tab;
        if (e.which == 13) {

            // set filename
            $input = $(this);
            newFileName = $input.val().replace(/[^A-z0-9\.\-_]/ig, '');
            $input.parent().replaceWith('<a href="#file-' + newFileName.replace(/\./g, '☺') + '">' + newFileName + '</a>');
            oldFileName = $input.siblings('input[name="old-file-name"]').val();
            $tab = $(oldFileName);

            // update textarea key
            $tab.attr('id', 'file-' + newFileName.replace(/\./g, '☺'));
            $tab.find('> textarea').attr('name', 'files[' + newFileName + ']');

            return false;
        }
    });

    // init codemirror editor
    editors.push(CodeMirror.fromTextArea($('.file-content.active textarea')[0], {
        mode: { name: "jinja2", htmlMode: true },
        viewportMargin: Infinity,
        theme: codemirrorTheme,
    }));
    editors.push(CodeMirror.fromTextArea($('#twig-vars')[0], {
        mode: "application/json",
        viewportMargin: Infinity,
        theme: codemirrorTheme,
    }));

    // init codemirror on the output too, but make it read only
    var $output = $('.file-output'),
        text = $output.text();
    CodeMirror(function(elt) {
        $output.replaceWith(elt);
        $(elt).addClass('file-output');
    }, {
        value: text,
        readOnly: true,
        mode: 'text/' + ($output.data('mode') || 'html'),
        lineNumbers: true,
        viewportMargin: Infinity,
        theme: codemirrorTheme,
    });

    // AJAX submission handler
    $('#twig-form').submit(function(event, bypass) {
        if (bypass) return true;

        event.preventDefault();

        $.ajax({
            type: 'POST',
            url: '',
            data: getData(),
            success: function(response) {
                // Safely extract data from response
                var $response = $(response);
                var $newOutput = $response.find('.file-output');
                var outputText = $newOutput.text();
                var outputMode = $newOutput.data('mode');

                // Update the code view
                var $output = $('.file-output');
                $output.text(outputText);

                // Reinitialize CodeMirror
                CodeMirror(function(elt) {
                    $output.replaceWith(elt);
                    $(elt).addClass('file-output');
                }, {
                    value: outputText,
                    readOnly: true,
                    mode: 'text/' + outputMode,
                    lineNumbers: true,
                    viewportMargin: Infinity,
                    theme: codemirrorTheme,
                });

                // Extract the new iframe URL and update it
                var $newFrame = $response.find('#render-frame');
                if ($newFrame.length) {
                    $('#render-frame').attr('src', $newFrame.attr('src'));
                }
            },
            error: function() {
                alert('An error occurred while processing the request.');
            }
        });
    });

    // Ctrl+enter or ctrl+s to submit form
    $(document).keydown(function(e) {
        if ((e.which == '115' || e.which == '83' ) && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            $('#twig-form').submit();
            return false;
        }
    });

});
