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
        $twig = new Twig\Environment($loader);

        // Sandbox to prevent php function calls directly (without sandbox these are allowed in arrow functions)
        // https://twig.symfony.com/doc/2.x/
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

        <link rel="stylesheet" type="text/css" href="bower_components/codemirror/lib/codemirror.css">
        
        <style>
        
        body { font: 14px/1.3 sans-serif; margin: 0 auto; max-width: 960px; padding: 10px; }
        a { cursor: pointer; }
        div, ul { padding: 0; margin: 0; }
        
        .file-names-list { width: 20%; float: left; border-top: 1px solid #CCC; }
        .file-contents { width: 80%; float: left; }

        .file-names-list li { padding: 4px; list-style: none; border-left: 3px solid #666; }
        .file-names-list li.active { background-color: #666; }
        .file-names-list a { display: inline-block; width: 100%; color: #666; text-decoration: none; }
        .file-names-list li.active a { color: #FFF; }

        .file-content { display: none; }
        .file-content.active { display: block; }
        .file-content textarea { width: 100%; min-height: 200px; }
        
        #twig-vars { width: 100%; }
        
        .file-output-container { clear: both; }
        .file-output { border: 1px solid #CCC; background-color: #EEE; padding: 4px; }
        
        .submit-btn,
        .reset-btn,
        .twig-links { float: right; padding: 5px; }
    
        #add-file-btn { font-style: italic; }

        a.delete-file-btn { float: right; width: auto; font-weight: bold; }

        .CodeMirror { border: 1px solid #ccc; padding: 5px; height: auto; min-height: 120px; }
        #twig-vars ~ .CodeMirror { min-height: 25px; }

        .title-tab-style {
            border: 1px solid #CCC;
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

            <header>
                <input type="submit" class="submit-btn" value="Render">
                <a href="/" class="reset-btn">Reset</a>

                <h1>Twig Playground</h1>
            </header>
        
            <!-- enter variables -->
            <p class="title-tab-container"><span class="title-tab-style">JSON variables:</span> <em>These will become variables available to the twig template files</em></p>
            <textarea name="twig-vars" id="twig-vars"><?php echo $twigVars; ?></textarea>
            
            <div class="twig-links">
                <a href="http://twig.sensiolabs.org/doc/templates.html" target="_blank">Twig Syntax Intro</a>
                | <a href="http://twig.sensiolabs.org/documentation#reference" target="_blank">Twig Reference</a>
            </div>

            <p class="title-tab-container"><span class="title-tab-style">Twig Files:</span> <em>Only the first file is compiled, but other files can be included or extended</em></p>

            <!-- list of files -->
            <ul class="file-names-list">
            <?php
            $active = true;
             foreach ($files as $filename => $input) { ?>
                <li<?php if ($active) echo ' class="active"'; ?>><a href="#file-<?php echo str_replace('.', '☺', $filename); ?>"><?php echo $filename; ?></a></li>
            <?php $active = false;
            } ?>
                <li>
                    <input type="text" id="new-file-name" placeholder="some-file.html.twig">
                    <br>
                    <a id="add-file-btn" href="#">+ Add file</a>
                </li>
            </ul>

            <!-- each file's content -->
            <div class="file-contents">
            <?php
            $active = true;
            foreach ($files as $filename => $input) { ?>
                <div id="file-<?php echo str_replace('.', '☺', $filename); ?>" class="file-content<?php if ($active) echo ' active'; ?>">
                    <textarea name="files[<?php echo $filename; ?>]" class="file-input"><?php echo $input; ?></textarea>
                </div>
            <?php $active = false;
            } ?>
            </div>
           
            <!-- show html output --> 
            <div class="file-output-container">
                <p class="title-tab-container"><span class="title-tab-style">HTML Output:</span></p>
                <code class="file-output"><?php echo htmlspecialchars($output); ?></code>
            </div>

        </form>

        <!-- bring in jquery lib -->
        <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
        <!-- bring in codemirror syntax editor -->
        <script src="/bower_components/codemirror/lib/codemirror.js"></script>
        <script src="/bower_components/codemirror/mode/jinja2/jinja2.js"></script>
        <script src="/bower_components/codemirror/mode/javascript/javascript.js"></script>
        <script src="/bower_components/codemirror/mode/xml/xml.js"></script>
        <script src="/bower_components/codemirror/mode/css/css.js"></script>

        <!-- main script for this page -->
        <script>
        jQuery(function($) {
            
            // Add file
            $('#add-file-btn').click(function () {
                var $this = $(this),
                    $newFileName = $('#new-file-name'),
                    newFileNameText = $newFileName.val().replace(/[^A-z0-9\.\-_]/ig, '');

                if (newFileNameText=='') return false;

                $this.parent().before('<li><a href="#file-' + newFileNameText.replace(/\./g, '☺') + '">' + newFileNameText + '</a></li>');
                $('.file-contents').append('<div id="file-' + newFileNameText.replace(/\./g, '☺') + '" class="file-content"><textarea name="files[' + newFileNameText + ']" class="file-input"></textarea></div>');

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
                    CodeMirror.fromTextArea($('.file-content.active textarea')[0], { mode: { name: "jinja2", htmlMode: true }, viewportMargin: Infinity });
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
            CodeMirror.fromTextArea($('.file-content.active textarea')[0], { mode: { name: "jinja2", htmlMode: true }, viewportMargin: Infinity });
            CodeMirror.fromTextArea($('#twig-vars')[0], { mode: "application/json", viewportMargin: Infinity });

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
                viewportMargin: Infinity
            });

            // Add event handler for form submission
            $('#twig-form').submit(function(event) {
                event.preventDefault(); // Prevent default form submission

                var formData = $(this).serialize(); // Serialize form data

                $.ajax({
                    type: 'POST',
                    url: '', // Current page URL
                    data: formData,
                    success: function(response) {
                        // Update the output area with the response
                        var $output = $('.file-output');
                        var filteredResponse = $(response).find('.file-output').html();
                        $output.html(filteredResponse);

                        // Reinitialize CodeMirror for the updated output
                        CodeMirror(function(elt) {
                            $output.replaceWith(elt);
                            $(elt).addClass('file-output');
                        }, {
                            value: filteredResponse,
                            readOnly: true,
                            mode: "text/<?php echo stristr(array_keys($files)[0], '.css') ? 'css' : 'html'; ?>",
                            lineNumbers: true,
                            viewportMargin: Infinity
                        });
                    },
                    error: function() {
                        alert('An error occurred while processing the request.');
                    }
                });
            });

        });
        </script>

    </body>
</html>
