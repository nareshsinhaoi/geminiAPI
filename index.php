<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use GeminiAPI\Client;
use GeminiAPI\Resources\Parts\TextPart;

session_start();

/* ---------- Config ---------- */
$apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
$models = array_filter(array_map('trim', explode(',',
    getenv('GEMINI_MODELS') ?: 'gemini-3.5-flash-lite,gemini-3.1-flash-lite,gemini-3.6-flash'
)));
const MAX_QUESTION_LENGTH = 2000;
const MAX_HISTORY = 20;
const SITE_NAME = 'Ask Gemini';

/* ---------- Gemini helpers ---------- */
function shortError(Throwable $e): string
{
    $msg = $e->getMessage();
    if (preg_match('/status_code=(\d+)/', $msg, $m)) {
        $line = "HTTP {$m[1]}";
        if (preg_match('/"message":\s*"([^"\\\\]*)/', $msg, $mm)) {
            $line .= ': ' . trim($mm[1]);
        }
        return $line;
    }
    return strtok($msg, "\n") ?: 'Unknown error';
}

function askGemini(Client $client, array $models, string $prompt): string
{
    $errors = [];

    foreach ($models as $model) {
        for ($i = 1; $i <= 2; $i++) {
            try {
                return $client
                    ->generativeModel($model)
                    ->generateContent(new TextPart($prompt))
                    ->text();
            } catch (Throwable $e) {
                $errors[] = "[$model] " . shortError($e);
                $msg = $e->getMessage();
                $retryable = str_contains($msg, 'status_code=503')
                          || str_contains($msg, 'status_code=500');
                if (!$retryable) {
                    break; // quota, bad model, timeout: next model
                }
                if ($i < 2) {
                    sleep(2);
                }
            }
        }
    }

    throw new RuntimeException(implode(PHP_EOL, $errors));
}

