<?php
// Configure session
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

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
$twigVars = isset($_POST['twig-vars']) ? $_POST['twig-vars'] : json_encode(["text" => "demo", "items" => [["name" => "A"], ["name" => "B"]]], JSON_PRETTY_PRINT);

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
            'strict_variables' => true,
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

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/default.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/material-darker.min.css">

        <link rel="stylesheet" href="style.css">

        <!-- PHP-driven theme override (depends on the selected theme) -->
        <style>
        <?php if (! isset($_POST['theme']) || $_POST['theme'] == '') { ?>@media (prefers-color-scheme: dark) {<?php } ?>
        <?php if (! isset($_POST['theme']) || $_POST['theme'] == '' || $_POST['theme'] == 'dark') { ?>
            :root {
                --bg: #0b0f17;
                --surface: #11161f;
                --surface-2: #161c28;
                --border: #232c3b;
                --border-strong: #303a4d;
                --text: #d6deeb;
                --text-dim: #8693a8;
                --accent: #4d9fff;
                --accent-hover: #6cb0ff;
                --accent-soft: rgba(77, 159, 255, 0.16);
                --danger: #ff6b6b;
                --shadow: 0 1px 2px rgba(0, 0, 0, 0.4), 0 8px 28px rgba(0, 0, 0, 0.4);
                color-scheme: dark;
            }
        <?php } ?>
        <?php if (! isset($_POST['theme']) || $_POST['theme'] == '') { ?>}<?php } ?>
        </style>
    </head>
    <body>

        <form id="twig-form" method="POST">

            <header class="app-header">
                <h1 class="brand"><span class="mark">{{</span> Twig Playground</h1>
                <div class="header-actions">
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

          <div class="app-shell">

            <!-- enter variables -->
            <div class="mb-4">
                <h2 class="section-label">JSON variables</h2>
                <p class="section-hint">These will become variables available to the twig template files</p>
                <textarea name="twig-vars" id="twig-vars" class="form-control"><?php echo $twigVars; ?></textarea>
            </div>

            <div class="mb-4">
                <h2 class="section-label">Twig Files</h2>
                <p class="section-hint">Only the first file is compiled, but other files can be included or extended</p>
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
                <div class="row">
                    <div class="col-md-6">
                        <h2 class="section-label">Raw Output</h2>
                        <code class="file-output" data-mode="<?php echo stristr(array_keys($files)[0], '.css') ? 'css' : 'html'; ?>"><?php echo htmlspecialchars($output); ?></code>
                    </div>
                    <div class="col-md-6">
                        <h2 class="section-label">Rendered Output</h2>
                        <?php
                        // Generate a unique token for this render
                        $_SESSION['render_token'] = uniqid('render_', true);
                        ?>
                        <iframe id="render-frame"
                            src="render.php?output=<?php echo urlencode(base64_encode($output)); ?>&token=<?php echo $_SESSION['render_token']; ?>"
                            sandbox="allow-same-origin"
                            class="rendered-frame"
                        ></iframe>
                    </div>
                </div>
            </div>

          </div><!-- /.app-shell -->

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
        <script src="app.js"></script>

    </body>
</html>
