<?php

error_reporting(E_ALL);
ini_set('display_errors', true);

require_once dirname(__FILE__) . '/vendor/autoload.php';

// setup array of files
$files = isset($_POST['files']) && ! empty($_POST['files']) ? $_POST['files'] : [
    'index.html.twig' => '<h1>{{ text | title }}</h1>
<ul>
{% for item in items %}
    <li>{{ item.name }}</li>
{% endfor %}
</ul>
',
];

// set vars
$twigVars = isset($_POST['twig-vars']) ? $_POST['twig-vars'] : '{ "text": "demo", "items": [{ "name": "A" }, { "name": "B" }] }';

// decode the json, check for errors
$jsonError = false;
$twigVarsArray = json_decode($twigVars, true);
if ( ! $twigVarsArray) {
    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            $jsonError = 'No errors';
        break;
        case JSON_ERROR_DEPTH:
            $jsonError = 'Maximum stack depth exceeded';
        break;
        case JSON_ERROR_STATE_MISMATCH:
            $jsonError = 'Underflow or the modes mismatch';
        break;
        case JSON_ERROR_CTRL_CHAR:
            $jsonError = 'Unexpected control character found';
        break;
        case JSON_ERROR_SYNTAX:
            $jsonError = 'Syntax error, malformed JSON';
        break;
        case JSON_ERROR_UTF8:
            $jsonError = 'Malformed UTF-8 characters, possibly incorrectly encoded';
        break;
        default:
            $jsonError = 'Unknown error';
        break;
    }
}

// if no json errors
if ( ! $jsonError) {

    try {

        // read in the files
        $loader = new Twig\Loader\ArrayLoader($files);
        $twig = new Twig\Environment($loader, [
            'debug' => true,
            'cache' => false,
            'optimizations' => 0,
            'max_render_time' => 2, // seconds
        ]);

        // enable dump() function
        $twig->addExtension(new Twig\Extension\DebugExtension());

        // Sandbox to prevent php function calls directly (without sandbox these are allowed in arrow functions)
        // https://twig.symfony.com/doc/3.x/
        $tags = [
            'apply',
            'autoescape',
            'block',
            'cache',
            'deprecated',
            'do',
            'embed',
            'extends',
            'flush',
            'for',
            'from',
            'if',
            'import',
            'include',
            'macro',
            'sandbox',
            'set',
            'use',
            'verbatim',
            'with',
        ];
        $filters = [
            'abs',
            'batch',
            'capitalize',
            'column',
            'convert_encoding',
            'country_name',
            'currency_name',
            'currency_symbol',
            'data_uri',
            'date',
            'date_modify',
            'default',
            'escape',
            'filter',
            'first',
            'format',
            'format_currency',
            'format_date',
            'format_datetime',
            'format_number',
            'format_time',
            'html_to_markdown',
            'inky_to_html',
            'inline_css',
            'join',
            'json_encode',
            'keys',
            'language_name',
            'last',
            'length',
            'locale_name',
            'lower',
            'map',
            'markdown_to_html',
            'merge',
            'nl2br',
            'number_format',
            'raw',
            'reduce',
            'replace',
            'reverse',
            'round',
            'slice',
            'slug',
            'sort',
            'spaceless',
            'split',
            'striptags',
            'timezone_name',
            'title',
            'trim',
            'u',
            'upper',
            'url_encode',
        ];
        $methods = [];
        $properties = [];
        $functions = [
            'attribute',
            'block',
            'constant',
            'country_timezones',
            'cycle',
            'date',
            'dump',
            'html_classes',
            'include',
            'max',
            'min',
            'parent',
            'random',
            'range',
            'source',
            'template_from_string',
        ];
        $policy = new \Twig\Sandbox\SecurityPolicy($tags, $filters, $methods, $properties, $functions);
        $sandbox = new Twig\Extension\SandboxExtension($policy, true);
        $twig->addExtension($sandbox);

        // render twig templates
        $output = $twig->render(array_keys($files)[0], $twigVarsArray);

    }
    // show user errors
    catch (Twig\Error\SyntaxError $e) {
        $output = 'Twig syntax error: ' . $e->getMessage();
    }
    catch (Twig\Error\RuntimeError $e) {
        $output = 'Twig runtime error: ' . $e->getMessage();
    }
    catch (Twig\Error\LoaderError $e) {
        $output = 'Twig loader error: ' . $e->getMessage();
    }
    catch (Twig\Error\Error $e) {
        $output = 'Twig error'; // not showing $e->getMessage() here as that may give too much away
    }
}
else {
    $output = 'Json error: ' . $jsonError;
}

