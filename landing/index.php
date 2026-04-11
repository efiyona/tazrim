<?php
/**
 * דף נחיתה — מבנה ועיצוב לפי מקור riseup.co.il (Elementor), תוכן התזרים.
 */
require __DIR__ . '/../path.php';

$base = rtrim(BASE_URL, '/');
$reg = htmlspecialchars($base . '/pages/register.php', ENT_QUOTES, 'UTF-8');
$login = htmlspecialchars($base . '/pages/login.php', ENT_QUOTES, 'UTF-8');
$forgot = htmlspecialchars($base . '/pages/forgot_password.php', ENT_QUOTES, 'UTF-8');
$self = htmlspecialchars($base . '/landing/', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="he-IL">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="index, follow">
    <title>התזרים | ניהול תקציב משפחתי חכם</title>
    <meta name="description" content="התזרים נותנת כלים פשוטים לניהול תקציב המשפחה בעברית — דאשבורד, בית משותף ותנועות קבועות.">
    <meta name="theme-color" content="#205441">
    <meta property="og:locale" content="he_IL">
    <meta property="og:type" content="website">
    <meta property="og:title" content="התזרים | ניהול תקציב משפחתי חכם">
    <meta property="og:description" content="שליטה בהוצאות ובהכנסות — שקיפות בין בני הזוג, בלי אקסל ובלי הפתעות.">
    <link rel="canonical" href="<?php echo $self; ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Assistant:wght@400;500;600;700;800&family=Rubik:ital,wght@0,400;0,500;0,600;0,700;1,500&display=swap" rel="stylesheet">

    <style>
        :root {
            --ru-green: #205441;
            --ru-green-soft: #2d6b52;
            --ru-cream: #f9f7ed;
            --ru-blue: #5d7afd;
            --ru-banner: #dce8f6;
            --ru-muted: #4a5f57;
            --ru-line: #d4e5dc;
            --ru-card: #ffffff;
            --ru-page: #f4f7f5;
            --maxw: 1140px;
            --header-h: 78px;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: calc(var(--header-h) + 12px); }
        body {
            margin: 0;
            font-family: Assistant, Rubik, system-ui, sans-serif;
            color: var(--ru-green);
            background: var(--ru-page);
            line-height: 1.6;
            font-size: 1.05rem;
        }

        a { color: var(--ru-blue); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .screen-reader-text {
            position: absolute !important;
            clip: rect(1px, 1px, 1px, 1px);
            width: 1px; height: 1px;
            overflow: hidden;
        }
        .screen-reader-text:focus {
            clip: auto !important;
            width: auto; height: auto;
            padding: 12px 16px;
            background: #000;
            color: #fff;
            z-index: 100000;
            top: 8px; right: 8px;
            border-radius: 8px;
        }

        /* Cookie banner — כמו riseup */
        #consent-banner {
            position: fixed;
            bottom: 0;
            right: 0;
            width: 100%;
            background: var(--ru-banner);
            padding: 12px 0;
            z-index: 999999;
            display: none;
            font-size: 17px;
        }
        #consent-banner .inner {
            color: var(--ru-green);
            max-width: 1400px;
            margin: auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            gap: 8px;
            flex-wrap: wrap;
        }
        #consent-banner .close-btn {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            line-height: 1;
            color: var(--ru-green);
        }
        #consent-banner .consent-link { font-weight: 700; color: var(--ru-blue) !important; }
        @media (max-width: 768px) {
            #consent-banner .inner { font-size: 14px; }
            #consent-banner .close-btn { left: 0; top: 7px; transform: none; }
        }

        /* Header */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 500;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--ru-line);
        }
        .header-inner {
            max-width: var(--maxw);
            margin: 0 auto;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: var(--header-h);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--ru-green);
        }
        .brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--ru-green), var(--ru-green-soft));
            display: grid;
            place-items: center;
        }
        .nav-main {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex: 1;
            justify-content: center;
        }
        .nav-main a {
            color: var(--ru-green);
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
        }
        .nav-main a:hover { color: var(--ru-blue); text-decoration: none; }
        .header-cta {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-outline {
            padding: 10px 18px;
            border-radius: 8px;
            border: 2px solid var(--ru-green);
            color: var(--ru-green);
            font-weight: 700;
            font-size: 0.9rem;
            background: transparent;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-outline:hover { background: rgba(32, 84, 65, 0.06); text-decoration: none; }

        .menu-toggle {
            display: none;
            width: 46px;
            height: 46px;
            border: 2px solid var(--ru-line);
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }
        .menu-toggle span {
            display: block;
            width: 22px;
            height: 2px;
            background: var(--ru-green);
            position: relative;
        }
        .menu-toggle span::before, .menu-toggle span::after {
            content: '';
            position: absolute;
            inset-inline: 0;
            height: 2px;
            background: var(--ru-green);
        }
        .menu-toggle span::before { top: -7px; }
        .menu-toggle span::after { top: 7px; }

        .nav-drawer {
            display: none;
            flex-direction: column;
            padding: 12px 20px 20px;
            border-top: 1px solid var(--ru-line);
            background: #fff;
        }
        .nav-drawer.open { display: flex; }
        .nav-drawer a {
            padding: 12px 0;
            color: var(--ru-green);
            font-weight: 600;
            border-bottom: 1px solid var(--ru-line);
        }
        .nav-drawer .header-cta { flex-direction: column; margin-top: 12px; }
        .nav-drawer .btn-cta { width: 100%; justify-content: center; }

        @media (max-width: 960px) {
            .nav-main, .header-cta.hd { display: none; }
            .menu-toggle { display: inline-flex; }
        }
        @media (min-width: 961px) {
            .nav-drawer { display: none !important; }
        }

        /* CTA כפתור ראשי — כמו btn_cta (רקע ירוק, חץ קרם) */
        .btn-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 22px;
            background: var(--ru-green);
            color: var(--ru-cream) !important;
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none !important;
            transition: background 0.2s, transform 0.15s;
        }
        .btn-cta:hover { background: var(--ru-green-soft); transform: translateY(-1px); text-decoration: none !important; }
        .btn-cta svg { flex-shrink: 0; }

        /* Hero #intro */
        #intro {
            position: relative;
            min-height: min(88vh, 820px);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .hero-video-bg {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(105deg, rgba(32, 84, 65, 0.92) 0%, rgba(45, 107, 82, 0.75) 45%, rgba(93, 122, 253, 0.25) 100%),
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            animation: heroShift 18s ease-in-out infinite alternate;
        }
        @keyframes heroShift {
            from { filter: brightness(1); transform: scale(1); }
            to { filter: brightness(1.05); transform: scale(1.03); }
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.35), transparent 50%);
            pointer-events: none;
        }
        .hero-inner {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 40px 20px;
            max-width: 900px;
            margin: 0 auto;
        }
        #intro h1 {
            margin: 0 0 1.5rem;
            font-size: clamp(2.2rem, 6vw, 3.6rem);
            font-weight: 800;
            line-height: 1.15;
            color: #fff;
            text-shadow: 0 2px 24px rgba(0,0,0,0.2);
        }
        #intro h1 em {
            font-style: italic;
            font-family: Rubik, Assistant, sans-serif;
            color: #c8f5dc;
        }

        .promo-row {
            max-width: var(--maxw);
            margin: -40px auto 0;
            position: relative;
            z-index: 3;
            padding: 0 20px 48px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 768px) {
            .promo-row { grid-template-columns: 1fr; margin-top: 0; }
        }
        .promo-card {
            background: var(--ru-card);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(32, 84, 65, 0.12);
            border: 1px solid var(--ru-line);
        }
        .promo-card a { display: block; color: inherit; text-decoration: none; }
        .promo-ph {
            height: 140px;
            background: linear-gradient(135deg, #e8f4ec, #dce8f6);
        }
        .promo-card figcaption { padding: 14px 16px; font-weight: 700; font-size: 0.95rem; }

        .section-pad { padding: clamp(3rem, 8vw, 5rem) 20px; }
        .wrap { max-width: var(--maxw); margin: 0 auto; }

        #Whatis .lead-big {
            text-align: center;
            font-size: clamp(1.25rem, 2.5vw, 1.6rem);
            font-weight: 800;
            margin: 0 0 1rem;
        }
        #Whatis .lead-sub {
            text-align: center;
            color: var(--ru-muted);
            max-width: 520px;
            margin: 0 auto;
            font-size: 1.1rem;
        }

        /* שלוש כרטיסיות */
        .cards-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-top: 2.5rem;
        }
        @media (max-width: 900px) { .cards-3 { grid-template-columns: 1fr; } }
        .card-ru {
            border-radius: 20px;
            padding: 2rem 1.5rem;
            text-align: center;
            min-height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-ru:nth-child(1) { background: #e8f4ec; }
        .card-ru:nth-child(2) { background: #eef2ff; }
        .card-ru:nth-child(3) { background: #fdf6e3; }
        .card-ru p { margin: 0; font-size: 1.05rem; line-height: 1.55; }
        .card-ru strong { color: var(--ru-green); }

        /* לקוחות מספרים + קרוסלה פשוטה */
        #howitworks {
            position: relative;
            background: linear-gradient(180deg, #1a4536 0%, var(--ru-green) 100%);
            color: #fff;
            padding: clamp(3rem, 7vw, 5rem) 20px;
        }
        #howitworks h3 {
            text-align: center;
            font-size: clamp(1.4rem, 3vw, 1.85rem);
            margin: 0 0 2rem;
            color: #c8f5dc;
        }
        .carousel-simple {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            padding-bottom: 8px;
            max-width: 900px;
            margin: 0 auto;
        }
        .carousel-simple::-webkit-scrollbar { height: 6px; }
        .carousel-simple::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 4px; }
        .car-slide {
            flex: 0 0 220px;
            height: 140px;
            border-radius: 16px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            scroll-snap-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            text-align: center;
            padding: 12px;
        }

        /* פלטפורמות */
        .plat-box {
            background: var(--ru-cream);
            border-radius: 24px;
            padding: clamp(2.5rem, 5vw, 3.5rem);
            text-align: center;
            border: 1px solid rgba(32, 84, 65, 0.12);
            box-shadow: 0 12px 36px rgba(32, 84, 65, 0.08);
        }
        .plat-box h3 { margin: 0 0 0.75rem; font-size: 1.5rem; }
        .plat-box p { margin: 0 0 1.5rem; color: var(--ru-muted); }
        .plat-icons { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
        .plat-pill {
            padding: 12px 20px;
            background: #fff;
            border-radius: 999px;
            border: 1px solid var(--ru-line);
            font-weight: 600;
            color: var(--ru-muted);
            font-size: 0.9rem;
        }

        /* מחירים — שני עמודות */
        .pricing-head { text-align: center; margin-bottom: 2rem; }
        .pricing-head h4 { margin: 0 0 0.5rem; font-size: 1rem; color: var(--ru-muted); }
        .pricing-head h2 { margin: 0; font-size: clamp(1.5rem, 3vw, 2rem); }
        .price-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            max-width: 880px;
            margin: 0 auto;
        }
        @media (max-width: 700px) { .price-grid { grid-template-columns: 1fr; } }
        .price-col {
            background: var(--ru-card);
            border-radius: 20px;
            padding: 2rem 1.5rem;
            border: 2px solid var(--ru-line);
        }
        .price-col.featured {
            border-color: var(--ru-blue);
            box-shadow: 0 12px 32px rgba(93, 122, 253, 0.15);
        }
        .price-badge {
            display: inline-block;
            background: var(--ru-blue);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 6px 12px;
            border-radius: 999px;
            margin-bottom: 8px;
        }
        .price-col h3 { margin: 0 0 0.5rem; font-size: 1.2rem; }
        .price-tag { font-size: 2rem; font-weight: 800; color: var(--ru-green); margin: 0.5rem 0; }
        .price-note { color: var(--ru-muted); font-size: 0.95rem; margin-bottom: 1.25rem; line-height: 1.5; }
        .price-col ul { list-style: none; padding: 0; margin: 0 0 1.25rem; text-align: right; }
        .price-col li {
            padding: 8px 0;
            border-bottom: 1px solid var(--ru-line);
            display: flex;
            align-items: flex-start;
            gap: 8px;
            color: var(--ru-muted);
            font-size: 0.95rem;
        }
        .price-col li::before { content: '✓'; color: var(--ru-green); font-weight: 800; }

        .manifesto-box {
            background: #fff;
            border-radius: 20px;
            padding: clamp(2rem, 5vw, 3rem);
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 8px 32px rgba(32, 84, 65, 0.08);
            border: 1px solid var(--ru-line);
        }
        .manifesto-box h4 { margin: 0 0 1rem; font-size: 1.25rem; }
        .manifesto-box p { margin: 0; color: var(--ru-muted); line-height: 1.75; }

        /* סטטיסטיקות — ללא מספרים מפוברקים */
        .stats-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: center;
            max-width: var(--maxw);
            margin: 0 auto;
        }
        @media (max-width: 768px) { .stats-split { grid-template-columns: 1fr; } }
        .stat-block h4 { font-size: 1.1rem; margin: 0 0 0.5rem; color: var(--ru-green); }
        .stat-big { font-size: 2rem; font-weight: 800; color: var(--ru-green); margin: 0 0 0.35rem; }
        .stat-block p { margin: 0; color: var(--ru-muted); }
        .stat-visual {
            height: 220px;
            border-radius: 20px;
            background: linear-gradient(160deg, #e8f4ec, #dce8f6);
        }

        /* מרקיזה */
        #betogether {
            overflow: hidden;
            padding: 18px 0;
            background: #fff;
            border-block: 1px solid var(--ru-line);
        }
        .ticker-track {
            display: flex;
            gap: 3rem;
            width: max-content;
            animation: ticker-rtl 22s linear infinite;
        }
        @keyframes ticker-rtl {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .ticker-track span {
            font-weight: 800;
            color: var(--ru-green);
            white-space: nowrap;
            font-size: 1rem;
        }

        .community-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: center;
            max-width: var(--maxw);
            margin: 0 auto;
        }
        @media (max-width: 900px) { .community-grid { grid-template-columns: 1fr; } }
        .community-ph {
            width: 100%;
            max-width: 280px;
            height: 320px;
            margin: 0 auto;
            border-radius: 16px;
            background: linear-gradient(180deg, #c8f5dc, var(--ru-green));
            opacity: 0.85;
        }
        .community-text h3 { font-size: 1.5rem; line-height: 1.35; margin: 0 0 1rem; }
        .community-text p { color: var(--ru-muted); margin: 0 0 1.25rem; }

        .quote-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-top: 2rem;
        }
        @media (max-width: 768px) { .quote-row { grid-template-columns: 1fr; } }
        .quote-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--ru-line);
            box-shadow: 0 6px 20px rgba(32, 84, 65, 0.06);
        }
        .quote-card p { margin: 0 0 0.75rem; font-weight: 500; }
        .quote-card small { color: var(--ru-muted); }

        #secur .partner-head {
            text-align: center;
            max-width: 640px;
            margin: 0 auto 1.5rem;
        }
        #secur .partner-head h5 { margin: 0 0 0.75rem; font-size: 1.15rem; }
        #secur .partner-head p { margin: 0; color: var(--ru-muted); }

        /* לוגואים — פס טקסט בלבד (ללא נכסי riseup) */
        .logo-strip {
            display: flex;
            gap: 2rem;
            justify-content: center;
            flex-wrap: wrap;
            padding: 2rem 0;
            color: var(--ru-muted);
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* אבטחה #secur3 */
        #secur3 {
            background: #eef5f1;
            padding: clamp(3rem, 7vw, 5rem) 20px;
        }
        .sec-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem;
            align-items: center;
            max-width: var(--maxw);
            margin: 0 auto;
        }
        @media (max-width: 900px) { .sec-split { grid-template-columns: 1fr; } }
        #secur3 h3 { font-size: 1.65rem; margin: 0 0 1.25rem; line-height: 1.3; }
        #secur3 ul { margin: 0; padding-inline-start: 0; list-style: none; }
        #secur3 li {
            padding: 10px 0;
            border-bottom: 1px solid var(--ru-line);
            color: var(--ru-muted);
        }
        #secur3 li strong { color: var(--ru-green); }
        .sec-illus {
            max-width: 320px;
            margin: 0 auto;
            aspect-ratio: 1;
            border-radius: 20px;
            background: linear-gradient(145deg, var(--ru-green), #5d7afd);
            opacity: 0.9;
        }

        /* ניוזלטר */
        .newsletter {
            position: relative;
            padding: clamp(3rem, 7vw, 4.5rem) 20px;
            background: linear-gradient(135deg, #1a4536 0%, var(--ru-green-soft) 50%, #3d4f8f 100%);
            color: #fff;
        }
        .news-inner {
            max-width: 520px;
            margin: 0 auto;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(8px);
            padding: 2rem;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .news-inner p { margin: 0 0 1rem; font-weight: 500; }
        .news-inner input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.35);
            margin-bottom: 10px;
            font-family: inherit;
            background: rgba(255,255,255,0.95);
        }
        .news-inner .hint { font-size: 0.85rem; opacity: 0.85; margin-top: 8px; }

        /* Sticky טקסט אנכי */
        .sticky_text {
            writing-mode: vertical-rl;
            position: fixed;
            width: auto;
            top: 0;
            right: 36px;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ru-green);
            font-size: 17px;
            font-weight: 700;
            transform: rotate(180deg);
            z-index: 400;
            pointer-events: none;
        }
        @media (max-width: 1024px) { .sticky_text { display: none; } }

        .cta-bottom {
            background: var(--ru-cream);
            border-radius: 24px;
            padding: clamp(2.5rem, 5vw, 3.5rem);
            text-align: center;
            max-width: var(--maxw);
            margin: 0 auto;
            border: 1px solid rgba(32, 84, 65, 0.1);
        }
        .cta-bottom h3 { margin: 0 0 0.75rem; font-size: clamp(1.35rem, 3vw, 1.75rem); }
        .cta-bottom p { margin: 0 0 1.5rem; color: var(--ru-muted); }

        /* Footer */
        .site-footer {
            background: var(--ru-green);
            color: rgba(255, 255, 255, 0.88);
            padding: 3rem 20px 2rem;
        }
        .footer-grid {
            max-width: var(--maxw);
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        @media (max-width: 640px) { .footer-grid { grid-template-columns: 1fr; } }
        .site-footer strong { color: #fff; display: block; margin-bottom: 0.75rem; }
        .site-footer a { color: rgba(255,255,255,0.88); }
        .site-footer a:hover { color: #fff; }
        .footer-copy {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.15);
            font-size: 0.9rem;
        }
        .logo-footer {
            text-align: center;
            padding: 2rem 20px 1rem;
            background: #1a3d32;
        }
        .logo-footer .mark { font-weight: 800; font-size: 1.5rem; color: #c8f5dc; }

        .center-cta { text-align: center; padding: 2rem 20px 3rem; }

        /* FAQ */
        .faq-item {
            border: 1px solid var(--ru-line);
            border-radius: 12px;
            margin-bottom: 10px;
            background: #fff;
        }
        .faq-btn {
            width: 100%;
            text-align: right;
            padding: 1rem 1.15rem;
            font: inherit;
            font-weight: 700;
            background: transparent;
            border: none;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            color: var(--ru-green);
        }
        .faq-body { display: none; padding: 0 1.15rem 1rem; color: var(--ru-muted); font-size: 0.95rem; }
        .faq-item.open .faq-body { display: block; }
        .faq-plus { font-weight: 800; color: var(--ru-blue); }
    </style>
</head>
<body>

<div id="consent-banner">
    <div class="inner">
        <button type="button" class="close-btn" id="consent-close" aria-label="סגירה">×</button>
        לידיעתך, אנחנו משתמשים בעוגיות באתר זה
        <a href="#" class="consent-link">לפרטים נוספים</a>
    </div>
</div>

<a class="screen-reader-text skip-link" href="#content">דלג לתוכן</a>

<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="<?php echo $self; ?>">
            <span class="brand-mark" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 48 48" fill="none"><path fill="#fff" d="M14 17h20a2 2 0 012 2v2H12v-2a2 2 0 012-2zm0 6h22v10a2 2 0 01-2 2H14a2 2 0 01-2-2V23zm14 2a1.5 1.5 0 100 3h4a1.5 1.5 0 100-3h-4z"/></svg>
            </span>
            תזרים
        </a>
        <nav class="nav-main" aria-label="תפריט ראשי">
            <a href="<?php echo $self; ?>">בית</a>
            <a href="#howitworks">ככה זה עובד</a>
            <a href="#pricing">להתחיל</a>
            <a href="#secur3">שומרים על המידע שלך</a>
            <a href="#faq">שאלות ותשובות</a>
        </nav>
        <div class="header-cta hd">
            <a class="btn-outline" href="<?php echo $login; ?>">כניסה למערכת</a>
            <a class="btn-cta" href="<?php echo $reg; ?>">להצטרף עכשיו</a>
        </div>
        <button type="button" class="menu-toggle" id="menuBtn" aria-expanded="false" aria-controls="drawer" aria-label="תפריט">
            <span></span>
        </button>
    </div>
    <nav class="nav-drawer" id="drawer" aria-label="תפריט מובייל">
        <a href="<?php echo $self; ?>">בית</a>
        <a href="#howitworks">ככה זה עובד</a>
        <a href="#pricing">להתחיל</a>
        <a href="#secur3">שומרים על המידע שלך</a>
        <a href="#faq">שאלות ותשובות</a>
        <div class="header-cta">
            <a class="btn-outline" href="<?php echo $login; ?>">כניסה למערכת</a>
            <a class="btn-cta" href="<?php echo $reg; ?>">להצטרף עכשיו</a>
        </div>
    </nav>
</header>

<main id="content">

    <section id="intro" aria-labelledby="hero-h1">
        <div class="hero-video-bg" aria-hidden="true"></div>
        <div class="hero-overlay" aria-hidden="true"></div>
        <div class="hero-inner">
            <h1 id="hero-h1">להיות <em>בשקט</em><br>עם התקציב של הבית</h1>
            <a class="btn-cta" href="<?php echo $reg; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="23" height="17" viewBox="0 0 23 17" fill="none" aria-hidden="true">
                    <path d="M8.59121 2L2.00079 8.59042L8.59122 15.1808" stroke="#F9F7ED" stroke-width="1.46447" stroke-linecap="square"/>
                    <path d="M22.039 8.58984L3.00001 8.58985" stroke="#F9F7ED" stroke-width="1.46447" stroke-linecap="square"/>
                </svg>
                התחילו בחינם
            </a>
        </div>
    </section>

    <div class="promo-row">
        <figure class="promo-card">
            <a href="#how">
                <div class="promo-ph" aria-hidden="true"></div>
                <figcaption>דאשבורד חכם — הכנסות מול הוצאות במבט אחד</figcaption>
            </a>
        </figure>
        <figure class="promo-card">
            <a href="#pricing">
                <div class="promo-ph" aria-hidden="true"></div>
                <figcaption>בית משותף — כולם רואים את אותה תמונת מצב</figcaption>
            </a>
        </figure>
    </div>

    <section id="Whatis" class="section-pad">
        <div class="wrap">
            <p class="lead-big"><strong>לחסוך זמן. לייצר שקיפות.</strong></p>
            <p class="lead-sub">עם ממשק נקי בעברית ותנועות קבועות, אפשר לנהל את תקציב המשפחה בלי אקסל ובלי הפתעות בסוף החודש.</p>
            <div class="cards-3">
                <div class="card-ru"><p>התזרים אוספת הוצאות והכנסות <strong>במקום אחד</strong> — ועושה סדר בתקציב.</p></div>
                <div class="card-ru"><p>רואים קטגוריות ומגמות, <strong>ומפשטים החלטות</strong> על מה לעקוב החודש.</p></div>
                <div class="card-ru"><p>בני הזוג על אותו עמוד — <strong>שקיפות</strong> שמורידה לחץ מהשיח על כסף.</p></div>
            </div>
        </div>
    </section>

    <section id="howitworks" aria-labelledby="clients-h">
        <h3 id="clients-h">לקוחות מספרים</h3>
        <div class="carousel-simple" role="list">
            <div class="car-slide" role="listitem">שקיפות מלאה בין בני הזוג</div>
            <div class="car-slide" role="listitem">תנועות קבועות — פחות הקלדה כל חודש</div>
            <div class="car-slide" role="listitem">ממשק בעברית ו־RTL מלא</div>
            <div class="car-slide" role="listitem">חוויית שימוש חלקה בדפדפן</div>
        </div>
        <p style="text-align:center;margin:1.5rem 0 0;font-size:0.9rem;opacity:0.85;">המסרים לדוגמה — לא סקר מאומת</p>
    </section>

    <section class="section-pad">
        <div class="wrap plat-box">
            <h3>התזרים זמינה בכל הפלטפורמות</h3>
            <p>בדפדפן במחשב או בטלפון — וניתן להתקין כאפליקציה מהדפדפן (PWA)</p>
            <div class="plat-icons">
                <span class="plat-pill">דפדפן — דסקטופ</span>
                <span class="plat-pill">דפדפן — מובייל</span>
                <span class="plat-pill">התקנה מ־PWA</span>
            </div>
            <div style="margin-top:1.75rem;">
                <a class="btn-cta" href="<?php echo $reg; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="19" viewBox="0 0 26 19" fill="none" aria-hidden="true">
                        <path d="M9.56763 1.96289L2.00086 9.52967L9.56764 17.0964" stroke="#F9F7ED" stroke-width="1.68142" stroke-linecap="square"/>
                        <path d="M25.0077 9.52905L3.14811 9.52906" stroke="#F9F7ED" stroke-width="1.68142" stroke-linecap="square"/>
                    </svg>
                    התחילו בחינם
                </a>
            </div>
        </div>
    </section>

    <section class="center-cta">
        <a class="btn-cta" href="<?php echo $reg; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="19" viewBox="0 0 26 19" fill="none" aria-hidden="true">
                <path d="M9.56763 1.96289L2.00086 9.52967L9.56764 17.0964" stroke="#F9F7ED" stroke-width="1.68142" stroke-linecap="square"/>
                <path d="M25.0077 9.52905L3.14811 9.52906" stroke="#F9F7ED" stroke-width="1.68142" stroke-linecap="square"/>
            </svg>
            התחילו בחינם
        </a>
    </section>

    <section id="pricing" class="section-pad">
        <div class="wrap">
            <div class="pricing-head">
                <h4>בחירת מסלול</h4>
                <h2>איך נכנסים להתזרים?</h2>
            </div>
            <div class="price-grid">
                <div class="price-col featured">
                    <span class="price-badge">מומלץ</span>
                    <h3>משתמש חדש</h3>
                    <p class="price-tag">חינם</p>
                    <p class="price-note">הרשמה מהירה, בית משותף וקטגוריות — ומתחילים לתעד תנועות.</p>
                    <ul>
                        <li>דאשבורד חודשי</li>
                        <li>בית משותף ותפקידים</li>
                        <li>תנועות קבועות</li>
                    </ul>
                    <a class="btn-cta" style="width:100%;" href="<?php echo $reg; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="19" viewBox="0 0 26 19" fill="none" aria-hidden="true"><path d="M9.56763 1.96289L2.00086 9.52967L9.56764 17.0964" stroke="#F9F7ED" stroke-width="1.68142" stroke-linecap="square"/><path d="M25.0077 9.52905L3.14811 9.52906" stroke="#F9F7ED" stroke-width="1.68142" stroke-linecap="square"/></svg>
                        להרשמה
                    </a>
                </div>
                <div class="price-col">
                    <h3>כבר רשומים</h3>
                    <p class="price-tag">התחברות</p>
                    <p class="price-note">ממשיכים מאיפה שהפסקתם — הנתונים שמורים בחשבון.</p>
                    <ul>
                        <li>כניסה מאובטחת</li>
                        <li>«זכור אותי» לפי בחירה</li>
                        <li>איפוס סיסמה במייל</li>
                    </ul>
                    <a class="btn-outline" style="width:100%;justify-content:center;" href="<?php echo $login; ?>">כניסה למערכת</a>
                </div>
            </div>
        </div>
    </section>

    <section id="how" class="section-pad" style="background:#fff;">
        <div class="wrap">
            <div class="manifesto-box">
                <h4>להבין את הכסף של הבית — בלי להסתבך</h4>
                <p><strong>התזרים כאן כדי לעזור עם תקציב משפחתי.</strong> במקום טבלאות מפוזרות — מקום אחד שבו כולם רואים את אותו מידע. במקום הזנה חוזרת של אותן הוצאות — תנועות קבועות. המטרה: פחות רעש, יותר בהירות.</p>
            </div>
            <div style="text-align:center;margin-top:2rem;">
                <a class="btn-cta" href="<?php echo $reg; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="23" height="17" viewBox="0 0 23 17" fill="none" aria-hidden="true"><path d="M8.59121 2L2.00079 8.59042L8.59122 15.1808" stroke="#F9F7ED" stroke-width="1.46447" stroke-linecap="square"/><path d="M22.039 8.58984L3.00001 8.58985" stroke="#F9F7ED" stroke-width="1.46447" stroke-linecap="square"/></svg>
                    התחילו בחינם
                </a>
            </div>
        </div>
    </section>

    <section class="section-pad">
        <div class="stats-split">
            <div>
                <div class="stat-block" style="margin-bottom:2rem;">
                    <h4>פחות זמן על נתונים</h4>
                    <p class="stat-big">אוטומציה</p>
                    <p>תנועות קבועות וממשק זורם — פחות הקלדה חוזרת.</p>
                </div>
                <div class="stat-block">
                    <h4>שקיפות בבית</h4>
                    <p class="stat-big">יחד</p>
                    <p>אותה תמונת מצב לכל מי שבבית — פחות אי־ודאות.</p>
                </div>
            </div>
            <div class="stat-visual" aria-hidden="true"></div>
        </div>
    </section>

    <section class="center-cta">
        <a class="btn-cta" href="<?php echo $reg; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="23" height="17" viewBox="0 0 23 17" fill="none" aria-hidden="true"><path d="M8.59121 2L2.00079 8.59042L8.59122 15.1808" stroke="#F9F7ED" stroke-width="1.46447" stroke-linecap="square"/><path d="M22.039 8.58984L3.00001 8.58985" stroke="#F9F7ED" stroke-width="1.46447" stroke-linecap="square"/></svg>
            התחילו בחינם
        </a>
    </section>

    <section id="betogether" aria-hidden="true">
        <div class="ticker-track">
            <span>ביחד על התקציב · ביחד על התקציב · ביחד על התקציב · ביחד על התקציב ·</span>
            <span>ביחד על התקציב · ביחד על התקציב · ביחד על התקציב · ביחד על התקציב ·</span>
        </div>
    </section>

    <section class="section-pad">
        <div class="community-grid">
            <div class="community-ph" aria-hidden="true"></div>
            <div class="community-text">
                <h3>המשפחה<br>על אותו<br>קו מספרים</h3>
                <p>כשכולם רואים את אותם נתונים, קל יותר לתכנן יחד ולמנוע הפתעות.</p>
                <a class="btn-cta" href="<?php echo $reg; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="19" viewBox="0 0 26 19" fill="none" aria-hidden="true"><path d="M9.56763 1.96289L2.00086 9.52967L9.56764 17.0964" stroke="#F9F7ED" stroke-width="1.68142" stroke-linecap="square"/><path d="M25.0077 9.52905L3.14811 9.52906" stroke="#F9F7ED" stroke-width="1.68142" stroke-linecap="square"/></svg>
                    הצטרפות
                </a>
            </div>
        </div>
        <div class="wrap quote-row">
            <div class="quote-card"><p>«סוף סוף אנחנו מדברים על אותו מספר — בלי לרדוף אחרי קבלות.»</p><small>דוגמה לשיח משתמשים</small></div>
            <div class="quote-card"><p>«הקבועות חוסכות לנו זמן כל חודש. שכירות, גן, ביטוח — מוגדר פעם אחת.»</p><small>דוגמה לשיח משתמשים</small></div>
        </div>
    </section>

    <section id="secur" class="section-pad">
        <div class="wrap partner-head">
            <h5>לא משנה איך אתם מנהלים היום — אפשר לפשט</h5>
            <p>התזרים מתמקדת בנתונים שאתם מזינים ובשקיפות בבית. אין כרגע חיבור אוטומטי לבנק; ייבוא CSV מתוכנן לעתיד.</p>
        </div>
        <div class="logo-strip" aria-label="הערה">
            <span>מערכת עצמאית</span>
            <span>·</span>
            <span>עברית ו־RTL</span>
            <span>·</span>
            <span>שקיפות משפחתית</span>
        </div>
        <div style="text-align:center;margin-top:1rem;">
            <a class="btn-cta" href="<?php echo $reg; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="23" height="17" viewBox="0 0 23 17" fill="none" aria-hidden="true"><path d="M8.59121 2L2.00079 8.59042L8.59122 15.1808" stroke="#F9F7ED" stroke-width="1.46447" stroke-linecap="square"/><path d="M22.039 8.58984L3.00001 8.58985" stroke="#F9F7ED" stroke-width="1.46447" stroke-linecap="square"/></svg>
                התחילו בחינם
            </a>
        </div>
    </section>

    <section id="secur3" aria-labelledby="sec-h">
        <div class="sec-split">
            <div>
                <h3 id="sec-h">המידע שלכם<br>מטופל ברצינות</h3>
                <ul>
                    <li>סיסמאות עם הצפנה חזקה (password_hash)? <strong>כן</strong></li>
                    <li>«זכור אותי» עם טוקן מאובטח? <strong>כן</strong></li>
                    <li>איפוס סיסמה עם אימות במייל? <strong>כן</strong></li>
                    <li>חיבור ישיר לבנק בלי הסכמה מפורשת? <strong>לא — לא חלק מהמוצר כרגע</strong></li>
                </ul>
                <p style="margin-top:1rem;font-size:0.95rem;color:var(--ru-muted);"><a href="<?php echo $forgot; ?>">איפוס סיסמה</a> דרך המערכת.</p>
            </div>
            <div class="sec-illus" role="img" aria-label="איור סכמטי לאבטחה"></div>
        </div>
    </section>

    <section class="newsletter" aria-labelledby="news-h">
        <div class="news-inner">
            <p id="news-h">רוצים עדכונים וטיפים לניהול תקציב בבית?</p>
            <form action="<?php echo $reg; ?>" method="get">
                <label class="screen-reader-text" for="nl-name">שם</label>
                <input type="text" id="nl-name" name="n" placeholder="שם פרטי" autocomplete="name">
                <label class="screen-reader-text" for="nl-mail">אימייל</label>
                <input type="email" id="nl-mail" name="e" placeholder="מייל" autocomplete="email">
                <button type="submit" class="btn-cta" style="width:100%;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="23" height="17" viewBox="0 0 23 17" fill="none" aria-hidden="true"><path d="M8.59121 2L2.00079 8.59042L8.59122 15.1808" stroke="#F9F7ED" stroke-width="1.46447" stroke-linecap="square"/><path d="M22.039 8.58984L3.00001 8.58985" stroke="#F9F7ED" stroke-width="1.46447" stroke-linecap="square"/></svg>
                    המשך להרשמה
                </button>
            </form>
            <p class="hint">שולחים לדף ההרשמה — שם תשלימו פרטים מלאים.</p>
        </div>
    </section>

    <div class="sticky_text" id="stickyText" aria-hidden="true"></div>

    <section class="section-pad">
        <div class="cta-bottom">
            <h3>לנהל את התקציב של הבית יכול להיות קל,<br>ואפילו נעים</h3>
            <p>הצטרפו להתזרים — דאשבורד, בית משותף ותנועות קבועות במקום אחד.</p>
        </div>
    </section>

    <section class="center-cta">
        <a class="btn-cta" href="<?php echo $reg; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="19" viewBox="0 0 26 19" fill="none" aria-hidden="true"><path d="M9.56763 1.96289L2.00086 9.52967L9.56764 17.0964" stroke="#F9F7ED" stroke-width="1.68142" stroke-linecap="square"/><path d="M25.0077 9.52905L3.14811 9.52906" stroke="#F9F7ED" stroke-width="1.68142" stroke-linecap="square"/></svg>
            להצטרפות עכשיו
        </a>
    </section>

    <section id="faq" class="section-pad" style="background:#fff;">
        <div class="wrap" style="max-width:720px;">
            <h2 style="text-align:center;margin:0 0 1.5rem;">שאלות ותשובות</h2>
            <div class="faq-item" data-faq>
                <button type="button" class="faq-btn" aria-expanded="false">כמה עולה? <span class="faq-plus">+</span></button>
                <div class="faq-body">לפרטי תמחור עדכניים — במערכת אצל מפעילי השירות.</div>
            </div>
            <div class="faq-item" data-faq>
                <button type="button" class="faq-btn" aria-expanded="false">איך מוסיפים בן/בת זוג? <span class="faq-plus">+</span></button>
                <div class="faq-body">אחרי ההרשמה מגדירים בית משותף ומזמינים משתמשים.</div>
            </div>
            <div class="faq-item" data-faq>
                <button type="button" class="faq-btn" aria-expanded="false">יש אפליקציה בחנות? <span class="faq-plus">+</span></button>
                <div class="faq-body">השימוש בדפדפן; אפשר להתקין PWA מ-manifest האתר.</div>
            </div>
        </div>
    </section>

</main>

<footer class="site-footer">
    <div class="footer-grid">
        <div>
            <strong>על המערכת</strong>
            <p><a href="<?php echo $self; ?>#how">איך זה עובד</a><br>
            <a href="<?php echo $self; ?>#secur3">אבטחת מידע</a><br>
            <a href="<?php echo $reg; ?>">הרשמה</a></p>
        </div>
        <div>
            <strong>מידע</strong>
            <p><a href="<?php echo $self; ?>#faq">שאלות ותשובות</a><br>
            <a href="<?php echo $login; ?>">כניסה למערכת</a><br>
            <a href="<?php echo $forgot; ?>">איפוס סיסמה</a></p>
        </div>
    </div>
    <p class="footer-copy">© <?php echo date('Y'); ?> התזרים</p>
</footer>

<div class="logo-footer">
    <div class="mark">תזרים</div>
</div>

<script>
(function () {
    var banner = document.getElementById('consent-banner');
    var closeBtn = document.getElementById('consent-close');
    if (banner && closeBtn) {
        var saved = localStorage.getItem('tazrim_consent');
        var sixMonths = 180 * 24 * 60 * 60 * 1000;
        if (!saved || (Date.now() - parseInt(saved, 10)) > sixMonths) {
            banner.style.display = 'block';
        }
        closeBtn.addEventListener('click', function () {
            banner.style.display = 'none';
            localStorage.setItem('tazrim_consent', String(Date.now()));
        });
    }

    var btn = document.getElementById('menuBtn');
    var dr = document.getElementById('drawer');
    if (btn && dr) {
        btn.addEventListener('click', function () {
            var o = dr.classList.toggle('open');
            btn.setAttribute('aria-expanded', o ? 'true' : 'false');
        });
        dr.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                dr.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
            });
        });
    }

    document.querySelectorAll('[data-faq]').forEach(function (item) {
        var b = item.querySelector('.faq-btn');
        var body = item.querySelector('.faq-body');
        if (!b || !body) return;
        b.addEventListener('click', function () {
            var o = item.classList.toggle('open');
            b.setAttribute('aria-expanded', o ? 'true' : 'false');
        });
    });

    var sticky = document.getElementById('stickyText');
    if (sticky) {
        var sections = [
            { id: 'intro', text: '' },
            { id: 'Whatis', text: 'מה זה התזרים?' },
            { id: 'howitworks', text: 'איך זה עובד?' },
            { id: 'how', text: 'בשפה פשוטה' },
            { id: 'betogether', text: 'ביחד על התקציב' },
            { id: 'secur3', text: 'שומרים על המידע' }
        ];
        function updateSticky() {
            var y = window.scrollY || window.pageYOffset;
            var off = window.matchMedia('(max-width: 760px)').matches ? 125 : 200;
            var next = '';
            for (var i = sections.length - 1; i >= 0; i--) {
                var el = document.getElementById(sections[i].id);
                if (el && y + off >= el.offsetTop) {
                    next = sections[i].text;
                    break;
                }
            }
            if (sticky.textContent !== next) {
                sticky.textContent = next;
            }
        }
        window.addEventListener('scroll', updateSticky, { passive: true });
        updateSticky();
    }
})();
</script>
</body>
</html>
