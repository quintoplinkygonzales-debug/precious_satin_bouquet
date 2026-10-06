<?php
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (password_verify($pass, $row['password'])) {
            $_SESSION['user_id']              = $row['id'];
            $_SESSION['username']             = $row['username'];
            $_SESSION['role']                 = $row['role'];
            $_SESSION['admin_just_logged_in'] = true;
            header("Location: admin.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Admin account not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Precious Satin Bouquet</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --peach:        #ffc9a0;
            --peach-mid:    #ffbf96;
            --peach-deep:   #f0956a;
            --blush:        #ffe8d8;
            --blush-mid:    #ffb888;
            --petal:        #f09060;
            --soft-pink:    #fdd5e2;
            --cream:        #fff8f4;
            --ivory:        #fffcfa;
            --text-dark:    #4a2c1e;
            --text-mid:     #9a6b5a;
            --text-hint:    #d4a898;
            --accent:       #d05a28;
            --accent-soft:  #f08050;
        }

        html, body { height: 100%; overflow: hidden; }
        body {
            display: flex;
            font-family: 'Jost', sans-serif;
            background: var(--cream);
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
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.032'/%3E%3C/svg%3E");
        }

        .art-top {
            position: relative; z-index: 3; flex-shrink: 0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .art-wordmark {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem; font-weight: 600; color: var(--text-dark);
            letter-spacing: 0.04em; text-shadow: 0 2px 16px rgba(255,255,255,0.7);
        }
        .art-badge {
            background: rgba(255,255,255,0.55);
            border: 1px solid rgba(255,255,255,0.85);
            border-radius: 50px; padding: 6px 18px;
            font-size: 0.72rem; letter-spacing: 0.18em; text-transform: uppercase;
            color: var(--text-mid); font-weight: 600; backdrop-filter: blur(12px);
        }

        .art-deco { position: absolute; inset: 0; pointer-events: none; z-index: 2; }

        @keyframes bloomA { 0%,100%{transform:scale(1) rotate(0deg);} 50%{transform:scale(1.08) rotate(6deg);} }
        @keyframes bloomB { 0%,100%{transform:scale(1) rotate(0deg);} 50%{transform:scale(1.10) rotate(-8deg);} }
        @keyframes bloomC { 0%,100%{transform:scale(1) rotate(0deg);} 50%{transform:scale(1.06) rotate(5deg);} }

        .art-floaters { position: absolute; inset: 0; pointer-events: none; z-index: 2; overflow: hidden; }
        .art-floater { position: absolute; opacity: 0; animation: floatPetal linear infinite; }
        @keyframes floatPetal {
            0%   { opacity:0; transform:translateY(0) rotate(0deg) scale(var(--sc)); }
            8%   { opacity:.75; }
            92%  { opacity:.5; }
            100% { opacity:0; transform:translateY(-110vh) rotate(var(--spin)) scale(var(--sc)); }
        }

        .art-bottom { position: relative; z-index: 3; flex-shrink: 0; }
        .art-quote {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3.2rem; font-weight: 300; font-style: italic; color: var(--text-dark);
            line-height: 1.2; text-shadow: 0 2px 20px rgba(255,255,255,0.6);
            margin-bottom: 1.25rem;
        }
        .art-meta {
            font-size: 0.75rem; letter-spacing: 0.25em; text-transform: uppercase;
            color: var(--text-mid); font-weight: 400;
        }

        /* ══ FORM PANEL ══ */
        .panel-form {
            width: 460px; flex-shrink: 0;
            height: 100vh; overflow-y: auto;
            background: var(--ivory);
            position: relative; z-index: 10;
            display: flex; flex-direction: column;
            padding: 0 3rem;
        }
        .panel-form-inner {
            flex: 1; display: flex; flex-direction: column;
            justify-content: center;
            padding: 2.5rem 0 3rem;
            min-height: min-content;
        }
        @media (max-width: 820px) {
            .panel-form { width: 100%; height: auto; min-height: 100vh; padding: 0 1.75rem; }
            .panel-form-inner { padding: 2rem 0 3rem; }
        }

        /* Peach-to-blush-pink top stripe */
        .panel-form::before {
            content: '';
            position: sticky; top: 0; flex-shrink: 0;
            display: block; height: 4px; margin: 0 -3rem;
            background: linear-gradient(90deg, var(--peach), var(--petal), var(--blush-mid), var(--peach-mid));
            z-index: 20;
        }

        /* ── Brand ── */
        .brand { display: flex; align-items: center; gap: 14px; margin-bottom: 2rem; flex-shrink: 0; }
        .brand img {
            width: 54px; height: 54px; border-radius: 50%; object-fit: contain; background: #fff; padding: 4px; flex-shrink: 0;
            box-shadow: 0 4px 18px rgba(240,149,106,0.3);
        }
        .brand-dot {
            width: 54px; height: 54px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, var(--peach), var(--peach-mid));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; box-shadow: 0 4px 18px rgba(240,149,106,0.35);
        }
        .brand-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.3rem; font-weight: 600; color: var(--text-dark); letter-spacing: 0.04em;
        }

        /* ── Admin badge ── */
        .admin-badge {
            display: inline-flex; align-items: center; gap: 7px;
            background: linear-gradient(135deg, rgba(255,200,160,0.35), rgba(249,184,204,0.25));
            border: 1px solid rgba(220,120,70,0.4);
            border-radius: 20px; padding: 5px 14px 5px 10px;
            margin-bottom: 1.5rem; width: fit-content;
        }
        .admin-badge .dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--accent); flex-shrink: 0;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:0.5;transform:scale(0.8);} }
        .admin-badge span {
            font-size: 0.7rem; letter-spacing: 0.18em; text-transform: uppercase;
            color: var(--accent); font-weight: 600;
        }

        /* ── Headings ── */
        .form-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.8rem; font-weight: 300; color: var(--text-dark);
            line-height: 1.1; margin-bottom: 0.5rem;
        }
        .form-title em { font-style: italic; color: var(--accent); }
        .form-subtitle { font-size: 0.9rem; color: var(--text-mid); font-weight: 300; margin-bottom: 2rem; }

        .hline { height: 1px; background: linear-gradient(to right, transparent, var(--blush-mid), transparent); margin-bottom: 1.75rem; }

        /* ── Messages ── */
        .msg-error {
            display: flex; align-items: flex-start; gap: .65rem;
            background: #fff0e8; border: 1.5px solid var(--peach);
            border-radius: 10px; padding: .85rem 1rem;
            margin-bottom: 1.5rem; font-size: .88rem; color: var(--accent);
        }

        /* ── Fields ── */
        .field { margin-bottom: 1.4rem; }
        .field label {
            display: block; font-size: 0.7rem; letter-spacing: 0.2em;
            text-transform: uppercase; color: var(--text-mid); font-weight: 500; margin-bottom: 8px;
        }
        .field input {
            font-family: 'Jost', sans-serif; font-size: 0.93rem; font-weight: 300;
            color: var(--text-dark); width: 100%; background: var(--cream);
            border: 1.5px solid rgba(250,201,201,0.5); border-radius: 50px;
            padding: 14px 22px; outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .field input::placeholder { color: var(--text-hint); }
        .field input:focus {
            border-color: var(--peach-mid); background: var(--ivory);
            box-shadow: 0 0 0 3px rgba(255,191,150,.22);
        }

        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 72px; }
        .pw-toggle {
            position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
            background: none; border: none; font-family: 'Jost', sans-serif;
            font-size: 0.7rem; font-weight: 600; letter-spacing: 0.08em;
            color: var(--accent-soft); cursor: pointer; padding: 4px; text-transform: uppercase;
        }
        .pw-toggle:hover { color: var(--accent); }

        .row-forgot { text-align: right; margin: -.5rem 0 1.75rem; }
        .row-forgot a { font-size: .82rem; color: var(--accent-soft); text-decoration: none; }
        .row-forgot a:hover { text-decoration: underline; color: var(--accent); }

        /* ── Button — warm peach-to-coral gradient ── */
        .btn-primary {
            font-family: 'Jost', sans-serif; font-size: .82rem; font-weight: 600;
            letter-spacing: .18em; text-transform: uppercase; color: #fff;
            background: linear-gradient(135deg, var(--peach-mid), var(--accent));
            border: none; border-radius: 50px; padding: 17px 32px; width: 100%;
            cursor: pointer; margin-bottom: 1.75rem;
            transition: transform .2s, box-shadow .2s, filter .2s;
            box-shadow: 0 8px 24px rgba(240,149,106,.35);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(240,149,106,.50); filter: brightness(1.06); }
        .btn-primary:active { transform: translateY(0); }

        .return-link { text-align: center; font-size: .86rem; color: var(--text-mid); }
        .return-link a { color: var(--accent); font-weight: 500; text-decoration: none; }
        .return-link a:hover { text-decoration: underline; }

        .form-footer { text-align: center; font-size: .72rem; color: var(--text-hint); line-height: 1.6; margin-top: 1.25rem; }
    </style>