/* ---------- Request handling (POST, then redirect) ---------- */
$_SESSION['history'] = $_SESSION['history'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear'])) {
        $_SESSION['history'] = [];
    } else {
        $question = trim((string)($_POST['question'] ?? ''));

        if ($question !== '' && $apiKey !== '') {
            $question = mb_substr($question, 0, MAX_QUESTION_LENGTH);
            $entry = ['q' => $question, 'a' => null, 'error' => null, 'time' => date('H:i')];

            try {
                $entry['a'] = askGemini(new Client($apiKey), $models, $question);
            } catch (Throwable $e) {
                $entry['error'] = $e->getMessage();
            }

            $_SESSION['history'][] = $entry;
            $_SESSION['history'] = array_slice($_SESSION['history'], -MAX_HISTORY);
        }
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$parsedown = new Parsedown();
$parsedown->setSafeMode(true);

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$history = $_SESSION['history'];
$activeModel = $models ? reset($models) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(SITE_NAME) ?> | Questions and answers</title>
<meta name="description" content="Ask a question and get a clear answer from Gemini.">
<style>
:root{--bg:#f3f5f4;--sf:#fff;--tx:#17231f;--mu:#5f6e69;--ln:#dbe2df;--ac:#0f766e;--at:#fff;--ub:#0f766e;--ut:#fff;--sub:#eef2f0;--er:#fdecea;--et:#9b2c24;--sh:0 1px 2px rgba(23,35,31,.06),0 4px 14px rgba(23,35,31,.05)}
@media(prefers-color-scheme:dark){:root{--bg:#0e1413;--sf:#16201e;--tx:#e6efec;--mu:#93a59f;--ln:#25332f;--ac:#3fb8a9;--at:#06201c;--ub:#1c6b62;--ut:#fff;--sub:#1d2a27;--er:#3a1f1d;--et:#ffb7b0;--sh:none}}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;min-height:100vh;display:flex;flex-direction:column;background:var(--bg);color:var(--tx);font:16px/1.65 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;-webkit-font-smoothing:antialiased}
.wrap{width:100%;max-width:820px;margin:0 auto;padding:0 20px}
header.site{position:sticky;top:0;z-index:10;background:var(--sf);border-bottom:1px solid var(--ln)}
header.site .wrap{display:flex;align-items:center;justify-content:space-between;height:60px}
.brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:1.05rem;letter-spacing:-.01em;color:var(--tx);text-decoration:none}
.logo{width:30px;height:30px;border-radius:8px;background:var(--ac);color:var(--at);display:grid;place-items:center}
.chip{font-size:.78rem;color:var(--mu);border:1px solid var(--ln);border-radius:999px;padding:3px 11px;background:var(--sub)}
main{flex:1;width:100%;max-width:820px;margin:0 auto;padding:28px 20px 0}
.intro h1{font-size:1.65rem;line-height:1.25;margin:0 0 6px;letter-spacing:-.02em}
.intro p{margin:0 0 26px;color:var(--mu)}
.msg{display:flex;gap:12px;margin-bottom:22px}
.msg.u{flex-direction:row-reverse}
.av{flex:none;width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-size:.8rem;font-weight:700}
.u .av{background:var(--sub);color:var(--mu);border:1px solid var(--ln)}
.g .av{background:var(--ac);color:var(--at)}
.body{min-width:0;max-width:calc(100% - 50px)}
.meta{font-size:.78rem;color:var(--mu);margin:0 0 4px}
.u .meta{text-align:right}
.bub{border-radius:14px;padding:11px 16px;overflow-wrap:anywhere}
.u .bub{background:var(--ub);color:var(--ut);border-top-right-radius:4px;white-space:pre-wrap}
.g .bub{background:var(--sf);border:1px solid var(--ln);border-top-left-radius:4px;box-shadow:var(--sh);padding-top:2px;padding-bottom:2px}
.g .bub>:first-child{margin-top:.7em}
.g .bub>:last-child{margin-bottom:.7em}
.bub pre{overflow-x:auto;background:var(--sub);border:1px solid var(--ln);padding:12px;border-radius:10px;font-size:.88rem}
.bub code{font-family:ui-monospace,Consolas,Menlo,monospace;font-size:.9em}
.bub :not(pre)>code{background:var(--sub);padding:1px 5px;border-radius:5px}
.bub table{border-collapse:collapse;display:block;overflow-x:auto}
.bub th,.bub td{border:1px solid var(--ln);padding:6px 12px;text-align:left}
.bub a{color:var(--ac)}
.bub blockquote{margin:1em 0;padding-left:14px;border-left:3px solid var(--ln);color:var(--mu)}
.acts{margin-top:6px}
.lnk{background:none;border:0;padding:2px 0;font:inherit;font-size:.8rem;color:var(--mu);cursor:pointer}
.lnk:hover{color:var(--ac);text-decoration:underline}
.err{background:var(--er);color:var(--et);border-radius:12px;padding:11px 16px;font-size:.92rem}
.err pre{margin:8px 0 0;white-space:pre-wrap;font-size:.8rem}
.empty{border:1px dashed var(--ln);border-radius:14px;padding:26px 20px;background:var(--sf)}
.empty h2{margin:0 0 4px;font-size:1rem}
.empty p{margin:0 0 14px;color:var(--mu);font-size:.93rem}
.sg{display:flex;flex-wrap:wrap;gap:8px}
.sg button{background:var(--sub);border:1px solid var(--ln);color:var(--tx);border-radius:999px;padding:7px 14px;font:inherit;font-size:.88rem;cursor:pointer}
.sg button:hover{border-color:var(--ac);color:var(--ac)}
.composer{position:sticky;bottom:0;margin-top:8px;padding:14px 0 16px;background:linear-gradient(to top,var(--bg) 78%,transparent)}
.ask{display:flex;gap:10px;align-items:flex-end;background:var(--sf);border:1px solid var(--ln);border-radius:16px;padding:8px 8px 8px 16px;box-shadow:var(--sh)}
.ask:focus-within{border-color:var(--ac)}
.ask textarea{flex:1;resize:none;border:0;outline:0;background:transparent;color:var(--tx);font:inherit;min-height:28px;max-height:160px;padding:6px 0}
.send{flex:none;border:0;border-radius:11px;background:var(--ac);color:var(--at);font:inherit;font-weight:600;padding:10px 20px;cursor:pointer}
.send:hover{filter:brightness(1.08)}
.send[disabled]{opacity:.65;cursor:wait}
.send:focus-visible,.lnk:focus-visible,.sg button:focus-visible{outline:2px solid var(--ac);outline-offset:2px}
.hint{display:flex;justify-content:space-between;gap:12px;margin:7px 4px 0;font-size:.78rem;color:var(--mu)}
.alert{margin-bottom:20px}
footer.site{border-top:1px solid var(--ln);background:var(--sf);color:var(--mu);font-size:.82rem}
footer.site .wrap{display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px 20px;padding-top:16px;padding-bottom:calc(16px + env(safe-area-inset-bottom,0px))}
@media(max-width:560px){.intro h1{font-size:1.4rem}.chip{display:none}.body{max-width:calc(100% - 46px)}.send{padding:10px 14px}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
</style>
</head>
<body>

<header class="site">
    <div class="wrap">
        <a class="brand" href="<?= e(strtok($_SERVER['REQUEST_URI'], '?')) ?>">
            <span class="logo" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 6.6L21 11l-6.6 2.4L12 20l-2.4-6.6L3 11l6.6-2.4z"/></svg>
            </span>
            <?= e(SITE_NAME) ?>
        </a>
        <?php if ($activeModel): ?><span class="chip" title="Model used first"><?= e($activeModel) ?></span><?php endif; ?>
    </div>
</header>

<main>
    <div class="intro">
        <h1>What would you like to know?</h1>
        <p>Ask a question in plain language and get a clear answer.</p>
    </div>

    <?php if ($apiKey === ''): ?>
        <div class="err alert">The API key is missing. Add <code>GEMINI_API_KEY</code> to your <code>.env</code> file, then run <code>docker compose up -d</code>.</div>
    <?php endif; ?>

    <?php if (!$history): ?>
        <section class="empty">
            <h2>Start with a question</h2>
            <p>Type below, or pick an example.</p>
            <div class="sg">
                <button type="button" data-q="What is the capital of India? Explain in a few words.">Capital of India</button>
                <button type="button" data-q="Explain how a rainbow forms in simple terms.">How a rainbow forms</button>
                <button type="button" data-q="Give me 5 tips to make a website load faster.">Faster website tips</button>
            </div>
        </section>
    <?php endif; ?>

    <section id="thread" aria-live="polite">
    <?php foreach ($history as $item): ?>
        <article class="msg u">
            <span class="av" aria-hidden="true">You</span>
            <div class="body">
                <p class="meta">You<?= !empty($item['time']) ? ', ' . e($item['time']) : '' ?></p>
                <div class="bub"><?= e($item['q']) ?></div>
            </div>
        </article>
        <article class="msg g">
            <span class="av" aria-hidden="true">G</span>
            <div class="body">
                <p class="meta">Gemini</p>
                <?php if ($item['a'] !== null): ?>
                    <div class="bub answer"><?= $parsedown->text($item['a']) ?></div>
                    <div class="acts"><button type="button" class="lnk copy">Copy answer</button></div>
                <?php else: ?>
                    <div class="err">
                        Gemini could not answer this question. Try again in a moment.
                        <pre><?= e((string)$item['error']) ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </section>

    <div class="composer">
        <form class="ask" method="post" id="askForm">
            <textarea name="question" id="question" rows="1" maxlength="<?= MAX_QUESTION_LENGTH ?>"
                      placeholder="Type your question" aria-label="Your question" required autofocus></textarea>
            <button type="submit" class="send" id="send">Ask</button>
        </form>
        <div class="hint">
            <span>Enter to send, Shift+Enter for a new line</span>
            <?php if ($history): ?>
                <form method="post"><button type="submit" name="clear" value="1" class="lnk">Clear conversation</button></form>
            <?php endif; ?>
        </div>
    </div>
</main>

<footer class="site">
    <div class="wrap">
        <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
        <span>Answers are generated by AI and may contain mistakes. Please verify important information.</span>
    </div>
</footer>

<script>
(function(){
    var form=document.getElementById('askForm'),
        box=document.getElementById('question'),
        send=document.getElementById('send');

    function grow(){box.style.height='auto';box.style.height=Math.min(box.scrollHeight,160)+'px'}

    box.addEventListener('keydown',function(ev){
        if(ev.key==='Enter'&&!ev.shiftKey){
            ev.preventDefault();
            if(box.value.trim()!=='')form.requestSubmit();
        }
    });
    box.addEventListener('input',grow);

    form.addEventListener('submit',function(){
        send.disabled=true;
        send.textContent='Thinking\u2026';
    });

    document.querySelectorAll('.sg button').forEach(function(b){
        b.addEventListener('click',function(){
            box.value=b.getAttribute('data-q');
            grow();
            box.focus();
        });
    });

    document.querySelectorAll('.copy').forEach(function(b){
        b.addEventListener('click',function(){
            var text=b.closest('.body').querySelector('.answer').innerText;
            function done(){b.textContent='Copied';setTimeout(function(){b.textContent='Copy answer'},1500)}
            if(navigator.clipboard&&window.isSecureContext){
                navigator.clipboard.writeText(text).then(done);
            }else{
                var t=document.createElement('textarea');
                t.value=text;document.body.appendChild(t);t.select();
                document.execCommand('copy');document.body.removeChild(t);done();
            }
        });
    });

    var last=document.querySelector('#thread .msg:last-child');
    if(last){last.scrollIntoView({block:'end'})}
})();
</script>
</body>
</html>