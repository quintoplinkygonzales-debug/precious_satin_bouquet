<?php
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (password_verify($pass, $row['password'])) {
            $_SESSION['user_id']        = $row['id'];
            $_SESSION['username']       = $row['username'];
            $_SESSION['role']           = $row['role'];
            $_SESSION['just_logged_in'] = true;

            // Detect new vs returning user based on login_count column (if it exists)
            // Falls back to "returning" safely if column is missing
            $isNew = false;
            if (isset($row['login_count'])) {
                $isNew = ((int)$row['login_count'] === 0);
                // Increment login_count
                $upd = $conn->prepare("UPDATE users SET login_count = login_count + 1 WHERE id = ?");
                $upd->bind_param("i", $row['id']);
                $upd->execute();
            }
            $_SESSION['is_new_user'] = $isNew;

            header("Location: " . ($row['role'] == 'admin' ? "admin_home.php" : "index.php"));
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        $error = "No account found with that username.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Precious Satin Bouquet</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blush:      #f9d0dd;
            --petal:      #f2a7bf;
            --rose:       #e0457b;
            --hot-pink:   #ff2d78;
            --magenta:    #c9186a;
            --deep-rose:  #b84d6a;
            --champagne:  #fdf3f6;
            --soft-white: #fffafc;
            --text-dark:  #3a1f28;
            --text-mid:   #7a4459;
            --text-hint:  #c4909f;
        }

        /* ── Root layout: two columns, each exactly 100vh tall ── */
        html, body {
            height: 100%;
            overflow: hidden; /* no outer scrollbar — each panel scrolls itself */
        }
        body {
            display: flex;
            font-family: 'Jost', sans-serif;
            background: var(--champagne);
        }

        /* ══ ART PANEL ══ */
        .panel-art {
            flex: 1;
            height: 100vh;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem 3.5rem;
        }
        @media (max-width: 820px) { .panel-art { display: none; } }

        .art-bg {
            position: absolute;
            inset: 0; width: 100%; height: 100%;
        }
        .art-grain {
            position: absolute; inset: 0; pointer-events: none; z-index: 1;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
        }

        .art-top {
            position: relative; z-index: 3; flex-shrink: 0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .art-wordmark {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem; font-weight: 600; color: #fff;
            letter-spacing: 0.04em; text-shadow: 0 2px 20px rgba(0,0,0,0.25);
        }
        .art-badge {
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 50px; padding: 6px 18px;
            font-size: 0.72rem; letter-spacing: 0.18em; text-transform: uppercase;
            color: #fff; font-weight: 500; backdrop-filter: blur(10px);
        }

        .art-deco { position: absolute; inset: 0; pointer-events: none; z-index: 2; }

        @keyframes bloomA { 0%,100%{transform:scale(1) rotate(0deg);} 50%{transform:scale(1.07) rotate(6deg);} }
        @keyframes bloomB { 0%,100%{transform:scale(1) rotate(0deg);} 50%{transform:scale(1.09) rotate(-8deg);} }
        @keyframes bloomC { 0%,100%{transform:scale(1) rotate(0deg);} 50%{transform:scale(1.05) rotate(4deg);} }

        .art-floaters { position: absolute; inset: 0; pointer-events: none; z-index: 2; overflow: hidden; }
        .art-floater {
            position: absolute; opacity: 0;
            animation: floatPetal linear infinite;
        }
        @keyframes floatPetal {
            0%   { opacity:0; transform:translateY(0) rotate(0deg) scale(var(--sc)); }
            8%   { opacity:.7; }
            92%  { opacity:.5; }
            100% { opacity:0; transform:translateY(-110vh) rotate(var(--spin)) scale(var(--sc)); }
        }

        .art-bottom {
            position: relative; z-index: 3; flex-shrink: 0;
        }
        .art-quote {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3.2rem; font-weight: 300; font-style: italic; color: #fff;
            line-height: 1.2; text-shadow: 0 4px 30px rgba(100,0,40,0.4);
            margin-bottom: 1.25rem;
        }
        .art-meta {
            font-size: 0.75rem; letter-spacing: 0.25em; text-transform: uppercase;
            color: rgba(255,255,255,0.7); font-weight: 400;
        }

        /* ══ FORM PANEL ══
           Fixed width, full viewport height, scrollable internally
           so nothing ever gets clipped. */
        .panel-form {
            width: 460px;
            flex-shrink: 0;
            height: 100vh;
            overflow-y: auto;
            background: var(--soft-white);
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: column;
            padding: 0 3rem;
        }
        .panel-form-inner {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2.5rem 0 3rem;
            min-height: min-content;
        }
        @media (max-width: 820px) {
            .panel-form {
                width: 100%;
                height: auto;
                min-height: 100vh;
                padding: 0 1.75rem;
            }
            .panel-form-inner { padding: 2rem 0 3rem; }
        }

        /* Pink top stripe — stays pinned inside the panel */
        .panel-form::before {
            content: '';
            position: sticky;
            top: 0;
            flex-shrink: 0;
            display: block;
            height: 4px;
            margin: 0 -3rem;
            background: linear-gradient(90deg, var(--hot-pink), var(--petal), var(--rose));
            z-index: 20;
        }

        /* ── Brand ── */
        .brand {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 2.75rem; flex-shrink: 0;
        }
        .brand img {
            width: 54px; height: 54px; border-radius: 50%;
            object-fit: contain; background: #fff; padding: 4px; flex-shrink: 0;
            box-shadow: 0 4px 18px rgba(224,69,123,0.3);
        }
        .brand-dot {
            width: 54px; height: 54px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, var(--rose), var(--magenta));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 18px rgba(224,69,123,0.35);
        }
        .brand-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.3rem; font-weight: 600; color: var(--deep-rose); letter-spacing: 0.04em;
        }

        /* ── Headings ── */
        .form-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.8rem; font-weight: 300; color: var(--text-dark);
            line-height: 1.1; margin-bottom: 0.5rem;
        }
        .form-title em { font-style: italic; color: var(--rose); }
        .form-subtitle {
            font-size: 0.9rem; color: var(--text-mid); font-weight: 300; margin-bottom: 2rem;
        }

        /* ── Tabs ── */
        .tabs {
            display: flex; border-bottom: 1.5px solid var(--blush);
            margin-bottom: 2rem; gap: 2rem;
        }
        .tabs .tab, .tabs a {
            font-size: 0.8rem; font-weight: 500; letter-spacing: 0.12em;
            padding: 0.6rem 0; text-decoration: none;
            border-bottom: 2.5px solid transparent;
            margin-bottom: -1.5px; transition: color 0.2s; text-transform: uppercase;
        }
        .tabs .tab { color: var(--rose); border-bottom-color: var(--rose); }
        .tabs a { color: var(--text-hint); }
        .tabs a:hover { color: var(--deep-rose); }

        /* ── Messages ── */
        .msg-error {
            display: flex; align-items: flex-start; gap: .65rem;
            background: #fff0f6; border: 1.5px solid var(--petal);
            border-radius: 10px; padding: .85rem 1rem;
            margin-bottom: 1.5rem; font-size: .88rem; color: var(--deep-rose);
        }
        .msg-success {
            background: #f0fdf4; border: 1.5px solid #86efac;
            border-radius: 10px; padding: .85rem 1rem;
            margin-bottom: 1.5rem; font-size: .88rem; color: #166534;
            display: flex; align-items: center; gap: .5rem;
        }

        /* ── Fields ── */
        .field { margin-bottom: 1.4rem; }
        .field label {
            display: block; font-size: 0.7rem; letter-spacing: 0.2em;
            text-transform: uppercase; color: var(--text-mid); font-weight: 500; margin-bottom: 8px;
        }
        .field input {
            font-family: 'Jost', sans-serif; font-size: 0.93rem; font-weight: 300;
            color: var(--text-dark); width: 100%; background: var(--champagne);
            border: 1.5px solid rgba(242,167,191,0.4); border-radius: 50px;
            padding: 14px 22px; outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .field input::placeholder { color: var(--text-hint); }
        .field input:focus {
            border-color: var(--rose); background: var(--soft-white);
            box-shadow: 0 0 0 3px rgba(224,69,123,.12);
        }

        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 72px; }
        .pw-toggle {
            position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
            background: none; border: none; font-family: 'Jost', sans-serif;
            font-size: 0.7rem; font-weight: 600; letter-spacing: 0.08em;
            color: var(--rose); cursor: pointer; padding: 4px; text-transform: uppercase;
        }
        .pw-toggle:hover { color: var(--magenta); }

        .row-forgot { text-align: right; margin: -.5rem 0 1.75rem; }
        .row-forgot a { font-size: .82rem; color: var(--rose); text-decoration: none; }
        .row-forgot a:hover { text-decoration: underline; }

        /* ── Button ── */
        .btn-primary {
            font-family: 'Jost', sans-serif; font-size: .82rem; font-weight: 600;
            letter-spacing: .18em; text-transform: uppercase; color: #fff;
            background: linear-gradient(135deg, var(--hot-pink), var(--magenta));
            border: none; border-radius: 50px; padding: 17px 32px; width: 100%;
            cursor: pointer; margin-bottom: 1.75rem;
            transition: transform .2s, box-shadow .2s, filter .2s;
            box-shadow: 0 8px 24px rgba(201,24,106,.35);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(201,24,106,.45); filter: brightness(1.06); }
        .btn-primary:active { transform: translateY(0); }

        .divider { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--blush); }
        .divider span { font-size: .8rem; color: var(--text-hint); }

        .admin-link { text-align: center; font-size: .86rem; color: var(--text-mid); margin-bottom: 1.25rem; }
        .admin-link a { color: var(--rose); font-weight: 500; text-decoration: none; }
        .admin-link a:hover { text-decoration: underline; }

        .form-footer { text-align: center; font-size: .72rem; color: var(--text-hint); line-height: 1.6; }
    </style>