?><!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Twig Playground</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/default.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/lucario.min.css">

        <style>

        /* === vars === */
        :root {
            --outer-background: #EEE;
            --inner-background: #FFF;
            --border: #CCC;
            --text: #222;
            --active-tab: #666;
        }
        <?php if (! isset($_POST['theme']) || $_POST['theme'] == '') { ?>@media (prefers-color-scheme: dark) {<?php } ?>
        <?php if (! isset($_POST['theme']) || $_POST['theme'] == '' || $_POST['theme'] == 'dark') { ?>
            :root {
                --outer-background: #000912;
                --inner-background: #011021;
                --border: #005f9f;
                --text: #cceeff;
                --active-tab: #007bff;
            }
        <?php } ?>
        <?php if (! isset($_POST['theme']) || $_POST['theme'] == '') { ?>}<?php } ?>

        /* === base === */
        html { background-color: var(--outer-background); }
        body {
            background-color: var(--inner-background);
            color: var(--text);
            margin: 0 auto;
            max-width: 1260px;
            padding: 2em;
            border-radius: 10px;
            box-shadow: 0 0 6px rgba(0, 0, 0, 0.2);
            margin-top: 2em;
        }

        a { cursor: pointer; }
        div, ul { padding: 0; margin: 0; }

        /* === editor === */
        .file-names-list { border-top: 1px solid var(--border); }
        .file-contents { }

        .list-group-item,
        .list-group-item + .list-group-item { background-color: transparent; }
        .file-names-list .form-control { background-color: transparent; color: var(--text); }
        .file-names-list .form-control,
        .file-names-list .form-control::placeholder { color: #666; }

        .file-names-list li { padding: 4px; list-style: none; border-left: 3px solid var(--active-tab); }
        .file-names-list li.active { background-color: var(--active-tab); }
        .file-names-list a { display: inline-block; width: 100%; color: var(--active-tab); text-decoration: none; }
        .file-names-list li.active a { color: #FFF; }

        .file-content { display: none; }
        .file-content.active { display: block; }
        .file-content textarea { width: 100%; min-height: 200px; }

        #twig-vars { width: 100%; }

        .file-output-container { clear: both; }
        .file-output { border: 1px solid var(--border); background-color: #EEE; padding: 4px; }

        .submit-btn,
        .reset-btn,
        .twig-links { float: right; padding: 5px; }

        #add-file-btn { font-style: italic; }

        a.delete-file-btn { float: right; width: auto; font-weight: bold; }

        .CodeMirror { border: 1px solid var(--border); padding: 5px; height: auto; min-height: 120px; }
        #twig-vars ~ .CodeMirror { min-height: 25px; }

        #theme { margin-top: 10px; }

        .title-tab-style {
            border: 1px solid var(--border);
            border-bottom: 0;
            background-color: #EEE;
            color: #333;
            font-weight: bold;
            padding: 5px;
            display: inline-block;
            margin-top: 10px;
        }
        .title-tab-container {
            margin: 0;
        }

        /* cancel theme box-shadow */
        .cm-s-default.CodeMirror { box-shadow: none; }

        @media (max-width: 600px){
            .file-contents,
            .file-names-list {
                float: none;
                width: 100%;
            }
        }

        </style>
    </head>
    <body>

        <form id="twig-form" method="POST">

            <header class="d-flex justify-content-between align-items-center mb-4">
                <h1>Twig Playground</h1>
                <div>
                    <input type="submit" class="btn btn-primary" value="Render">
                    <a href="/" class="btn btn-secondary">Reset</a>

                    <!-- theme select -->
                    <select name="theme" id="theme" class="form-select">
                        <option value="">Auto</option>
                        <option value="light" <?php if (isset($_POST['theme']) && $_POST['theme'] == 'light') echo 'selected'; ?>>Light</option>
                        <option value="dark" <?php if (isset($_POST['theme']) && $_POST['theme'] == 'dark') echo 'selected'; ?>>Dark</option>
                    </select>
                </div>
            </header>

            <!-- enter variables -->
            <div class="mb-4">
                <h2 class="h5">JSON variables:</h2>
                <p><em>These will become variables available to the twig template files</em></p>
                <textarea name="twig-vars" id="twig-vars" class="form-control"><?php echo $twigVars; ?></textarea>
            </div>

            <div class="mb-4">
                <h2 class="h5">Twig Files:</h2>
                <p><em>Only the first file is compiled, but other files can be included or extended</em></p>
                <div class="row">
                    <div class="col-md-3">
                        <ul class="list-group file-names-list">
                        <?php
                        $active = true;
                        foreach ($files as $filename => $input) { ?>
                            <li class="list-group-item<?php if ($active) echo ' active'; ?>"><a href="#file-<?php echo str_replace('.', '☺', $filename); ?>"><?php echo $filename; ?></a></li>
                        <?php $active = false;
                        } ?>
                            <li class="list-group-item">
                                <input type="text" id="new-file-name" class="form-control" placeholder="some-file.html.twig">
                                <br>
                                <a id="add-file-btn" href="#" class="btn btn-link">+ Add file</a>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-9">
                        <div class="file-contents">
                        <?php
                        $active = true;
                        foreach ($files as $filename => $input) { ?>
                            <div id="file-<?php echo str_replace('.', '☺', $filename); ?>" class="file-content<?php if ($active) echo ' active'; ?>">
                                <textarea name="files[<?php echo $filename; ?>]" class="form-control file-input"><?php echo $input; ?></textarea>
                            </div>
                        <?php $active = false;
                        } ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- show html output -->
            <div class="file-output-container">
                <h2 class="h5">HTML Output:</h2>
                <code class="file-output"><?php echo htmlspecialchars($output); ?></code>
            </div>

        </form>

        <!-- bring in jquery lib -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <!-- bring in codemirror syntax editor -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/jinja2/jinja2.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/xml/xml.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/css/css.min.js"></script>

        <!-- main script for this page -->
        <script>
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
            var codemirrorTheme = theme == 'dark' ? 'lucario' : 'default';

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
                mode: "text/<?php echo stristr(array_keys($files)[0], '.css') ? 'css' : 'html'; ?>",
                lineNumbers: true,
                viewportMargin: Infinity,
                theme: codemirrorTheme,
            });

            // Add event handler for form submission
            $('#twig-form').submit(function(event, bypass) {
                if (bypass) return true;

                event.preventDefault(); // Prevent default form submission

                $.ajax({
                    type: 'POST',
                    url: '', // Current page URL
                    data: getData(),
                    success: function(response) {
                        // Update the output area with the response
                        var $output = $('.file-output');
                        var filteredResponse = $(response).find('.file-output').html();
                        var decodedResponse = $('<textarea/>').html(filteredResponse).text();
                        $output.html(decodedResponse);

                        // Reinitialize CodeMirror for the updated output
                        CodeMirror(function(elt) {
                            $output.replaceWith(elt);
                            $(elt).addClass('file-output');
                        }, {
                            value: decodedResponse,
                            readOnly: true,
                            mode: "text/<?php echo stristr(array_keys($files)[0], '.css') ? 'css' : 'html'; ?>",
                            lineNumbers: true,
                            viewportMargin: Infinity,
                            theme: codemirrorTheme,
                        });
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
        </script>

    </body>
</html>
