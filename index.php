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
{% include \'some-file.html.twig\' %}
',
    'some-file.html.twig' => '<p>{{ items | length }} items</p>
',
];

// set vars
$twigVars = isset($_POST['twig-vars']) ? $_POST['twig-vars'] : '{
    "text": "demo",
    "items": [
        { "name": "A" },
        { "name": "B" }
    ]
}';

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

// output highlighting mode
$outputMode = stristr(array_keys($files)[0], '.css') ? 'css' : 'html';

// Generate a unique token for this render
$_SESSION['render_token'] = uniqid('render_', true);
$frameSrc = 'render.php?output=' . urlencode(base64_encode($output)) . '&token=' . $_SESSION['render_token'];

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

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css">

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
                                <li<?php if ($active) echo ' class="active"'; ?>><a href="#<?php echo htmlspecialchars(fileId($filename)); ?>" title="Double-click to rename"><?php echo htmlspecialchars($filename); ?></a><span class="entry-badge">entry</span><button type="button" class="delete-file-btn" title="Delete file">×</button></li>
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
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <!-- bring in codemirror syntax editor -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/css/css.min.js"></script>

        <!-- main script for this page -->
        <script src="app.js"></script>

    </body>
</html>
