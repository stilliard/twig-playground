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
        $loader = new Twig_Loader_Array($files);
        $twig = new Twig_Environment($loader);

        // render twig templates
        $output = $twig->render('index.html.twig', $twigVarsArray);

    }
    // show user errors
    catch (Twig_Error_Syntax $e) {
        $output = 'Twig syntax error: ' . $e->getMessage();
    }
    catch (Twig_Error_Runtime $e) {
        $output = 'Twig runtime error: ' . $e->getMessage();
    }
    catch (Twig_Error_Loader $e) {
        $output = 'Twig loader error: ' . $e->getMessage();
    }
    catch (Twig_Error $e) {
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

        </style>
    </head>
    <body>

        <form method="POST">

            <header>
                <input type="submit" class="submit-btn" value="Render">
                <a href="/" class="reset-btn">Reset</a>

                <h1>Twig Playground</h1>
            </header>
        
            <!-- enter variables -->
            <p class="title-tab-container"><span class="title-tab-style">JSON variables:</span></p>
            <textarea name="twig-vars" id="twig-vars"><?php echo $twigVars; ?></textarea>
            
            <div class="twig-links">
                <a href="http://twig.sensiolabs.org/doc/templates.html" target="_blank">Twig Syntax Intro</a>
                | <a href="http://twig.sensiolabs.org/documentation#reference" target="_blank">Twig Reference</a>
            </div>

            <p class="title-tab-container"><span class="title-tab-style">Setup twig files:</span></p>

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
                <p class="title-tab-container"><span class="title-tab-style">Result:</span></p>
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
            $('.file-names-list').on('click', 'a', function () {

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
                mode: 'text/html',
                lineNumbers: true,
                viewportMargin: Infinity
            });

        });
        </script>

    </body>
</html>