<?php include __DIR__ . '/pwa_head.php'; ?>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='32' fill='%23d96b8a'/%3E%3Ctext x='32' y='43' font-size='30' font-family='Georgia,serif' font-style='italic' text-anchor='middle' fill='white'%3ESB%3C/text%3E%3C/svg%3E">
</head>
<body>

<!-- ══ ART PANEL ══ -->
<div class="panel-art">
    <svg class="art-bg" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <radialGradient id="ag1" cx="60%" cy="22%" r="75%">
                <stop offset="0%"   stop-color="#ffc9a0"/>
                <stop offset="45%"  stop-color="#ffbf96"/>
                <stop offset="100%" stop-color="#f9c4cf"/>
            </radialGradient>
            <radialGradient id="ag2" cx="18%" cy="80%" r="65%">
                <stop offset="0%"   stop-color="#fde8e8"/>
                <stop offset="100%" stop-color="#ffc9a0" stop-opacity="0"/>
            </radialGradient>
            <radialGradient id="ag3" cx="88%" cy="60%" r="55%">
                <stop offset="0%"   stop-color="#fac9c9" stop-opacity="0.75"/>
                <stop offset="100%" stop-color="#ffc9a0" stop-opacity="0"/>
            </radialGradient>
            <radialGradient id="ag4" cx="50%" cy="50%" r="60%">
                <stop offset="0%"   stop-color="#fff3ed" stop-opacity="0.65"/>
                <stop offset="100%" stop-color="#ffc9a0" stop-opacity="0"/>
            </radialGradient>
            <filter id="ab1"><feGaussianBlur stdDeviation="30"/></filter>
            <filter id="ab2"><feGaussianBlur stdDeviation="50"/></filter>
        </defs>
        <rect width="800" height="900" fill="#ffe8d6"/>
        <ellipse cx="580" cy="170" rx="420" ry="390" fill="url(#ag1)" filter="url(#ab1)" opacity="0.92"/>
        <ellipse cx="150" cy="720" rx="380" ry="350" fill="url(#ag2)" filter="url(#ab2)" opacity="0.85"/>
        <ellipse cx="710" cy="600" rx="300" ry="280" fill="url(#ag3)" filter="url(#ab1)" opacity="0.7"/>
        <ellipse cx="400" cy="450" rx="350" ry="300" fill="url(#ag4)" filter="url(#ab1)" opacity="0.5"/>
        <ellipse cx="500" cy="290" rx="210" ry="115" fill="#fff5ee" opacity="0.35" filter="url(#ab1)" transform="rotate(-28 500 290)"/>
        <ellipse cx="200" cy="250" rx="150" ry="85"  fill="#fff3ed" opacity="0.30" filter="url(#ab1)" transform="rotate(18 200 250)"/>
        <ellipse cx="650" cy="760" rx="180" ry="95"  fill="#ffe4d0" opacity="0.40" filter="url(#ab1)" transform="rotate(-14 650 760)"/>
        <ellipse cx="560" cy="175" rx="78"  ry="38"  fill="white" opacity="0.30" transform="rotate(-24 560 175)"/>
        <ellipse cx="310" cy="425" rx="60"  ry="28"  fill="white" opacity="0.22" transform="rotate(14 310 425)"/>
        <ellipse cx="700" cy="530" rx="50"  ry="22"  fill="white" opacity="0.25" transform="rotate(-38 700 530)"/>
    </svg>
    <div class="art-grain"></div>

    <div class="art-deco">
        <!-- Large bloom top-right -->
        <svg style="position:absolute;top:80px;right:42px;" width="180" height="180" viewBox="-90 -90 180 180">
            <g style="animation:bloomA 5s ease-in-out infinite;transform-origin:0 0;">
                <ellipse rx="44" ry="17" fill="#ffc9a0" opacity="0.90" transform="rotate(0)"/>
                <ellipse rx="44" ry="17" fill="#fac9c9" opacity="0.85" transform="rotate(30)"/>
                <ellipse rx="44" ry="17" fill="#fde0c8" opacity="0.80" transform="rotate(60)"/>
                <ellipse rx="44" ry="17" fill="#fac9c9" opacity="0.80" transform="rotate(90)"/>
                <ellipse rx="44" ry="17" fill="#ffc9a0" opacity="0.75" transform="rotate(120)"/>
                <ellipse rx="44" ry="17" fill="#fac9c9" opacity="0.75" transform="rotate(150)"/>
                <circle r="26" fill="#fff5ee" opacity="0.95"/>
                <circle r="15" fill="#ffe4d0" opacity="0.98"/>
                <circle r="8"  fill="#f08050" opacity="0.88"/>
            </g>
        </svg>
        <!-- Mid bloom left -->
        <svg style="position:absolute;top:330px;left:30px;" width="132" height="132" viewBox="-66 -66 132 132">
            <g style="animation:bloomB 4.4s ease-in-out infinite;transform-origin:0 0;">
                <ellipse rx="33" ry="12" fill="#fac9c9" opacity="0.88" transform="rotate(0)"/>
                <ellipse rx="33" ry="12" fill="#ffc9a0" opacity="0.82" transform="rotate(45)"/>
                <ellipse rx="33" ry="12" fill="#fac9c9" opacity="0.78" transform="rotate(90)"/>
                <ellipse rx="33" ry="12" fill="#ffc9a0" opacity="0.78" transform="rotate(135)"/>
                <circle r="19" fill="#fff5ee" opacity="0.92"/>
                <circle r="11" fill="#fde0c8" opacity="0.95"/>
                <circle r="5.5" fill="#f08050" opacity="0.88"/>
            </g>
        </svg>
        <!-- Small bloom bottom-right -->
        <svg style="position:absolute;bottom:230px;right:72px;" width="92" height="92" viewBox="-46 -46 92 92">
            <g style="animation:bloomC 3.8s ease-in-out infinite;transform-origin:0 0;">
                <ellipse rx="23" ry="8.5" fill="#fde8e8" opacity="0.88" transform="rotate(0)"/>
                <ellipse rx="23" ry="8.5" fill="#ffc9a0" opacity="0.82" transform="rotate(36)"/>
                <ellipse rx="23" ry="8.5" fill="#fac9c9" opacity="0.78" transform="rotate(72)"/>
                <ellipse rx="23" ry="8.5" fill="#ffc9a0" opacity="0.76" transform="rotate(108)"/>
                <ellipse rx="23" ry="8.5" fill="#fde8e8" opacity="0.72" transform="rotate(144)"/>
                <circle r="15" fill="#fff5ee" opacity="0.92"/>
                <circle r="8"  fill="#f08050" opacity="0.88"/>
            </g>
        </svg>
        <!-- Tiny accent top-mid -->
        <svg style="position:absolute;top:200px;right:160px;" width="52" height="52" viewBox="-26 -26 52 52">
            <g style="animation:bloomA 6s 1s ease-in-out infinite;transform-origin:0 0;">
                <ellipse rx="13" ry="5" fill="#fde8e8" opacity="0.82" transform="rotate(0)"/>
                <ellipse rx="13" ry="5" fill="#ffc9a0" opacity="0.76" transform="rotate(72)"/>
                <ellipse rx="13" ry="5" fill="#fac9c9" opacity="0.70" transform="rotate(144)"/>
                <circle r="8" fill="#fff5ee" opacity="0.90"/>
                <circle r="4" fill="#f08050" opacity="0.88"/>
            </g>
        </svg>
        <!-- Small bloom lower-left -->
        <svg style="position:absolute;bottom:310px;left:142px;" width="56" height="56" viewBox="-28 -28 56 56">
            <g style="animation:bloomB 5s 0.5s ease-in-out infinite;transform-origin:0 0;">
                <ellipse rx="14" ry="5.5" fill="#ffc9a0" opacity="0.85" transform="rotate(0)"/>
                <ellipse rx="14" ry="5.5" fill="#fac9c9" opacity="0.78" transform="rotate(60)"/>
                <ellipse rx="14" ry="5.5" fill="#ffc9a0" opacity="0.72" transform="rotate(120)"/>
                <circle r="9"   fill="#fff5ee" opacity="0.90"/>
                <circle r="4.5" fill="#f08050" opacity="0.86"/>
            </g>
        </svg>
        <div style="position:absolute;top:142px;left:122px;color:rgba(240,149,106,.55);font-size:18px;animation:bloomA 2.5s ease-in-out infinite;">✦</div>
        <div style="position:absolute;top:448px;right:132px;color:rgba(250,180,170,.65);font-size:14px;animation:bloomB 3s .8s ease-in-out infinite;">✦</div>
        <div style="position:absolute;bottom:280px;left:90px;color:rgba(240,149,106,.42);font-size:12px;animation:bloomC 3.5s .3s ease-in-out infinite;">✦</div>
        <div style="position:absolute;top:580px;right:50px;color:rgba(250,180,170,.58);font-size:20px;animation:bloomA 4s 1.2s ease-in-out infinite;">✿</div>
        <div style="position:absolute;top:262px;left:202px;color:rgba(240,149,106,.48);font-size:16px;animation:bloomB 3.2s .6s ease-in-out infinite;">✿</div>
    </div>

    <div class="art-floaters" id="artFloaters"></div>

    <div class="art-top">
        <div class="art-wordmark">Precious Satin Bouquet</div>
        <div class="art-badge">Admin Portal</div>
    </div>
    <div class="art-bottom">
        <div class="art-quote">"Behind every<br>bloom, a<br>careful hand."</div>
        <div class="art-meta">Precious Satin Bouquet &nbsp;·&nbsp; Staff Access Only</div>
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

    <div class="admin-badge">
        <div class="dot"></div>
        <span>Admin Portal</span>
    </div>

    <h1 class="form-title">Admin <em>Login</em></h1>
    <p class="form-subtitle">Enter your credentials to access the dashboard.</p>

    <div class="hline"></div>

    <?php if (isset($error)): ?>
        <div class="msg-error"><span>⚠</span><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   placeholder="Enter your admin username"
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
        <div class="row-forgot"><a href="admin_forgot_password.php">Forgot password?</a></div>
        <button type="submit" class="btn-primary">Sign In</button>
    </form>

    <p class="return-link">Not an admin? <a href="login.php">Customer Login</a></p>
    <p class="form-footer">This portal is for authorized Precious Satin Bouquet staff only.</p>

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
    const petals = ['🌸','🌺','🌷','💐','✿','❀','🌼','🌸'];
    for (let i = 0; i < 14; i++) {
        const el = document.createElement('div');
        el.className = 'art-floater';
        el.textContent = petals[i % petals.length];
        const dur   = 8 + Math.random() * 10;
        const delay = -(Math.random() * dur);
        const sc    = 0.5 + Math.random() * 0.8;
        const spin  = (Math.random() * 360 - 180) + 'deg';
        el.style.cssText = `left:${Math.random()*95}%;top:${90+Math.random()*20}%;--sc:${sc};--spin:${spin};font-size:${1.1+Math.random()*1.5}rem;animation-duration:${dur}s;animation-delay:${delay}s;`;
        c.appendChild(el);
    }
})();
</script>
</body>
</html>