<?php include __DIR__ . '/pwa_head.php'; ?>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='32' fill='%23d96b8a'/%3E%3Ctext x='32' y='43' font-size='30' font-family='Georgia,serif' font-style='italic' text-anchor='middle' fill='white'%3ESB%3C/text%3E%3C/svg%3E">
</head>
<body>

<!-- ══ ART PANEL ══ -->
<div class="panel-art">
    <svg class="art-bg" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <radialGradient id="g1" cx="70%" cy="20%" r="75%">
                <stop offset="0%"   stop-color="#ff2d78"/>
                <stop offset="50%"  stop-color="#e0457b"/>
                <stop offset="100%" stop-color="#8b1a4a"/>
            </radialGradient>
            <radialGradient id="g2" cx="20%" cy="80%" r="65%">
                <stop offset="0%"   stop-color="#ff6eb4"/>
                <stop offset="100%" stop-color="#c9186a" stop-opacity="0"/>
            </radialGradient>
            <radialGradient id="g3" cx="85%" cy="65%" r="55%">
                <stop offset="0%"   stop-color="#ffabd0" stop-opacity="0.6"/>
                <stop offset="100%" stop-color="#ff2d78" stop-opacity="0"/>
            </radialGradient>
            <filter id="b1"><feGaussianBlur stdDeviation="28"/></filter>
            <filter id="b2"><feGaussianBlur stdDeviation="45"/></filter>
        </defs>
        <rect width="800" height="900" fill="#8b1a4a"/>
        <ellipse cx="600" cy="150" rx="420" ry="380" fill="url(#g1)" filter="url(#b1)" opacity="0.95"/>
        <ellipse cx="150" cy="700" rx="380" ry="340" fill="url(#g2)" filter="url(#b2)" opacity="0.85"/>
        <ellipse cx="700" cy="600" rx="300" ry="280" fill="url(#g3)" filter="url(#b1)" opacity="0.7"/>
        <ellipse cx="500" cy="300" rx="200" ry="120" fill="#ffabd0" opacity="0.22" filter="url(#b1)" transform="rotate(-30 500 300)"/>
        <ellipse cx="200" cy="250" rx="150" ry="90"  fill="#ff6eb4" opacity="0.18" filter="url(#b1)" transform="rotate(20 200 250)"/>
        <ellipse cx="650" cy="750" rx="180" ry="100" fill="#ff2d78" opacity="0.25" filter="url(#b1)" transform="rotate(-15 650 750)"/>
        <ellipse cx="560" cy="180" rx="80"  ry="40"  fill="white" opacity="0.14" transform="rotate(-25 560 180)"/>
        <ellipse cx="320" cy="420" rx="60"  ry="28"  fill="white" opacity="0.10" transform="rotate(15 320 420)"/>
        <ellipse cx="700" cy="520" rx="50"  ry="22"  fill="white" opacity="0.12" transform="rotate(-40 700 520)"/>
        <ellipse cx="420" cy="680" rx="55"  ry="24"  fill="white" opacity="0.09" transform="rotate(-20 420 680)"/>
        <ellipse cx="300" cy="550" rx="200" ry="60"  fill="#5c0a30" opacity="0.30" filter="url(#b1)" transform="rotate(25 300 550)"/>
        <ellipse cx="680" cy="350" rx="160" ry="50"  fill="#7a0040" opacity="0.22" filter="url(#b1)" transform="rotate(-35 680 350)"/>
    </svg>
    <div class="art-grain"></div>

    <div class="art-deco">
        <svg style="position:absolute;top:80px;right:40px;" width="180" height="180" viewBox="-90 -90 180 180">
            <g style="animation:bloomA 5s ease-in-out infinite;transform-origin:0 0;">
                <ellipse rx="42" ry="16" fill="#ffcce6" opacity="0.85" transform="rotate(0)"/>
                <ellipse rx="42" ry="16" fill="#ff9dc8" opacity="0.80" transform="rotate(30)"/>
                <ellipse rx="42" ry="16" fill="#ffcce6" opacity="0.75" transform="rotate(60)"/>
                <ellipse rx="42" ry="16" fill="#ff9dc8" opacity="0.75" transform="rotate(90)"/>
                <ellipse rx="42" ry="16" fill="#ffcce6" opacity="0.70" transform="rotate(120)"/>
                <ellipse rx="42" ry="16" fill="#ff9dc8" opacity="0.70" transform="rotate(150)"/>
                <circle r="24" fill="#fff0f8" opacity="0.9"/>
                <circle r="14" fill="#ffd6ec" opacity="0.95"/>
                <circle r="7"  fill="#ff6eb4" opacity="0.9"/>
            </g>
        </svg>
        <svg style="position:absolute;top:320px;left:30px;" width="130" height="130" viewBox="-65 -65 130 130">
            <g style="animation:bloomB 4.5s ease-in-out infinite;transform-origin:0 0;">
                <ellipse rx="32" ry="12" fill="#ffd6ec" opacity="0.82" transform="rotate(0)"/>
                <ellipse rx="32" ry="12" fill="#ff9dc8" opacity="0.78" transform="rotate(45)"/>
                <ellipse rx="32" ry="12" fill="#ffd6ec" opacity="0.74" transform="rotate(90)"/>
                <ellipse rx="32" ry="12" fill="#ff9dc8" opacity="0.74" transform="rotate(135)"/>
                <circle r="18" fill="#fff0f8" opacity="0.88"/>
                <circle r="10" fill="#ffabd0" opacity="0.9"/>
                <circle r="5"  fill="#c9186a" opacity="0.85"/>
            </g>
        </svg>
        <svg style="position:absolute;bottom:220px;right:70px;" width="90" height="90" viewBox="-45 -45 90 90">
            <g style="animation:bloomC 3.8s ease-in-out infinite;transform-origin:0 0;">
                <ellipse rx="22" ry="8" fill="#fff0f8" opacity="0.80" transform="rotate(0)"/>
                <ellipse rx="22" ry="8" fill="#ffd6ec" opacity="0.75" transform="rotate(36)"/>
                <ellipse rx="22" ry="8" fill="#fff0f8" opacity="0.70" transform="rotate(72)"/>
                <ellipse rx="22" ry="8" fill="#ffd6ec" opacity="0.70" transform="rotate(108)"/>
                <ellipse rx="22" ry="8" fill="#fff0f8" opacity="0.65" transform="rotate(144)"/>
                <circle r="14" fill="#ffabd0" opacity="0.88"/>
                <circle r="7"  fill="#ff2d78" opacity="0.85"/>
            </g>
        </svg>
        <svg style="position:absolute;top:200px;right:160px;" width="50" height="50" viewBox="-25 -25 50 50">
            <g style="animation:bloomA 6s 1s ease-in-out infinite;transform-origin:0 0;">
                <ellipse rx="13" ry="5" fill="#fff0f8" opacity="0.75" transform="rotate(0)"/>
                <ellipse rx="13" ry="5" fill="#ffd6ec" opacity="0.70" transform="rotate(72)"/>
                <ellipse rx="13" ry="5" fill="#fff0f8" opacity="0.65" transform="rotate(144)"/>
                <circle r="8" fill="#ff9dc8" opacity="0.88"/>
                <circle r="4" fill="#fff"    opacity="0.9"/>
            </g>
        </svg>
        <svg style="position:absolute;bottom:320px;left:140px;" width="55" height="55" viewBox="-27 -27 54 54">
            <g style="animation:bloomB 5s 0.5s ease-in-out infinite;transform-origin:0 0;">
                <ellipse rx="14" ry="5.5" fill="#ffd6ec" opacity="0.78" transform="rotate(0)"/>
                <ellipse rx="14" ry="5.5" fill="#ff9dc8" opacity="0.72" transform="rotate(60)"/>
                <ellipse rx="14" ry="5.5" fill="#ffd6ec" opacity="0.66" transform="rotate(120)"/>
                <circle r="9"   fill="#fff0f8" opacity="0.88"/>
                <circle r="4.5" fill="#ff6eb4" opacity="0.85"/>
            </g>
        </svg>
        <div style="position:absolute;top:140px;left:120px;color:rgba(255,255,255,.5);font-size:18px;animation:bloomA 2.5s ease-in-out infinite;">✦</div>
        <div style="position:absolute;top:440px;right:130px;color:rgba(255,255,255,.4);font-size:14px;animation:bloomB 3s .8s ease-in-out infinite;">✦</div>
        <div style="position:absolute;bottom:280px;left:90px;color:rgba(255,255,255,.35);font-size:12px;animation:bloomC 3.5s .3s ease-in-out infinite;">✦</div>
        <div style="position:absolute;top:580px;right:50px;color:rgba(255,210,230,.5);font-size:20px;animation:bloomA 4s 1.2s ease-in-out infinite;">✿</div>
        <div style="position:absolute;top:260px;left:200px;color:rgba(255,210,230,.4);font-size:16px;animation:bloomB 3.2s .6s ease-in-out infinite;">✿</div>
    </div>

    <div class="art-floaters" id="artFloaters"></div>

    <div class="art-top">
        <div class="art-wordmark">Precious Satin Bouquet</div>
        <div class="art-badge">Est. 2026</div>
    </div>
    <div class="art-bottom">
        <div class="art-quote">"Where every<br>petal tells<br>a story."</div>
        <div class="art-meta">Precious Satin Bouquet &nbsp;·&nbsp; Fresh Flowers Daily</div>
    </div>
