window.addEventListener("load", () => {
    const name = document.querySelector(".name");
    const intro = document.querySelector(".intro.loader");
  if (!intro) return;

    // Only show loader on actual reload (F5 / Ctrl+R), not navigation
    const navEntry = performance.getEntriesByType("navigation")[0];
    const isReload = navEntry && navEntry.type === "reload";

    if (!isReload) {
        // Not a reload — instantly hide the loader
        intro.style.display = "none";
        return;
    }

    // ── It's a real reload — show the petal loader ──
    const petalPairs = [
        ['#ffb6d0','#ff8fb5'],['#ffc8da','#f06fa0'],
        ['#ffd6e8','#e05090'],['#ff99bb','#c94f7c'],
        ['#ffe0ee','#d97a9a'],['#f4a6c0','#b8547e'],
        ['#fde8f0','#e8b4c4'],['#f9d0de','#d4688a'],
    ];

    const shapeFns = [
        (w,h,id) => `<ellipse cx="${w/2}" cy="${h/2}" rx="${w/2}" ry="${h/2}" fill="url(#${id})"/>`,
        (w,h,id) => `<path d="M${w/2} 0 Q${w} ${h*.28} ${w/2} ${h} Q0 ${h*.28} ${w/2} 0Z" fill="url(#${id})"/>`,
        (w,h,id) => `<path d="M${w/2} 0 C${w*1.1} ${h*.1} ${w*.9} ${h*.9} ${w/2} ${h} C${w*.1} ${h*.9} ${-w*.1} ${h*.1} ${w/2} 0Z" fill="url(#${id})"/>`,
        (w,h,id) => `<path d="M${w/2} 0 Q${w*.8} ${h*.4} ${w} ${h/2} Q${w*.8} ${h*.7} ${w/2} ${h} Q${w*.2} ${h*.7} 0 ${h/2} Q${w*.2} ${h*.4} ${w/2} 0Z" fill="url(#${id})"/>`,
    ];

    function makePetal(w, h, c1, c2) {
        const id = 'pg' + Math.random().toString(36).slice(2, 9);
        const shape = shapeFns[Math.floor(Math.random() * shapeFns.length)](w, h, id);
        return `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">
            <defs><radialGradient id="${id}" cx="38%" cy="32%" r="68%">
                <stop offset="0%" stop-color="${c1}" stop-opacity="0.97"/>
                <stop offset="100%" stop-color="${c2}" stop-opacity="0.72"/>
            </radialGradient></defs>${shape}</svg>`;
    }

    for (let i = 0; i < 42; i++) {
        const pw = document.createElement('div');
        pw.className = 'hero-petal-wrap';
        const sd = 2.0 + Math.random() * 3.8;
        pw.style.cssText = `left:${Math.random()*100}%;animation-duration:${sd}s;animation-delay:${-(Math.random()*sd)}s;`;

        const p = document.createElement('div');
        p.className = 'hero-petal';
        const w = 10 + Math.random() * 20;
        const h = w * (1.15 + Math.random() * 0.7);
        const fd = 3.5 + Math.random() * 5;
        const pair = petalPairs[Math.floor(Math.random() * petalPairs.length)];
        p.style.cssText = `width:${w}px;height:${h}px;animation-duration:${fd}s;animation-delay:${-(Math.random()*8)}s;display:block;opacity:${0.7+Math.random()*0.3};`;
        p.innerHTML = makePetal(Math.round(w), Math.round(h), pair[0], pair[1]);

        pw.appendChild(p);
        intro.appendChild(pw);
    }

    setTimeout(() => {
        name.style.opacity = "1";
        name.style.transform = "translateY(0)";
    }, 500);

    setTimeout(() => {
        intro.style.transition = "top 1s ease-in-out";
        intro.style.top = "-100%";
    }, 2500);

    setTimeout(() => {
        intro.style.display = "none";
    }, 3600);
});