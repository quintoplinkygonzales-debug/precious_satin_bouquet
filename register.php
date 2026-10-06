<?php
include "config.php";

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);
    $pass      = $_POST['password'];
    $confirm   = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $facebook     = trim($_POST['facebook']);
    $phone        = trim($_POST['phone']);
    $gender       = trim($_POST['gender'] ?? '');

    if (empty($username))                                              $errors[] = "Username is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = "A valid email address is required.";
    if (strlen($pass) < 8)                                            $errors[] = "Password must be at least 8 characters.";
    if ($pass !== $confirm)                                           $errors[] = "Passwords do not match.";
    if (empty($full_name))                                            $errors[] = "Full name is required.";
    if (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone))         $errors[] = "Phone number must be exactly 11 digits (numbers only).";
    $allowed_genders = ['Male','Female','Non-binary','Prefer not to say'];
    if (empty($gender) || !in_array($gender, $allowed_genders))        $errors[] = "Please select a gender.";

    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $errors[] = "That username or email is already registered.";
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $stmt   = $conn->prepare("INSERT INTO users (username, email, password, full_name, facebook, phone, gender, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'customer')");
            $stmt->bind_param("sssssss", $username, $email, $hashed, $full_name, $facebook, $phone, $gender);
            if ($stmt->execute()) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                $errors[] = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — Precious Satin Bouquet</title>
    <link rel="icon" type="image/png" href="images/logo/SB.png">
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

        html, body {
            height: 100%;
            overflow: hidden;
        }
        body {
            display: flex;
            font-family: 'Jost', sans-serif;
            background: var(--champagne);
        }

        /* ══ ART PANEL ══ */
        .panel-art {
            flex: 1; height: 100vh;
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 3rem 3.5rem;
        }
        @media (max-width: 820px) { .panel-art { display: none; } }

        .art-bg { position: absolute; inset: 0; width: 100%; height: 100%; }
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
            letter-spacing: 0.04em; text-shadow: 0 2px 20px rgba(0,0,0,.25);
        }
        .art-badge {
            background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.35);
            border-radius: 50px; padding: 6px 18px; font-size: .72rem;
            letter-spacing: .18em; text-transform: uppercase;
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

        .art-bottom { position: relative; z-index: 3; flex-shrink: 0; }
        .art-quote {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3rem; font-weight: 300; font-style: italic; color: #fff;
            line-height: 1.2; text-shadow: 0 4px 30px rgba(100,0,40,.4); margin-bottom: 1.25rem;
        }
        .art-meta {
            font-size: .75rem; letter-spacing: .25em; text-transform: uppercase;
            color: rgba(255,255,255,.7); font-weight: 400;
        }

        /* ══ FORM PANEL ══
           Fixed 500px wide, full 100vh tall, scrollable inside */
        .panel-form {
            width: 500px;
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
            padding: 2rem 0 2.5rem;
            min-height: min-content;
        }
        @media (max-width: 820px) {
            .panel-form { width: 100%; height: auto; min-height: 100vh; padding: 0 1.75rem; }
            .panel-form-inner { padding: 2rem 0 3rem; }
        }

        /* Top stripe */
        .panel-form::before {
            content: '';
            display: block;
            flex-shrink: 0;
            height: 4px;
            margin: 0 -3rem;
            background: linear-gradient(90deg, var(--hot-pink), var(--petal), var(--rose));
            position: sticky;
            top: 0;
            z-index: 20;
        }

        /* ── Brand ── */
        .brand { display: flex; align-items: center; gap: 14px; margin-bottom: 2rem; flex-shrink: 0; }
        .brand img {
            width: 54px; height: 54px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
            box-shadow: 0 4px 18px rgba(224,69,123,.3);
        }
        .brand-dot {
            width: 54px; height: 54px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, var(--rose), var(--magenta));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; box-shadow: 0 4px 18px rgba(224,69,123,.35);
        }
        .brand-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.3rem; font-weight: 600; color: var(--deep-rose); letter-spacing: .04em;
        }

        .form-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.5rem; font-weight: 300; color: var(--text-dark);
            line-height: 1.1; margin-bottom: .45rem;
        }
        .form-title em { font-style: italic; color: var(--rose); }
        .form-subtitle { font-size: .88rem; color: var(--text-mid); font-weight: 300; margin-bottom: 1.5rem; }

        .tabs { display: flex; border-bottom: 1.5px solid var(--blush); margin-bottom: 1.5rem; gap: 2rem; }
        .tabs .tab, .tabs a {
            font-size: .8rem; font-weight: 500; letter-spacing: .12em; padding: .6rem 0;
            text-decoration: none; border-bottom: 2.5px solid transparent;
            margin-bottom: -1.5px; transition: color .2s; text-transform: uppercase;
        }
        .tabs .tab { color: var(--rose); border-bottom-color: var(--rose); }
        .tabs a { color: var(--text-hint); }
        .tabs a:hover { color: var(--deep-rose); }

        .msg-errors {
            background: #fff0f6; border: 1.5px solid var(--petal);
            border-radius: 10px; padding: .85rem 1rem;
            margin-bottom: 1.25rem; font-size: .88rem; color: var(--deep-rose);
        }
        .msg-errors p { font-weight: 500; margin-bottom: .4rem; }
        .msg-errors ul { padding-left: 1.1rem; }
        .msg-errors li { line-height: 1.6; font-weight: 300; }

        /* Two-column grid */
        .fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
        .field-full  { grid-column: 1 / -1; }

        .field { margin-bottom: 1rem; }
        .field label {
            display: block; font-size: .7rem; letter-spacing: .18em;
            text-transform: uppercase; color: var(--text-mid); font-weight: 500; margin-bottom: 7px;
        }
        .field input {
            font-family: 'Jost', sans-serif; font-size: .9rem; font-weight: 300;
            color: var(--text-dark); width: 100%; background: var(--champagne);
            border: 1.5px solid rgba(242,167,191,.4); border-radius: 50px;
            padding: 12px 18px; outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .field input::placeholder { color: var(--text-hint); }
        .field input:focus {
            border-color: var(--rose); background: var(--soft-white);
            box-shadow: 0 0 0 3px rgba(224,69,123,.12);
        }

        /* ── Gender dropdown ── */
        .gender-wrap { position: relative; width: 100%; user-select: none; }
        .gender-trigger {
            display: flex; align-items: center; gap: 10px;
            font-family: 'Jost', sans-serif; font-size: .9rem; font-weight: 300;
            color: var(--text-dark); width: 100%; background: var(--champagne);
            border: 1.5px solid rgba(242,167,191,.4); border-radius: 50px;
            padding: 12px 18px; cursor: pointer;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .gender-trigger:hover, .gender-wrap.open .gender-trigger {
            border-color: var(--rose); background: var(--soft-white);
            box-shadow: 0 0 0 3px rgba(224,69,123,.12);
        }
        .gender-icon { font-size: 1rem; color: var(--rose); flex-shrink: 0; }
        .gender-label { flex: 1; }
        .gender-label.placeholder { color: var(--text-hint); }
        .gender-arrow { color: var(--text-hint); flex-shrink: 0; transition: transform .2s; }
        .gender-wrap.open .gender-arrow { transform: rotate(180deg); }

        .gender-dropdown {
            display: none;
            position: absolute; top: calc(100% + 6px); left: 0; right: 0;
            background: var(--soft-white);
            border: 1.5px solid rgba(242,167,191,.5);
            border-radius: 18px; overflow: hidden;
            box-shadow: 0 8px 28px rgba(180,60,100,.13);
            z-index: 9999;
        }
        .gender-wrap.open .gender-dropdown { display: block; }
        .gender-option {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 18px; cursor: pointer; font-size: .9rem;
            color: var(--text-dark); transition: background .15s;
        }
        .gender-option:hover { background: var(--blush); }
        .gender-option.selected { background: rgba(242,167,191,.2); font-weight: 600; color: var(--rose); }
        .gender-opt-icon { font-size: 1rem; color: var(--petal); width: 20px; text-align: center; flex-shrink: 0; }
        .gender-check { margin-left: auto; color: var(--rose); font-size: .85rem; opacity: 0; }
        .gender-option.selected .gender-check { opacity: 1; }

        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 68px; }
        .pw-toggle {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            background: none; border: none; font-family: 'Jost', sans-serif;
            font-size: .68rem; font-weight: 600; letter-spacing: .08em;
            color: var(--rose); cursor: pointer; padding: 4px; text-transform: uppercase;
        }
        .pw-toggle:hover { color: var(--magenta); }

        .strength-track { display: flex; gap: 4px; margin-top: 6px; }
        .strength-track span { flex: 1; height: 3px; border-radius: 3px; background: var(--blush); transition: background .3s; }
        .strength-track span.s-weak   { background: var(--magenta); }
        .strength-track span.s-medium { background: #e8956d; }
        .strength-track span.s-strong { background: #6dba8a; }
        .strength-label { font-size: .72rem; color: var(--text-hint); margin-top: 4px; }

        .terms-row {
            display: flex; align-items: flex-start; gap: 10px;
            margin-bottom: 1.2rem; font-size: .82rem; color: var(--text-mid); font-weight: 300;
        }
        .terms-row input[type="checkbox"] { margin-top: 2px; accent-color: var(--rose); }
        .terms-row a { color: var(--rose); text-decoration: none; }
        .terms-row a:hover { text-decoration: underline; }

        .btn-primary {
            font-family: 'Jost', sans-serif; font-size: .8rem; font-weight: 600;
            letter-spacing: .16em; text-transform: uppercase; color: #fff;
            background: linear-gradient(135deg, var(--hot-pink), var(--magenta));
            border: none; border-radius: 50px; padding: 15px 32px; width: 100%;
            cursor: pointer; margin-bottom: 1.25rem;
            transition: transform .2s, box-shadow .2s, filter .2s;
            box-shadow: 0 8px 24px rgba(201,24,106,.35);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(201,24,106,.45); filter: brightness(1.06); }
        .btn-primary:active { transform: translateY(0); }

        .divider { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--blush); }
        .divider span { font-size: .8rem; color: var(--text-hint); }

        .admin-link { text-align: center; font-size: .84rem; color: var(--text-mid); margin-bottom: .75rem; }
        .admin-link a { color: var(--rose); font-weight: 500; text-decoration: none; }
        .admin-link a:hover { text-decoration: underline; }

        .form-footer { text-align: center; font-size: .72rem; color: var(--text-hint); line-height: 1.6; }
    </style>
<?php include __DIR__ . '/pwa_head.php'; ?>
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
        <div class="art-quote">"Begin your<br>bloom<br>journey."</div>
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

    <h1 class="form-title">Create <em>account</em></h1>
    <p class="form-subtitle">Join our community and start your floral journey.</p>

    <div class="tabs">
        <a href="login.php">Login</a>
        <span class="tab">Sign Up</span>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="msg-errors">
            <p>Please fix the following:</p>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <div class="fields-grid">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Choose a username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="username">
            </div>
            <div class="field">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" placeholder="Your full name"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required autocomplete="name">
            </div>
            <div class="field field-full">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="your@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
            </div>
            <div class="field">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" placeholder="09XXXXXXXXX"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required autocomplete="tel"
                       maxlength="11" inputmode="numeric" pattern="[0-9]{11}">
                <p class="field-hint" id="phone-hint" style="font-size:0.75rem;color:var(--text-hint);margin-top:4px;">11 digits only — no spaces, dashes, or letters.</p>
            </div>
            <div class="field">
                <label for="facebook">Facebook Account</label>
                <input type="text" id="facebook" name="facebook" placeholder="facebook.com/you"
                       value="<?= htmlspecialchars($_POST['facebook'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="field">
                <label>Gender</label>
                <!-- hidden native select submits with form -->
                <select id="gender" name="gender" required style="display:none">
                    <option value="" disabled <?= empty($_POST['gender']) ? 'selected' : '' ?>>Select gender</option>
                    <?php foreach (['Male','Female','Non-binary','Prefer not to say'] as $g): ?>
                        <option value="<?= $g ?>" <?= (($_POST['gender'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
                <!-- custom styled UI -->
                <div class="gender-wrap" id="genderWrap">
                    <div class="gender-trigger" id="genderTrigger" tabindex="0" role="button">
                        <?php
                            $icons = ['Male'=>'♂','Female'=>'♀','Non-binary'=>'⚧','Prefer not to say'=>'–'];
                            $selG  = $_POST['gender'] ?? '';
                        ?>
                        <span class="gender-icon"><?= $selG ? ($icons[$selG] ?? '♀') : '♀' ?></span>
                        <span class="gender-label <?= empty($selG) ? 'placeholder' : '' ?>" id="genderLabel">
                            <?= $selG ?: 'Select gender' ?>
                        </span>
                        <span class="gender-arrow">
                            <svg width="11" height="7" viewBox="0 0 11 7" fill="none"><path d="M1 1l4.5 4.5L10 1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </span>
                    </div>
                    <div class="gender-dropdown" id="genderDropdown">
                        <?php foreach (['Male'=>'♂','Female'=>'♀','Non-binary'=>'⚧','Prefer not to say'=>'–'] as $g => $icon): ?>
                            <div class="gender-option <?= ($selG === $g) ? 'selected' : '' ?>" data-value="<?= $g ?>">
                                <span class="gender-opt-icon"><?= $icon ?></span>
                                <span><?= $g ?></span>
                                <span class="gender-check">✓</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="field field-full">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password" placeholder="Minimum 8 characters"
                           required autocomplete="new-password" oninput="checkStrength(this.value)">
                    <button type="button" class="pw-toggle" onclick="togglePw('password',this)">Show</button>
                </div>
                <div class="strength-track"><span id="s1"></span><span id="s2"></span><span id="s3"></span><span id="s4"></span></div>
                <p class="strength-label" id="slabel"></p>
            </div>
            <div class="field field-full">
                <label for="confirm_password">Confirm Password</label>
                <div class="pw-wrap">
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Repeat your password" required autocomplete="new-password">
                    <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)">Show</button>
                </div>
            </div>
        </div>

        <div class="terms-row">
            <input type="checkbox" id="terms" name="terms" required>
            <label for="terms">I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a></label>
        </div>

        <button type="submit" class="btn-primary">Create Account</button>
    </form>

    <div class="divider"><span>or</span></div>
    <p class="admin-link">Staff or Admin? <a href="admin_login.php">Sign in here</a></p>
    <p class="form-footer">Your personal information is always kept private and secure.</p>
</div>
</div>

<script>
// ── Phone: digits only, max 11 ──
const phoneInput = document.getElementById('phone');
const phoneHint  = document.getElementById('phone-hint');
if (phoneInput) {
    phoneInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
        const len = this.value.length;
        if (len === 0) {
            phoneHint.textContent = '11 digits only — no spaces, dashes, or letters.';
            phoneHint.style.color = 'var(--text-hint)';
        } else if (len < 11) {
            phoneHint.textContent = `${len} / 11 digits entered.`;
            phoneHint.style.color = 'var(--deep-rose)';
        } else {
            phoneHint.textContent = '✓ Looks good!';
            phoneHint.style.color = '#276244';
        }
    });
}

function togglePw(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? 'Show' : 'Hide';
}
function checkStrength(v) {
    const bars  = ['s1','s2','s3','s4'].map(id => document.getElementById(id));
    const label = document.getElementById('slabel');
    bars.forEach(b => b.className = '');
    let score = 0;
    if (v.length >= 8)            score++;
    if (/[A-Z]/.test(v))          score++;
    if (/[0-9]/.test(v))          score++;
    if (/[^A-Za-z0-9]/.test(v))   score++;
    const cls = score <= 1 ? 's-weak' : score <= 2 ? 's-medium' : 's-strong';
    const lbl = ['','Weak','Fair','Good','Strong'];
    const clr = score <= 1 ? '#b84d6a' : score <= 2 ? '#e8956d' : '#6dba8a';
    for (let i = 0; i < score; i++) bars[i].className = cls;
    label.textContent = v.length ? (lbl[score] || 'Strong') : '';
    label.style.color = v.length ? clr : '';
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

// ── Gender dropdown ──
(function() {
    const wrap    = document.getElementById('genderWrap');
    const trigger = document.getElementById('genderTrigger');
    const native  = document.getElementById('gender');
    const label   = document.getElementById('genderLabel');
    const icons   = { Male:'♂', Female:'♀', 'Non-binary':'⚧', 'Prefer not to say':'–' };
    if (!wrap) return;

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        wrap.classList.toggle('open');
    });
    trigger.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); wrap.classList.toggle('open'); }
        if (e.key === 'Escape') wrap.classList.remove('open');
    });
    wrap.querySelectorAll('.gender-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            const val = opt.dataset.value;
            native.value = val;
            label.textContent = val;
            label.classList.remove('placeholder');
            wrap.querySelector('.gender-icon').textContent = icons[val] || '♀';
            wrap.querySelectorAll('.gender-option').forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            wrap.classList.remove('open');
        });
    });
    document.addEventListener('click', function() { wrap.classList.remove('open'); });
    wrap.addEventListener('click', function(e) { e.stopPropagation(); });
})();
</script>
</body>
</html>