<?php

error_reporting(E_ALL);
ini_set('display_errors', true);

require_once dirname(__FILE__) . '/vendor/autoload.php';

// setup array of files
$files = isset($_POST['files']) && ! empty($_POST['files']) ? $_POST['files'] : [
    'index.html.twig' => '
<h1>{{ text | title }}</h1>
<ul>
{% for item in items %}
    <li>{{ item.name }}</li>
{% endfor %}
</ul>
',
];

// set vars
$twigVars = isset($_POST['twig-vars']) ? $_POST['twig-vars'] : '{ "text": "demo", "items": [{ "name": "A" }, { "name": "B" }] }';

$loader = new Twig_Loader_Array($files);
$twig = new Twig_Environment($loader);

$output = $twig->render('index.html.twig', json_decode($twigVars, true));

?><!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Twig Playground</title>
        
        <style>
        
        body { font: 14px/1.3 sans-serif; margin: 0 auto; max-width: 960px; padding: 10px; }
       
        div, ul { padding: 0; margin: 0; }
        
        .file-names-list { width: 20%; float: left; }
        .file-contents { width: 80%; float: left; }

        .file-names-list li { padding: 4px; }
        .file-names-list li.active { background-color: #CCC; }
        .file-names-list a { display: inline-block; width: 100%; }

        .file-content { display: none; }
        .file-content.active { display: block; }
        .file-content textarea { width: 100%; min-height: 200px; }
        
        #twig-vars { width: 100%; }
        
        .file-output { clear: both; border: 1px solid #CCC; background-color: #EEE; padding: 4px; }
        
        .submit-btn,
        .reset-btn,
        .twig-links { float: right; padding: 5px; }
    
        #add-file-btn { font-style: italic; }

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
            <p>
                <label for="twig-vars">JSON variables: </label>
                <textarea name="twig-vars" id="twig-vars"><?php echo $twigVars; ?></textarea>
            </p>
            
            <div class="twig-links">
                <a href="http://twig.sensiolabs.org/doc/templates.html" target="_blank">Twig Syntax Intro</a>
                | <a href="http://twig.sensiolabs.org/documentation#reference" target="_blank">Twig Reference</a>
            </div>

            <p>Setup twig files:</p>

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
            <p>Result:</p>
            <pre class="file-output"><?php echo htmlspecialchars($output); ?></pre>

        </form>

        <!-- bring in jquery lib -->
        <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>

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

                var tab;
                
                // hide current tabs
                $('.file-names-list li.active, .file-content.active').removeClass('active');
                
                // show this tab
                $(this).parent().addClass('active');
                tab = $($(this).attr('href')).addClass('active');

                // put cursor into textarea
                tab.find('textarea').focus();

                return false;
            });

        });
        </script>

    </body>
</html>

