// simple twig + html highlighter: {% tags %}, {{ output }}, {# comments #} and html tags
CodeMirror.defineMode('twig-html', function () {
    var closers = { '{%': '%}', '{{': '}}', '{#': '#}' },
        styles = { '{%': 'twig-tag', '{{': 'twig-output', '{#': 'comment' };

    return {
        startState: function () { return { open: null }; },
        token: function (stream, state) {
            var ch, quote;

            // inside a twig block
            if (state.open) {
                var style = styles[state.open];
                if (stream.match(closers[state.open])) {
                    state.open = null;
                    return style;
                }
                ch = stream.next();
                if (state.open !== '{#' && (ch === '"' || ch === "'")) {
                    quote = ch;
                    while ((ch = stream.next()) != null) {
                        if (ch === '\\') stream.next();
                        else if (ch === quote) break;
                    }
                    return style + ' twig-string';
                }
                return style;
            }

            // opening a twig block
            var opener = stream.match(/^\{[%{#]-?/);
            if (opener) {
                state.open = opener[0].slice(0, 2);
                return styles[state.open];
            }

            // html tags
            if (stream.match(/^<\/?[\w-]+[^>{]*>?/) || stream.match(/^<!--.*?-->/)) {
                return 'html-tag';
            }

            // plain text, up to the next interesting char
            stream.next();
            while ( ! stream.eol() && ! /[<{]/.test(stream.peek())) stream.next();
            return null;
        }
    };
});

jQuery(function($) {

    var $status = $('#render-status'),
        $varsMeta = $('#vars-meta'),
        outputEditor,
        renderXhr,
        renderTimer;

    // file tab ids can't contain dots
    function fileId(filename) {
        return 'file-' + filename.replace(/\./g, '☺');
    }
    function cleanFileName(name) {
        return name.replace(/[^A-Za-z0-9.\-_]/g, '');
    }

    // init codemirror on a textarea, rendering again on every change
    function initEditor(textarea, mode) {
        var editor = CodeMirror.fromTextArea(textarea, {
            mode: mode,
            lineNumbers: true,
            viewportMargin: Infinity,
            theme: 'twig',
        });
        editor.on('change', scheduleRender);
        $(textarea).data('editor', editor);
        return editor;
    }

    // get data from the editors, in tab order so the first file stays the entry file
    function getData() {
        var data = { ajax: 1 };
        $('#twig-vars, .file-content > textarea').each(function () {
            var editor = $(this).data('editor');
            data[this.name] = editor ? editor.getValue() : this.value;
        });
        return data;
    }

    // show how many top-level keys the variables have
    function updateVarsMeta() {
        var vars, count;
        try {
            vars = JSON.parse(varsEditor.getValue());
        } catch (e) {
            return $varsMeta.text('Invalid JSON');
        }
        if (vars === null || typeof vars != 'object') return $varsMeta.text('Must be an object');
        count = Object.keys(vars).length;
        $varsMeta.text(count + ' top-level key' + (count == 1 ? '' : 's'));
    }

    // read only output view
    function setOutput(text, mode) {
        outputEditor.setOption('mode', 'html' == mode ? 'twig-html' : 'text/' + mode);
        outputEditor.setValue(text);
    }

    // render on the server
    function render() {
        clearTimeout(renderTimer);

        // render tokens are single use, so only the latest request should win
        if (renderXhr) renderXhr.abort();

        renderXhr = $.ajax({
            type: 'POST',
            url: '',
            data: getData(),
            dataType: 'json',
            success: function (response) {
                setOutput(response.output, response.mode);
                $('#render-frame').attr('src', response.frameSrc);
                $status.toggleClass('is-error', response.error).find('.text').text(response.status);
            },
            error: function (xhr, textStatus) {
                if (textStatus == 'abort') return;
                $status.addClass('is-error').find('.text').text('Request failed');
            }
        });
    }
    function scheduleRender() {
        clearTimeout(renderTimer);
        renderTimer = setTimeout(render, 350);
    }

    // switch tabs
    function openTab($link) {
        var $tab = $($link.attr('href'));

        $('.file-tabs li.active, .file-content.active').removeClass('active');
        $link.parent().addClass('active');
        $tab.addClass('active');

        // lazy init codemirror
        var $textarea = $tab.children('textarea'),
            editor = $textarea.data('editor') || initEditor($textarea[0], 'twig-html');
        editor.refresh();
        editor.focus();
    }
    $('.file-tabs').on('click', 'a[href]', function () {
        openTab($(this));
        return false;
    });

    // add file
    var $newFileName = $('#new-file-name'),
        $addFileBtn = $('#add-file-btn');

    function addFile() {
        var name = cleanFileName($newFileName.val()),
            $existing = $('a[href="#' + fileId(name) + '"]');

        $newFileName.val('').prop('hidden', true);
        $addFileBtn.prop('hidden', false);
        if (name == '') return;

        // already have it? just open it
        if ($existing.length) return openTab($existing);

        var $li = $('<li><a></a><span class="entry-badge">entry</span><button type="button" class="delete-file-btn">×</button></li>');
        $li.find('a').attr({ href: '#' + fileId(name), title: 'Double-click to rename' }).text(name);
        $li.find('.delete-file-btn').attr({ title: 'Delete ' + name, 'aria-label': 'Delete ' + name });
        $('.file-tabs .add-file').before($li);

        var $content = $('<div class="file-content"><textarea class="file-input"></textarea></div>').attr('id', fileId(name));
        $content.find('textarea').attr('name', 'files[' + name + ']');
        $('.file-contents').append($content);

        openTab($li.find('a'));
    }
    $addFileBtn.click(function () {
        $addFileBtn.prop('hidden', true);
        $newFileName.prop('hidden', false).focus();
    });
    $newFileName.on('keydown', function (e) {
        if (e.key == 'Enter') {
            addFile();
            return false;
        }
        if (e.key == 'Escape') {
            $newFileName.val('');
            addFile();
        }
    }).on('blur', addFile);

    // delete file
    $('.file-tabs').on('click', '.delete-file-btn', function () {
        var $li = $(this).parent(),
            wasActive = $li.hasClass('active');

        $($li.find('a').attr('href')).remove();
        $li.remove();

        if (wasActive) openTab($('.file-tabs li:first-child > a'));
        render();
        return false;
    });

    // rename file
    $('.file-tabs').on('dblclick', 'a[href]', function () {
        var $link = $(this),
            $input = $('<input type="text" class="rename-file">').val($link.text());

        $link.hide().after($input);
        $input.focus().select();

        function finish(save) {
            var name = cleanFileName($input.val()),
                $tab = $($link.attr('href'));

            $input.remove();
            $link.show();
            if ( ! save || name == '' || name == $link.text()) return;
            if ($('a[href="#' + fileId(name) + '"]').length) return;

            $link.attr('href', '#' + fileId(name)).text(name);
            $link.siblings('.delete-file-btn').attr({ title: 'Delete ' + name, 'aria-label': 'Delete ' + name });
            $tab.attr('id', fileId(name));
            $tab.children('textarea').attr('name', 'files[' + name + ']');
            render();
        }
        $input.on('keydown', function (e) {
            if (e.key == 'Enter') { finish(true); return false; }
            if (e.key == 'Escape') finish(false);
        }).on('blur', function () { finish(true); });

        return false;
    });

    // init editors
    var varsEditor = initEditor($('#twig-vars')[0], 'application/json');
    varsEditor.on('change', updateVarsMeta);
    updateVarsMeta();
    initEditor($('.file-content.active textarea')[0], 'twig-html');

    var $output = $('.file-output');
    outputEditor = CodeMirror(function (elt) {
        $output.replaceWith(elt);
        $(elt).addClass('file-output');
    }, {
        value: $output.text(),
        readOnly: true,
        mode: 'html' == $output.data('mode') ? 'twig-html' : 'text/' + $output.data('mode'),
        lineNumbers: true,
        viewportMargin: Infinity,
        theme: 'twig',
    });

    // light/dark toggle, choosing the system theme goes back to following it
    var systemDark = window.matchMedia('(prefers-color-scheme: dark)');
    function systemTheme() {
        return systemDark.matches ? 'dark' : 'light';
    }
    $('#theme-toggle').click(function () {
        var theme = document.documentElement.getAttribute('data-theme') == 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        try {
            if (theme == systemTheme()) localStorage.removeItem('theme');
            else localStorage.setItem('theme', theme);
        } catch (e) {}
    });
    systemDark.addEventListener('change', function () {
        var saved;
        try { saved = localStorage.getItem('theme'); } catch (e) {}
        if ( ! saved) document.documentElement.setAttribute('data-theme', systemTheme());
    });

    // copy output
    $('#copy-output-btn').click(function () {
        var $btn = $(this);
        navigator.clipboard.writeText(outputEditor.getValue()).then(function () {
            $btn.text('Copied');
            setTimeout(function () { $btn.text('Copy'); }, 1500);
        });
    });

    // form submits (e.g. enter in an input) render via ajax instead
    $('#twig-form').submit(function (event) {
        event.preventDefault();
        render();
    });

    // Ctrl+enter or ctrl+s to render now
    $(document).keydown(function(e) {
        if ((e.key.toLowerCase() == 's' || e.key == 'Enter') && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            render();
            return false;
        }
    });

});
