<?php
    require('../path.php');
    require_once ROOT_PATH . '/app/functions/landing_visit_log.php';
    tazrim_log_landing_page_visit();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>התזרים</title>
    <meta name="description" content="מערכת התזרים עוזרת לכם לעקוב אחרי הוצאות והכנסות, לתכנן חודש קדימה, ולדעת בדיוק לאן הכסף הולך - ללא חיבור לחשבון הבנק, בחינם לגמרי.">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Assistant:wght@200;300;400;500;600;700;800&family=Montserrat:ital,wght@0,100;1,100&family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://kit.fontawesome.com/9a47092d09.js" crossorigin="anonymous"></script>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>


    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>favicon.ico">
    <meta name="theme-color" content="#29b669">

    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="התזרים">

    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/apple-touch-icon.png">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>landing/assets/header.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>landing/assets/footer.css">

    <style>
        /* =========================================
           Core Settings & Design System Inherited
        ========================================= */
        :root {
            --main: #29b669;
            --main-dark: #68D391;
            --main-light: rgba(35, 114, 39, 0.1);
            --sub_main: #4FD1C5;
            --error: #F56565;
            --success: #68D391;
            --text: #2D3748;
            --text-light: #777777;
            --gray: #ECEDEF;
            --gray-light: #F8F9FA;
            --white: #fff;
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            --box-shadow-hover: 0 10px 30px rgba(0, 0, 0, 0.06);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Assistant', sans-serif; }

        html { scroll-behavior: smooth; scroll-padding-top: 90px; }
        body { background-color: var(--white); color: var(--text); line-height: 1.6; font-size: 18px; overflow-x: hidden; }
        section[id] { scroll-margin-top: 90px; }

        /* Typography - Updated H2 sizes and weights */
        h1, h2, h3 { line-height: 1; color: var(--text); }
        h1 { font-size: 2.5rem; font-weight: 800; }
        h2 { font-size: 1.6rem; font-weight: 700; margin-bottom: 1.5rem; }
        h3 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
        p { color: var(--text-light); font-size: 1rem; margin-bottom: 1rem; }

        @media (min-width: 768px) {
            h1 { font-size: 3.2rem; }
            h2 { font-size: 1.9rem; } /* Reduced from 2.2rem */
        }

        /* Reusable Components */
        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .section-pad { padding: 70px 0; }
        .bg-gray-light { background-color: var(--gray-light); }
        .text-center { text-align: center; }

        /* Animations */
        @keyframes softPulse {
            0% { transform: scale(1); box-shadow: 0 4px 15px rgba(41, 182, 105, 0.2); }
            50% { transform: scale(1.04); box-shadow: 0 8px 25px rgba(41, 182, 105, 0.4); }
            100% { transform: scale(1); box-shadow: 0 4px 15px rgba(41, 182, 105, 0.2); }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes itemFadeIn {
            from { opacity: 0; transform: translateX(15px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .btn-primary {
            display: inline-flex; align-items: center; justify-content: center;
            background-color: var(--main); color: var(--white);
            border: none; padding: 16px 32px; border-radius: 999px;
            font-weight: 700; font-size: 1.1rem; cursor: pointer;
            text-decoration: none; transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(41, 182, 105, 0.2);
            width: 100%; max-width: 300px;
        }

        .btn-primary.animated-btn { animation: softPulse 2.5s infinite; }
        .btn-primary.animated-btn:hover { animation: none; background-color: var(--main-dark); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(41, 182, 105, 0.3); }

        /* =========================================
           CTA Hover Arrow (appears on the inline-end side, RTL forward)
        ========================================= */
        .btn-primary,
        .header-btn--primary {
            position: relative;
        }
        .btn-primary::after,
        .header-btn--primary::after {
            content: "\f060"; /* Font Awesome: fa-arrow-left */
            font-family: "Font Awesome 6 Free", "Font Awesome 5 Free", "FontAwesome";
            font-weight: 900;
            font-size: 0.9em;
            line-height: 1;
            display: inline-block;
            max-width: 0;
            opacity: 0;
            margin-inline-start: 0;
            transform: translateX(6px);
            overflow: hidden;
            white-space: nowrap;
            vertical-align: -0.05em;
            transition:
                max-width 0.3s ease,
                opacity 0.25s ease,
                margin-inline-start 0.3s ease,
                transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-primary:hover::after,
        .btn-primary:focus-visible::after,
        .header-btn--primary:hover::after,
        .header-btn--primary:focus-visible::after {
            max-width: 1.2em;
            opacity: 1;
            margin-inline-start: 8px;
            transform: translateX(0);
        }

        /* Ghost button: nudge its existing icon on hover */
        .header-btn--ghost i { transition: transform 0.25s ease; }
        .header-btn--ghost:hover i,
        .header-btn--ghost:focus-visible i { transform: translateX(-3px); }

        @media (prefers-reduced-motion: reduce) {
            .btn-primary::after,
            .header-btn--primary::after,
            .btn-primary:hover::after,
            .header-btn--primary:hover::after,
            .header-btn--ghost i,
            .header-btn--ghost:hover i { transition: none; transform: none; }
        }

        /* =========================================
           Hero & Mockup
        ========================================= */
        .hero { padding: 30px 0 30px; background: var(--white); }
        .hero .container { display: grid; grid-template-columns: 1fr; gap: 32px; align-items: center; }
        .hero-content { text-align: center; }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--main-light); color: var(--main);
            font-weight: 700; font-size: 0.85rem;
            padding: 6px 14px; border-radius: 999px;
            margin-bottom: 14px;
        }
        .hero-eyebrow i { font-size: 0.8rem; }
        .hero h1 { margin-bottom: 0.9rem; }
        .hero-subtitle { font-size: 1.05rem; margin-bottom: 2rem; color: var(--text-light); max-width: 460px; margin-inline: auto; }

        .hero-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5rem; justify-content: center; }
        .hero-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; border: 1px solid #e5e7eb; background: #fff; color: var(--text); font-weight: 700; font-size: 0.82rem; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .hero-chip i { color: var(--main); font-size: 0.85rem; }

        @media (min-width: 900px) {
            .hero { padding: 60px 0 50px; }
            .hero .container { grid-template-columns: 1.1fr 0.9fr; }
            .hero-content { text-align: right; }
            .hero-subtitle { margin-inline: 0; }
            .btn-primary { margin-left: auto; margin-right: 0; }
            .hero-chips { justify-content: flex-start; }
        }

        .hero-visual { display: flex; justify-content: center; position: relative; }
        
        /* Mobile App Mockup Styling & Animations */
        .mockup-phone { width: 290px; height: 590px; background-color: var(--white); border: 10px solid #1a202c; border-radius: 40px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; display: flex; flex-direction: column; animation: fadeUp 1s cubic-bezier(0.16, 1, 0.3, 1); }
        .mockup-notch { position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 100px; height: 20px; background-color: #1a202c; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; z-index: 10; }
        .mockup-screen { flex: 1; background-color: var(--gray-light); padding: 35px 15px 15px; display: flex; flex-direction: column; position: relative; }
        
        .m-top-bar { display: flex; align-items: center; gap: 10px; background: #fff; padding: 10px 12px; border-radius: 16px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        .m-avatar { width: 32px; height: 32px; background: var(--main); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; border: 2px solid var(--main-light); }
        .m-greeting { font-weight: 800; font-size: 0.85rem; color: var(--text); line-height: 1.1; }
        .m-greeting span { display: block; font-size: 0.65rem; color: var(--text-light); font-weight: 600; }
        
        .m-month-nav { display: flex; justify-content: center; margin-bottom: 15px; }
        .m-chip { background: var(--main); color: #fff; padding: 6px 16px; border-radius: 999px; font-weight: 700; font-size: 0.75rem; display: flex; gap: 6px; align-items: center;}
        
        .m-kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
        .m-kpi-card { background: #fff; padding: 12px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); text-align: right; border-right: 3px solid transparent;}
        .m-kpi-card.span-full { grid-column: 1 / -1; text-align: center; border-right: none; background: var(--white);}
        .m-kpi-label { font-size: 0.7rem; color: var(--text-light); font-weight: 700; margin-bottom: 2px; }
        .m-kpi-card.span-full .m-kpi-label { color: var(--main); }
        .m-kpi-val { font-size: 1.1rem; font-weight: 800; line-height: 1; }
        .m-kpi-val.error { color: var(--error); }
        .m-kpi-val.success { color: var(--success); }
        
        .m-section-title { font-size: 0.85rem; font-weight: 800; margin-bottom: 8px; color: var(--text); }
        
        .m-tx-list { display: flex; flex-direction: column; gap: 8px; }
        .m-tx-item { opacity: 0; background: #fff; padding: 10px; border-radius: 12px; display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); animation: itemFadeIn 0.5s ease-out forwards; }
        /* Staggered Animations */
        .m-tx-item:nth-child(1) { animation-delay: 0.4s; }
        .m-tx-item:nth-child(2) { animation-delay: 0.6s; }
        .m-tx-item:nth-child(3) { animation-delay: 0.8s; }

        .m-tx-icon { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; }
        .m-tx-icon.red { background: #fee2e2; color: var(--error); }
        .m-tx-icon.green { background: #dcfce7; color: var(--success); }
        .m-tx-details { flex: 1; }
        .m-tx-title { font-weight: 700; font-size: 0.8rem; line-height: 1.1;}
        .m-tx-date { font-size: 0.65rem; color: var(--text-light); }
        .m-tx-amount { font-weight: 800; font-size: 0.85rem; direction: ltr;}
        .m-tx-amount.error { color: var(--error); }
        .m-tx-amount.success { color: var(--success); }
        
        .m-fab { position: absolute; bottom: 60px; left: 20px; width: 45px; height: 45px; background: var(--main); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(41,182,105,0.3); font-size: 1.2rem;}

        /* =========================================
           Pain Section (Problem Cards)
        ========================================= */
        .pain-section { position: relative; overflow: hidden; background: var(--gray-light); }
        .pain-section::before,
        .pain-section::after {
            content: '';
            position: absolute;
            width: 320px; height: 320px;
            border-radius: 50%;
            background: radial-gradient(closest-side, rgba(245,101,101,0.06), transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        .pain-section::before { top: -120px; right: -120px; }
        .pain-section::after  { bottom: -140px; left: -140px; }
        .pain-section .container { position: relative; z-index: 1; }

        .pain-eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 0.85rem; font-weight: 700; color: var(--error);
            background: #fff5f5; border: 1px solid #fee2e2;
            padding: 6px 14px; border-radius: 999px;
            margin-bottom: 14px;
        }
        .pain-eyebrow i { font-size: 0.85rem; }

        .pain-lead { max-width: 620px; margin: 0 auto 40px; font-size: 1.05rem; color: var(--text-light); }

        .pain-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            max-width: 1040px;
            margin: 0 auto;
        }
        @media (min-width: 900px) {
            .pain-grid { grid-template-columns: repeat(3, 1fr); gap: 22px; }
        }

        .pain-item {
            position: relative;
            background: var(--white);
            border-radius: 18px;
            padding: 26px 22px 22px;
            border: 1px solid var(--gray);
            text-align: right;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            display: flex; flex-direction: column; gap: 10px;
            overflow: hidden;
        }
        .pain-item::after {
            content: '';
            position: absolute;
            top: 0; right: 0; left: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--error), #fca5a5);
            opacity: 0.55;
            transition: opacity 0.25s ease;
        }
        .pain-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(245, 101, 101, 0.12);
            border-color: #fde2e2;
        }
        .pain-item:hover::after { opacity: 1; }

        .pain-item-icon {
            width: 54px; height: 54px;
            border-radius: 14px;
            background: #fff5f5;
            color: var(--error);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 4px;
            transition: background 0.25s ease, color 0.25s ease, transform 0.25s ease;
        }
        .pain-item:hover .pain-item-icon {
            background: var(--error);
            color: #fff;
            transform: scale(1.05);
        }
        .pain-item-title {
            margin: 0;
            font-size: 1.12rem;
            font-weight: 800;
            line-height: 1.3;
            color: var(--text);
        }
        .pain-item-desc {
            margin: 0;
            font-size: 0.93rem;
            line-height: 1.6;
            color: var(--text-light);
        }

        /* =========================================
           Outcomes (Compact 2x2 Grid)
        ========================================= */
        .outcomes-intro { max-width: 560px; margin: 0 auto 32px; font-size: 1.02rem; color: var(--text-light); }
        .features-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            max-width: 920px;
            margin: 0 auto;
        }
        .feature-card {
            background: var(--white);
            padding: 20px 18px;
            border-radius: 18px;
            box-shadow: var(--box-shadow);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            border: 1px solid var(--gray);
            display: flex; align-items: flex-start; gap: 14px;
            text-align: right;
        }
        .feature-card:hover { transform: translateY(-3px); box-shadow: var(--box-shadow-hover); border-color: #d9f3e2; }
        .feature-icon {
            width: 46px; height: 46px;
            background: var(--main-light); color: var(--main);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
            transition: background 0.25s ease, color 0.25s ease;
        }
        .feature-card:hover .feature-icon { background: var(--main); color: #fff; }
        .feature-content { flex: 1; }
        .feature-content h3 { font-size: 1.02rem; margin-bottom: 4px; line-height: 1.3; }
        .feature-content p { margin: 0; font-size: 0.92rem; color: var(--text-light); line-height: 1.5; }

        @media (min-width: 700px) {
            .features-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
            .feature-card { padding: 22px 20px; }
        }

        /* =========================================
           Steps (Redesigned as Timeline)
        ========================================= */
        .steps-container { max-width: 900px; margin: 40px auto 50px; }
        .timeline-steps { display: flex; flex-direction: column; gap: 20px; }
        .timeline-step { display: flex; align-items: center; gap: 20px; background: #fff; padding: 25px 20px; border-radius: 20px; box-shadow: var(--box-shadow); border: 1px solid var(--gray); text-align: right; transition: transform 0.2s ease;}
        .timeline-step:hover { transform: translateX(-5px); border-color: var(--main-light); }
        .timeline-step-num { width: 55px; height: 55px; flex-shrink: 0; background: var(--main); color: #fff; font-size: 1.5rem; font-weight: 800; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(41,182,105,0.3);}
        .timeline-content h3 { margin-bottom: 5px; font-size: 1.2rem;}
        .timeline-content p { margin: 0; font-size: 0.95rem; }

        @media (min-width: 768px) {
            .timeline-steps { flex-direction: row; align-items: stretch; gap: 25px;}
            .timeline-step { flex-direction: column; text-align: center; flex: 1; padding: 40px 20px; position: relative; }
            .timeline-step:hover { transform: translateY(-5px); }
            .timeline-step-num { margin-bottom: 10px; z-index: 2;}
            /* Connective line desktop */
        }

        /* =========================================
           Testimonials, FAQ, CTA & Footer
        ========================================= */
        /* =========================================
           Testimonials (Scroll Gallery)
        ========================================= */
        .testimonials-section { position: relative; }
        .testimonials-lead { max-width: 600px; margin: 0 auto 30px; color: var(--text-light); font-size: 1.05rem; }

        .testi-gallery-wrap { position: relative; }
        .testi-gallery {
            display: flex;
            gap: 18px;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            scroll-padding-inline: 4px;
            padding: 14px 4px 28px;
            margin: 0 -4px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .testi-gallery::-webkit-scrollbar { display: none; }

        .testi-card {
            flex: 0 0 85%;
            max-width: 380px;
            scroll-snap-align: center;
            background: var(--white);
            border: 1px solid var(--gray);
            border-radius: 22px;
            padding: 26px 22px 22px;
            box-shadow: var(--box-shadow);
            text-align: right;
            display: flex; flex-direction: column; gap: 14px;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .testi-card::before {
            content: '\201C'; /* opening double quote */
            position: absolute;
            top: 6px; left: 18px;
            font-family: Georgia, serif;
            font-size: 4.5rem;
            line-height: 1;
            color: var(--main-light);
            pointer-events: none;
        }
        .testi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(41, 182, 105, 0.12);
            border-color: #d9f3e2;
        }

        .testi-quote {
            font-size: 1.05rem; color: var(--text);
            font-weight: 600; line-height: 1.55;
            margin: 0;
        }
        .testi-user { display: flex; align-items: center; gap: 12px; margin-top: auto; padding-top: 14px; border-top: 1px solid var(--gray); }
        .testi-avatar {
            width: 46px; height: 46px; border-radius: 50%;
            background: linear-gradient(135deg, var(--main), var(--sub_main));
            color: #fff; font-weight: 800; font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .testi-meta strong { display: block; font-size: 0.98rem; color: var(--text); font-weight: 800; }
        .testi-meta span { display: block; font-size: 0.82rem; color: var(--text-light); font-weight: 600; }

        .testi-controls {
            display: flex; align-items: center; justify-content: center;
            gap: 14px; margin-top: 10px;
        }
        .testi-btn {
            width: 44px; height: 44px; border-radius: 50%;
            border: 1.5px solid var(--gray); background: #fff;
            color: var(--main); font-size: 1rem;
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s ease;
        }
        .testi-btn:hover:not(:disabled) {
            background: var(--main); color: #fff; border-color: var(--main);
            transform: translateY(-1px);
        }
        .testi-btn:disabled { opacity: 0.35; cursor: not-allowed; }
        .testi-dots { display: inline-flex; gap: 6px; align-items: center; }
        .testi-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--gray); border: 0; cursor: pointer;
            padding: 0; transition: all 0.2s ease;
        }
        .testi-dot[aria-current="true"] { background: var(--main); width: 22px; border-radius: 999px; }

        @media (min-width: 700px) {
            .testi-card { flex-basis: 48%; }
        }
        @media (min-width: 1000px) {
            .testi-card { flex-basis: 32%; }
        }

        /* =========================================
           FAQ (Redesigned)
        ========================================= */
        .faq-lead { text-align: center; color: var(--text-light); max-width: 520px; margin: -0.5rem auto 30px; font-size: 1.02rem; }
        .faq-container { max-width: 780px; margin: 0 auto; display: grid; gap: 12px; }
        .faq-item {
            background: var(--white);
            border: 1px solid var(--gray);
            border-radius: 16px;
            overflow: hidden;
            transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease;
        }
        .faq-item:hover { border-color: #d9f3e2; }
        .faq-item.active { border-color: var(--main); box-shadow: 0 8px 24px rgba(41,182,105,0.08); }
        .faq-question {
            padding: 18px 20px;
            display: flex; align-items: center; gap: 14px;
            cursor: pointer; font-weight: 700; font-size: 1rem;
            color: var(--text);
            transition: background 0.2s;
        }
        .faq-q-icon {
            width: 34px; height: 34px; flex-shrink: 0;
            border-radius: 10px;
            background: var(--main-light); color: var(--main);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            transition: background 0.25s ease, color 0.25s ease;
        }
        .faq-item.active .faq-q-icon { background: var(--main); color: #fff; }
        .faq-q-text { flex: 1; line-height: 1.4; }
        .faq-chevron {
            color: var(--text-light); font-size: 0.85rem;
            transition: transform 0.3s ease, color 0.3s ease;
            flex-shrink: 0;
        }
        .faq-item.active .faq-chevron { color: var(--main); transform: rotate(180deg); }
        .faq-answer {
            padding: 0 20px;
            max-height: 0; overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        .faq-item.active .faq-answer { padding: 0 20px 18px 68px; max-height: 320px; }
        .faq-answer p { margin: 0; font-size: 0.95rem; color: var(--text-light); line-height: 1.65; }
        @media (max-width: 500px) {
            .faq-item.active .faq-answer { padding: 0 20px 18px 20px; }
        }

        .cta-section { background-color: var(--main); color: var(--white); text-align: center; border-radius: 30px; padding: 60px 20px; margin: 0 20px 60px; box-shadow: 0 10px 40px rgba(41,182,105,0.2); }
        .cta-section h2 { color: var(--white); margin-bottom: 1rem; }
        .cta-section p { color: rgba(255, 255, 255, 0.9); margin-bottom: 2rem; font-size: 1.15rem; }
        .cta-section .btn-primary { background-color: var(--white); color: var(--main); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .cta-section .btn-primary:hover { background-color: var(--gray-light); }
        @media (min-width: 900px) { .cta-section { margin: 0 auto 60px; max-width: 1160px; } }

        @media (max-width: 700px) {

            .hero {
                padding-top: 60px;
            }
        }

    </style>
</head>
<body>

    <?php require_once(__DIR__ . '/assets/header.php'); ?>

    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>התזרים המשפחתי,<br>בשליטה מלאה.</h1>
                <p class="hero-subtitle">רואים איפה הכסף, יודעים איך החודש ייגמר - בזמן אמת.</p>

                <a href="#steps" class="btn-primary animated-btn">בואו נתחיל</a>

                <div class="hero-chips">
                    <div class="hero-chip"><i class="fa-solid fa-tag"></i> חינמי ומאובטח</div>
                    <div class="hero-chip"><i class="fa-solid fa-shield-halved"></i> ללא חיבור לבנק</div>
                    <div class="hero-chip"><i class="fa-solid fa-users"></i> סנכרון משפחתי</div>
                </div>
            </div>
            
            <div class="hero-visual">
                <div class="mockup-phone">
                    <div class="mockup-notch"></div>
                    <div class="mockup-screen">
                        
                        <div class="m-top-bar">
                            <div class="m-avatar">מ</div>
                            <div class="m-greeting">
                                <span>בוקר טוב,</span>
                                משפחת לוי
                            </div>
                        </div>

                        <div class="m-month-nav">
                            <div class="m-chip">
                                <i class="fa-regular fa-calendar-days"></i> מאי 2026
                            </div>
                        </div>

                        <div class="m-kpi-grid">
                            <div class="m-kpi-card income">
                                <div class="m-kpi-label">הכנסות</div>
                                <div class="m-kpi-val success">+12,500 ₪</div>
                            </div>
                            <div class="m-kpi-card expense">
                                <div class="m-kpi-label">הוצאות</div>
                                <div class="m-kpi-val error">-4,480 ₪</div>
                            </div>
                            <div class="m-kpi-card span-full">
                                <div class="m-kpi-label">יתרת החשבון</div>
                                <div class="m-kpi-val">₪8,020</div>
                            </div>
                        </div>

                        <div class="m-section-title">פעולות אחרונות</div>

                        <div class="m-tx-list">
                            <div class="m-tx-item">
                                <div class="m-tx-icon red"><i class="fa-solid fa-cart-shopping"></i></div>
                                <div class="m-tx-details">
                                    <div class="m-tx-title">קניות בסופר</div>
                                    <div class="m-tx-date">היום, 14:30</div>
                                </div>
                                <div class="m-tx-amount error">-450 ₪</div>
                            </div>
                            <div class="m-tx-item">
                                <div class="m-tx-icon red"><i class="fa-solid fa-car"></i></div>
                                <div class="m-tx-details">
                                    <div class="m-tx-title">דלק</div>
                                    <div class="m-tx-date">אתמול, 09:15</div>
                                </div>
                                <div class="m-tx-amount error">-280 ₪</div>
                            </div>
                            <div class="m-tx-item">
                                <div class="m-tx-icon green"><i class="fa-solid fa-briefcase"></i></div>
                                <div class="m-tx-details">
                                    <div class="m-tx-title">משכורת</div>
                                    <div class="m-tx-date">01 במאי</div>
                                </div>
                                <div class="m-tx-amount success">+12,500 ₪</div>
                            </div>
                        </div>
                        <br>
                        <div class="m-section-title">קטגוריות</div>

                        <div class="m-tx-list">
                            <div class="m-tx-item">
                                <div class="m-tx-icon red"><i class="fa-solid fa-cart-shopping"></i></div>
                                <div class="m-tx-details">
                                    <div class="m-tx-title">חשבונות הבית</div>
                                    <div class="m-tx-date">ללא יעד</div>
                                </div>
                                <div class="m-tx-amount error">-450 ₪</div>
                            </div>
                        </div>
                        <div class="m-fab"><i class="fa-solid fa-plus"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <main>
        <section class="section-pad bg-gray-light pain-section">
            <div class="container text-center">
                <span class="pain-eyebrow"><i class="fa-solid fa-circle-exclamation"></i> אתם לא לבד</span>
                <h2>הכסף נעלם באמצע החודש?</h2>
                <p class="pain-lead">שלושת האתגרים שחוזרים על עצמם כמעט בכל משפחה צעירה - ומחכים לפתרון אחד, פשוט.</p>

                <div class="pain-grid">
                    <article class="pain-item">
                        <div class="pain-item-icon" aria-hidden="true">
                            <i class="fa-regular fa-magnifying-glass-dollar"></i>
                       
                        </div>
                        <h3 class="pain-item-title">הוצאות שמתחמקות מהעין</h3>
                        <p class="pain-item-desc">סופר, רכב, חשבונות ורכישות קטנות - הסכומים מצטברים בשקט בלי שרואים את התמונה המלאה.</p>
                    </article>

                    <article class="pain-item">
                        <div class="pain-item-icon" aria-hidden="true">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <h3 class="pain-item-title">סוף החודש באי-ודאות</h3>
                        <p class="pain-item-desc">קשה לדעת מראש אם ייסגר בפלוס או במינוס, ואין כלי אמיתי לתכנן ולקבל החלטות בבית.</p>
                    </article>

                    <article class="pain-item">
                        <div class="pain-item-icon" aria-hidden="true">
                            <i class="fa-solid fa-people-roof"></i>
                        </div>
                        <h3 class="pain-item-title">חוסר סנכרון בבית</h3>
                        <p class="pain-item-desc">כל אחד מנהל בצד את שלו - בלי מקום משותף אחד עם כל ההכנסות, ההוצאות והיעדים של המשפחה.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section-pad">
            <div class="container text-center">
                <h2>ככה זה עובד</h2>
                <p class="outcomes-intro">תמונת מצב ברורה של הבית: קטגוריות, תקציב ויתרה - בזמן אמת.</p>

                <div class="features-grid">
                    <article class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-calendar-check"></i></div>
                        <div class="feature-content">
                            <h3>רואים את סוף החודש</h3>
                            <p>צפי יתרה מדויק, אחרי כל ההוצאות וההכנסות הצפויות.</p>
                        </div>
                    </article>
                    <article class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="feature-content">
                            <h3>בלי לחבר את הבנק</h3>
                            <p>הזנה של 2 קליקים עם אוטומציות חכמות. המידע שלכם, אצלכם.</p>
                        </div>
                    </article>
                    <article class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-users"></i></div>
                        <div class="feature-content">
                            <h3>ביחד, על אותו עמוד</h3>
                            <p>שיתוף מלא בין בני הזוג, סנכרון בזמן אמת וקניות משותפות.</p>
                        </div>
                    </article>
                    <article class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-robot"></i></div>
                        <div class="feature-content">
                            <h3>יועץ AI אישי</h3>
                            <p>שואלים שאלה, מקבלים תשובה חכמה מבוססת הנתונים שלכם.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="steps" class="section-pad bg-gray-light">
            <div class="container text-center">
                <h2>שלושה צעדים להתחלה</h2>
                
                <div class="steps-container">
                    <div class="timeline-steps">
                        <div class="timeline-step">
                            <div class="timeline-step-num">1</div>
                            <div class="timeline-content">
                                <h3>הקמת הפרופיל</h3>
                                <p>נרשמים למערכת ופותחים את "הבית" שלכם באופן מאובטח.</p>
                            </div>
                        </div>
                        <div class="timeline-step">
                            <div class="timeline-step-num">2</div>
                            <div class="timeline-content">
                                <h3>התאמה אישית</h3>
                                <p>מגדירים קטגוריות הוצאות והכנסות (אפשר תמיד לערוך ולשנות).</p>
                            </div>
                        </div>
                        <div class="timeline-step">
                            <div class="timeline-step-num">3</div>
                            <div class="timeline-content">
                                <h3>שליטה מלאה</h3>
                                <p>מתחילים לעקוב בזמן אמת ונהנים מתמונת מצב חודשית ושקט נפשי.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <a href="<?php echo BASE_URL . 'pages/login.php'; ?>" class="btn-primary animated-btn">אני רוצה להתחיל עכשיו</a>
            </div>
        </section>

        <section class="section-pad testimonials-section">
            <div class="container">
                <h2 class="text-center">משפחות כבר מנהלות ברוגע</h2>
                <p class="testimonials-lead text-center">מה מרגישות משפחות צעירות שהחליפו את הגיליון האקסל ב"התזרים":</p>

                <div class="testi-gallery-wrap">
                    <div class="testi-gallery" id="testiGallery" role="region" aria-label="המלצות משפחות" tabindex="0">
                        <article class="testi-card">
                            <p class="testi-quote">"פעם ראשונה שאנחנו רואים בזמן אמת אם אנחנו עומדים בתקציב. פתאום יש שקיפות מלאה לאן הכסף הולך."</p>
                            <div class="testi-user">
                                <div class="testi-avatar" aria-hidden="true">ל</div>
                                <div class="testi-meta"><strong>משפחת לוי</strong><span>הורים לשניים, מרכז</span></div>
                            </div>
                        </article>

                        <article class="testi-card">
                            <p class="testi-quote">"תוך שבוע הבנו איפה אנחנו חורגים ומה לשפר. נוח שאנחנו לא צריכים לתת סיסמאות לבנק."</p>
                            <div class="testi-user">
                                <div class="testi-avatar" aria-hidden="true">כ</div>
                                <div class="testi-meta"><strong>משפחת כהן</strong><span>זוג צעיר, חיפה</span></div>
                            </div>
                        </article>

                        <article class="testi-card">
                            <p class="testi-quote">"סוף סוף יש לנו תמונה משפחתית אחת. כבר לא רבים על איפה הכסף - יודעים בדיוק."</p>
                            <div class="testi-user">
                                <div class="testi-avatar" aria-hidden="true">מ</div>
                                <div class="testi-meta"><strong>משפחת מזרחי</strong><span>הורים לשלושה, ירושלים</span></div>
                            </div>
                        </article>

                        <article class="testi-card">
                            <p class="testi-quote">"החיזוי לסוף החודש שינה לנו את הראש. מתכננים קדימה במקום לכבות שריפות."</p>
                            <div class="testi-user">
                                <div class="testi-avatar" aria-hidden="true">ב</div>
                                <div class="testi-meta"><strong>משפחת בן-דוד</strong><span>זוג צעיר עם תינוק</span></div>
                            </div>
                        </article>

                        <article class="testi-card">
                            <p class="testi-quote">"האוטומציות של הפעולות הקבועות חוסכות לנו שעות בחודש. זה פשוט עובד לבד."</p>
                            <div class="testi-user">
                                <div class="testi-avatar" aria-hidden="true">ש</div>
                                <div class="testi-meta"><strong>משפחת שמש</strong><span>משפחה צעירה, השרון</span></div>
                            </div>
                        </article>
                    </div>

                    <div class="testi-controls">
                        <button type="button" class="testi-btn" id="testiPrev" aria-label="המלצה קודמת" aria-controls="testiGallery">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                        <div class="testi-dots" id="testiDots" role="tablist" aria-label="נקודות ניווט בין המלצות"></div>
                        <button type="button" class="testi-btn" id="testiNext" aria-label="המלצה הבאה" aria-controls="testiGallery">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-pad bg-gray-light">
            <div class="container">
                <h2 class="text-center">שאלות נפוצות</h2>
                <p class="faq-lead">כל מה שרציתם לדעת לפני שמתחילים.</p>
                
                <div class="faq-container">
                    <div class="faq-item">
                        <div class="faq-question" role="button" tabindex="0" aria-expanded="false">
                            <span class="faq-q-icon"><i class="fa-solid fa-clock"></i></span>
                            <span class="faq-q-text">כמה זמן לוקח להתחיל?</span>
                            <i class="fa-solid fa-chevron-down faq-chevron" aria-hidden="true"></i>
                        </div>
                        <div class="faq-answer">
                            <p>ההתחלה פשוטה וקלה. ממלאים את פרטי ההרשמה וההגדרה הראשונית פעם אחת - ומתחילים לעקוב.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" role="button" tabindex="0" aria-expanded="false">
                            <span class="faq-q-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                            <span class="faq-q-text">זה מתאים גם למי שלא מבין בפיננסים?</span>
                            <i class="fa-solid fa-chevron-down faq-chevron" aria-hidden="true"></i>
                        </div>
                        <div class="faq-answer">
                            <p>כן. המערכת בנויה בשפה פשוטה וברורה, ומיועדת במיוחד למשתמשים בלי שום רקע פיננסי או ידע באקסל.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" role="button" tabindex="0" aria-expanded="false">
                            <span class="faq-q-icon"><i class="fa-solid fa-lock"></i></span>
                            <span class="faq-q-text">האם אני חייב/ת לחבר את חשבון הבנק או האשראי שלי?</span>
                            <i class="fa-solid fa-chevron-down faq-chevron" aria-hidden="true"></i>
                        </div>
                        <div class="faq-answer">
                            <p>ממש לא. אנחנו מאמינים בפרטיות מקסימלית. המערכת עובדת באופן עצמאי לחלוטין. אתם מזינים את הנתונים בצורה קלה ומהירה, בלי לחשוף שום מידע בנקאי.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" role="button" tabindex="0" aria-expanded="false">
                            <span class="faq-q-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="faq-q-text">אפשר להשתמש במערכת יחד עם בן/בת הזוג?</span>
                            <i class="fa-solid fa-chevron-down faq-chevron" aria-hidden="true"></i>
                        </div>
                        <div class="faq-answer">
                            <p>בהחלט. אפשר לשתף את הבית עם מספר משתמשים. הכל מסתנכרן בזמן אמת, כולל רשימות קניות משותפות לסופר.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" role="button" tabindex="0" aria-expanded="false">
                            <span class="faq-q-icon"><i class="fa-solid fa-tag"></i></span>
                            <span class="faq-q-text">כמה זה עולה?</span>
                            <i class="fa-solid fa-chevron-down faq-chevron" aria-hidden="true"></i>
                        </div>
                        <div class="faq-answer">
                            <p>ללא עלות. אין קאץ' ואין עלויות נסתרות, המערכת מוצעת כרגע לשימוש חופשי.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="login" class="section-pad">
            <div class="cta-section">
                <h2>מוכנים לסיים את החודש בשליטה?</h2>
                <p>הצטרפו עכשיו ל"התזרים" ותתחילו לנהל את ההוצאות וההכנסות שלכם בצורה פשוטה וברורה.</p>
                <a href="<?php echo BASE_URL . 'pages/login.php'; ?>" class="btn-primary animated-btn" style="background-color: var(--white); color: var(--main);">בואו נתחיל עכשיו</a>
                <span style="display:block; margin-top:15px; font-size:0.9rem; font-weight:600; color:rgba(255,255,255,0.8);">מתחילים בקלות, בלי מורכבות ובלי התחייבות.</span>
            </div>
        </section>
    </main>

    <?php require_once(__DIR__ . '/assets/footer.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // FAQ Logic
            const faqItems = document.querySelectorAll('.faq-item');
            const toggleFaq = (item) => {
                faqItems.forEach(otherItem => {
                    if (otherItem !== item && otherItem.classList.contains('active')) {
                        otherItem.classList.remove('active');
                        const q = otherItem.querySelector('.faq-question');
                        if (q) q.setAttribute('aria-expanded', 'false');
                    }
                });
                const isOpen = item.classList.toggle('active');
                const q = item.querySelector('.faq-question');
                if (q) q.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            };
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question');
                if (!question) return;
                question.addEventListener('click', () => toggleFaq(item));
                question.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleFaq(item);
                    }
                });
            });

            // Testimonials scroll gallery (RTL-aware)
            const gallery = document.getElementById('testiGallery');
            const prevBtn = document.getElementById('testiPrev');
            const nextBtn = document.getElementById('testiNext');
            const dotsWrap = document.getElementById('testiDots');

            if (gallery && prevBtn && nextBtn && dotsWrap) {
                const cards = Array.from(gallery.querySelectorAll('.testi-card'));

                // Build dots
                cards.forEach((_, i) => {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'testi-dot';
                    dot.setAttribute('role', 'tab');
                    dot.setAttribute('aria-label', 'מעבר להמלצה ' + (i + 1));
                    dot.dataset.index = i;
                    if (i === 0) dot.setAttribute('aria-current', 'true');
                    dotsWrap.appendChild(dot);
                });

                const dots = Array.from(dotsWrap.querySelectorAll('.testi-dot'));

                const getStep = () => {
                    const card = cards[0];
                    if (!card) return gallery.clientWidth;
                    const style = getComputedStyle(gallery);
                    const gap = parseFloat(style.columnGap || style.gap || 0) || 0;
                    return card.getBoundingClientRect().width + gap;
                };

                const currentIndex = () => {
                    // scrollLeft in RTL is 0 at start and becomes more negative (or positive depending on browser normalization)
                    const step = getStep();
                    if (step <= 0) return 0;
                    const abs = Math.abs(gallery.scrollLeft);
                    return Math.round(abs / step);
                };

                const updateControls = () => {
                    const idx = Math.min(Math.max(currentIndex(), 0), cards.length - 1);
                    dots.forEach((d, i) => {
                        if (i === idx) d.setAttribute('aria-current', 'true');
                        else d.removeAttribute('aria-current');
                    });
                    // Edge detection (works for standard + RTL where scrollLeft may be negative)
                    const maxScroll = gallery.scrollWidth - gallery.clientWidth - 2;
                    const atStart = Math.abs(gallery.scrollLeft) <= 2;
                    const atEnd   = Math.abs(gallery.scrollLeft) >= maxScroll;
                    prevBtn.disabled = atStart;
                    nextBtn.disabled = atEnd;
                };

                const scrollByStep = (dir) => {
                    // dir: -1 = previous (right in RTL), +1 = next (left in RTL)
                    // In RTL, scrolling "forward" means decreasing scrollLeft (more negative or toward 0).
                    const step = getStep();
                    gallery.scrollBy({ left: dir * step * -1, behavior: 'smooth' });
                };

                prevBtn.addEventListener('click', () => scrollByStep(-1));
                nextBtn.addEventListener('click', () => scrollByStep(+1));

                dots.forEach(dot => {
                    dot.addEventListener('click', () => {
                        const target = cards[parseInt(dot.dataset.index, 10)];
                        if (target) target.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                    });
                });

                let scrollTimer = null;
                gallery.addEventListener('scroll', () => {
                    if (scrollTimer) window.clearTimeout(scrollTimer);
                    scrollTimer = window.setTimeout(updateControls, 80);
                }, { passive: true });

                window.addEventListener('resize', updateControls);
                updateControls();

                // Autoplay (with pause on hover/focus/interaction, loops back to start)
                const AUTOPLAY_MS = 5000;
                const RESUME_AFTER_INTERACTION_MS = 9000;
                let autoplayTimer = null;
                let resumeTimer = null;

                const autoplayStep = () => {
                    const maxScroll = gallery.scrollWidth - gallery.clientWidth;
                    const currentAbs = Math.abs(gallery.scrollLeft);
                    if (currentAbs + getStep() / 2 >= maxScroll) {
                        gallery.scrollTo({ left: 0, behavior: 'smooth' });
                    } else {
                        scrollByStep(+1);
                    }
                };

                const startAutoplay = () => {
                    if (autoplayTimer) return;
                    autoplayTimer = window.setInterval(autoplayStep, AUTOPLAY_MS);
                };
                const stopAutoplay = () => {
                    if (autoplayTimer) { window.clearInterval(autoplayTimer); autoplayTimer = null; }
                };
                const pauseThenResume = () => {
                    stopAutoplay();
                    if (resumeTimer) window.clearTimeout(resumeTimer);
                    resumeTimer = window.setTimeout(startAutoplay, RESUME_AFTER_INTERACTION_MS);
                };

                gallery.addEventListener('mouseenter', stopAutoplay);
                gallery.addEventListener('mouseleave', startAutoplay);
                gallery.addEventListener('focusin', stopAutoplay);
                gallery.addEventListener('focusout', startAutoplay);
                gallery.addEventListener('touchstart', pauseThenResume, { passive: true });

                [prevBtn, nextBtn].forEach(btn => btn.addEventListener('click', pauseThenResume));
                dots.forEach(d => d.addEventListener('click', pauseThenResume));

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) stopAutoplay(); else startAutoplay();
                });

                startAutoplay();
            }

            // Smooth scroll
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    if(this.getAttribute('href') !== '#') {
                        e.preventDefault();
                        const target = document.querySelector(this.getAttribute('href'));
                        if(target) {
                            target.scrollIntoView({ behavior: 'smooth' });
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>