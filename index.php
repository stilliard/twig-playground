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

// log errors (they show in docker logs) but never display them, as that leaks paths and stack traces
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once dirname(__FILE__) . '/vendor/autoload.php';

// default files and vars
$defaultFiles = [
    'index.html.twig' => '<h1>{{ text | title }}</h1>
<ul>
{% for item in items %}
    <li>{{ item.name }}</li>
{% endfor %}
</ul>
{% include \'some-file.html.twig\' %}
',
    'some-file.html.twig' => '<p>{{ items | length }} items</p>
',
];
$defaultTwigVars = '{
    "text": "demo",
    "items": [
        { "name": "A" },
        { "name": "B" }
    ]
}';

// setup array of files (filename => contents) and vars (a JSON string), falling back to the defaults if malformed
$inputError = false;
$files = ! empty($_POST['files']) ? $_POST['files'] : $defaultFiles;
if ( ! is_array($files) || array_filter($files, 'is_string') !== $files) {
    $inputError = 'Invalid request: files must be a list of template names and contents';
    $files = $defaultFiles;
}
$twigVars = isset($_POST['twig-vars']) ? $_POST['twig-vars'] : $defaultTwigVars;
if ( ! is_string($twigVars)) {
    $inputError = 'Invalid request: variables must be a JSON string';
    $twigVars = $defaultTwigVars;
}

// output highlighting mode
$outputMode = stristr(array_keys($files)[0], '.css') ? 'css' : 'html';

// store the output for the preview iframe (render.php) with a single use token, returning the iframe src
function renderFrameSrc($output) {
    $_SESSION['render_token'] = bin2hex(random_bytes(16));
    $_SESSION['render_output'] = $output;
    return 'render.php?token=' . $_SESSION['render_token'];
}

// fatal errors (e.g. timeout or memory exhaustion while rendering) can't be caught,
// so for ajax requests respond with the usual JSON and a friendly message instead
if (isset($_POST['ajax'])) {
    register_shutdown_function(function () use ($outputMode) {
        $error = error_get_last();
        if ( ! $error || ! ($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR))) {
            return;
        }
        if (str_contains($error['message'], 'Maximum execution time')) {
            $output = 'Render took too long';
        } elseif (str_contains($error['message'], 'Allowed memory size')) {
            $output = 'Render used too much memory';
        } else {
            $output = 'Render failed';
        }
        $frameSrc = renderFrameSrc($output);
        session_write_close();
        header('Content-Type: application/json', true, 200); // PHP sets a 500 on fatal errors
        echo json_encode([
            'output' => $output,
            'mode' => $outputMode,
            'status' => 'Render failed',
            'error' => true,
            'frameSrc' => $frameSrc,
        ]);
    });
}