</div>

<!-- ══ FORM PANEL ══ -->
<div class="panel-form">
<div class="panel-form-inner">

    <div class="brand">
        <img src="images/logo/SB.png" alt="Precious Satin Bouquet"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="brand-dot" style="display:none;">🌸</div>
        <span class="brand-name">Precious Satin Bouquet</span>
    </div>

    <h1 class="form-title">Welcome <em>back!</em></h1>
    <p class="form-subtitle">Sign in to your account to continue.</p>

    <div class="tabs">
        <span class="tab">Login</span>
        <a href="register.php">Sign Up</a>
    </div>

    <?php if (isset($_GET['registered'])): ?>
        <div class="msg-success"><span>🌸</span><span>Account created! Please sign in below.</span></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="msg-error"><span>⚠</span><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <div class="field">
            <label for="username">Username or Email</label>
            <input type="text" id="username" name="username"
                   placeholder="Enter your username or email"
                   required autocomplete="username">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <div class="pw-wrap">
                <input type="password" id="password" name="password"
                       placeholder="Enter your password"
                       required autocomplete="current-password">
                <button type="button" class="pw-toggle" onclick="togglePw('password',this)">Show</button>
            </div>
        </div>
        <div class="row-forgot"><a href="forgot_password.php">Forgot password?</a></div>
        <button type="submit" class="btn-primary">Sign In</button>
    </form>

    <div class="divider"><span>or</span></div>
    <p class="admin-link">Staff or Admin? <a href="admin_login.php">Sign in here</a></p>
    <p class="form-footer">By signing in, you agree to our Terms of Service and Privacy Policy.</p>
</div>
</div>

<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? 'Show' : 'Hide';
}
(function(){
    const c = document.getElementById('artFloaters');
    if (!c) return;
    const petals = ['🌸','🌺','🌷','🌹','💮','🏵️','✿','❀'];
    for (let i = 0; i < 14; i++) {
        const el = document.createElement('div');
        el.className = 'art-floater';
        el.textContent = petals[i % petals.length];
        const dur   = 8 + Math.random() * 10;
        const delay = -(Math.random() * dur);
        const sc    = 0.5 + Math.random() * 0.8;
        const spin  = (Math.random() * 360 - 180) + 'deg';
        el.style.cssText = `left:${Math.random()*95}%;top:${90+Math.random()*20}%;--sc:${sc};--spin:${spin};font-size:${1.2+Math.random()*1.6}rem;animation-duration:${dur}s;animation-delay:${delay}s;`;
        c.appendChild(el);
    }
})();
</script>
</body>
</html>