// decode the json, check for errors
$jsonError = false;
$twigVarsArray = json_decode($twigVars, true);
if ( ! is_array($twigVarsArray)) {
    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            $jsonError = 'Variables must be a JSON object';
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

// render state, shown in the header status
$renderError = true;
$renderMs = null;

// malformed request
if ($inputError) {
    $output = $inputError;
}
// if no json errors
elseif ( ! $jsonError) {

    try {

        // read in the files
        $loader = new Twig\Loader\ArrayLoader($files);
        $twig = new Twig\Environment($loader, [
            'debug' => true,
            'cache' => false,
            'optimizations' => 0,
            'strict_variables' => true,
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

        // render twig templates, limiting how long that can take
        set_time_limit(3);
        $renderStart = hrtime(true);
        $output = $twig->render(array_keys($files)[0], $twigVarsArray);
        $renderMs = max(1, (int) round((hrtime(true) - $renderStart) / 1e6));
        $renderError = false;

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
    catch (\Throwable $e) {
        error_log((string) $e);
        $output = 'Render failed'; // unexpected error, details are only logged
    }
}
else {
    $output = 'Json error: ' . $jsonError;
}

// header status text
if ($jsonError) {
    $status = 'Invalid JSON';
} elseif ($renderError) {
    $status = 'Render failed';
} else {
    $status = 'Rendered in ' . $renderMs . 'ms';
}

// hand the output to the preview iframe, then release the session lock so render.php can load it
$frameSrc = renderFrameSrc($output);
session_write_close();

// ajax renders only need the results
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'output' => $output,
        'mode' => $outputMode,
        'status' => $status,
        'error' => $renderError,
        'frameSrc' => $frameSrc,
    ]);
    exit;
}

// file tab ids can't contain dots
function fileId($filename) {
    return 'file-' . str_replace('.', '☺', $filename);
}

?><!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Twig Playground</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap">

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css" integrity="sha384-zaeBlB/vwYsDRSlFajnDd7OydJ0cWk+c2OWybl3eSUf6hW2EbhlCsQPqKr3gkznT" crossorigin="anonymous" referrerpolicy="no-referrer">

        <link rel="stylesheet" href="style.css">

        <!-- set the theme before first paint: saved choice, else the system theme -->
        <script>
        (function () {
            var theme;
            try { theme = localStorage.getItem('theme'); } catch (e) {}
            if ( ! theme) theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
        </script>
    </head>
    <body>

        <form id="twig-form" method="POST">

            <header class="app-header">
                <a href="/" class="brand"><span class="mark">{%</span> Twig Playground</a>
                <div class="header-actions">
                    <span id="render-status" class="render-status<?php if ($renderError) echo ' is-error'; ?>"><span class="dot"></span><span class="text"><?php echo $status; ?></span></span>
                    <button type="button" id="theme-toggle" class="theme-toggle" title="Toggle light/dark mode" aria-label="Toggle light/dark mode">
                        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    </button>
                    <a href="/" class="btn">Reset</a>
                </div>
            </header>

            <main class="app-shell">

                <div class="app-col">

                    <!-- enter variables -->
                    <section class="pane">
                        <div class="pane-head">
                            <h2>Variables <span class="dim">JSON</span></h2>
                            <span id="vars-meta" class="pane-meta"></span>
                        </div>
                        <div class="card">
                            <textarea name="twig-vars" id="twig-vars"><?php echo htmlspecialchars($twigVars); ?></textarea>
                        </div>
                    </section>

                    <!-- template files -->
                    <section class="pane">
                        <div class="pane-head">
                            <h2>Templates</h2>
                            <span class="pane-meta">First file renders; others can be included</span>
                        </div>
                        <div class="card">
                            <ul class="file-tabs">
                            <?php
                            $active = true;
                            foreach ($files as $filename => $input) { ?>
                                <li<?php if ($active) echo ' class="active"'; ?>><a href="#<?php echo htmlspecialchars(fileId($filename)); ?>" title="Double-click to rename"><?php echo htmlspecialchars($filename); ?></a><span class="entry-badge">entry</span><button type="button" class="delete-file-btn" title="Delete <?php echo htmlspecialchars($filename); ?>" aria-label="Delete <?php echo htmlspecialchars($filename); ?>">×</button></li>
                            <?php $active = false;
                            } ?>
                                <li class="add-file">
                                    <input type="text" id="new-file-name" placeholder="some-file.html.twig" hidden>
                                    <button type="button" id="add-file-btn">+ File</button>
                                </li>
                            </ul>
                            <div class="file-contents">
                            <?php
                            $active = true;
                            foreach ($files as $filename => $input) { ?>
                                <div id="<?php echo htmlspecialchars(fileId($filename)); ?>" class="file-content<?php if ($active) echo ' active'; ?>">
                                    <textarea name="files[<?php echo htmlspecialchars($filename); ?>]" class="file-input"><?php echo htmlspecialchars($input); ?></textarea>
                                </div>
                            <?php $active = false;
                            } ?>
                            </div>
                        </div>
                    </section>

                </div>

                <div class="app-col">

                    <!-- rendered output -->
                    <section class="pane">
                        <div class="pane-head">
                            <h2>Preview</h2>
                            <span class="pane-meta">Updates as you type</span>
                        </div>
                        <iframe id="render-frame"
                            src="<?php echo htmlspecialchars($frameSrc); ?>"
                            sandbox="allow-same-origin"
                            class="card rendered-frame"
                            title="Rendered preview"
                        ></iframe>
                    </section>

                    <!-- raw html output -->
                    <section class="pane">
                        <div class="pane-head">
                            <h2>HTML output</h2>
                            <button type="button" id="copy-output-btn" class="link-btn">Copy</button>
                        </div>
                        <div class="card">
                            <code class="file-output" data-mode="<?php echo $outputMode; ?>"><?php echo htmlspecialchars($output); ?></code>
                        </div>
                    </section>

                </div>

            </main>

        </form>

        <footer class="app-footer">
            <span>Provided by <a href="https://wildfireinternet.co.uk">Wildfire Internet</a></span>
            <span class="footer-links">
                <a href="https://twig.symfony.com/doc/3.x/">Twig docs</a>
                <a href="https://github.com/stilliard/twig-playground">GitHub</a>
            </span>
        </footer>

        <!-- bring in jquery lib -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha384-vtXRMe3mGCbOeY7l30aIg8H9p3GdeSe4IFlP6G8JMa7o7lXvnz3GFKzPxzJdPfGK" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <!-- bring in codemirror syntax editor -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js" integrity="sha384-UXbkZAbZYZ/KCAslc6UO4d6UHNKsOxZ/sqROSQaPTZCuEIKhfbhmffQ64uXFOcma" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js" integrity="sha384-kmQrbJf09Uo1WRLMDVGoVG3nM6F48frIhcj7f3FDUjeRzsiHwyBWDjMUIttnIeAf" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/css/css.min.js" integrity="sha384-to8njsu2GAiXQnY/aLGzz0DIY/SFSeSDodtvSl869n2NmsBdHOTZNNqbEBPYh7Pa" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

        <!-- main script for this page -->
        <script src="app.js"></script>

    </body>
</html>
