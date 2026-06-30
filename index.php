<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Cooperativa de Ahorro y Crédito</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Inter',sans-serif;color:#0f172a;overflow-x:hidden;background:#fff;}

        /* NAV */
        nav{position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(10,36,99,.97);height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 6%;}
        .nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
        .nav-logo{width:36px;height:36px;border-radius:8px;background:#fff;padding:4px;object-fit:contain;}
        .nav-brand-text h2{color:#fff;font-size:1rem;font-weight:700;line-height:1;}
        .nav-brand-text span{color:#93c5fd;font-size:.65rem;font-weight:400;}
        .nav-links{display:flex;align-items:center;gap:24px;}
        .nav-links a{color:rgba(255,255,255,.75);text-decoration:none;font-size:.85rem;font-weight:500;transition:color .2s;}
        .nav-links a:hover{color:#fff;}
        .nav-cta{background:#2563eb;color:#fff !important;padding:8px 18px;border-radius:7px;font-weight:600 !important;}
        .nav-login{color:rgba(255,255,255,.75) !important;}
        .menu-btn{display:none;background:none;border:none;cursor:pointer;color:#fff;padding:4px;}
        .menu-btn svg{width:24px;height:24px;}
        .nav-mobile{display:none;position:fixed;top:64px;left:0;right:0;background:#0a2463;padding:16px 6%;flex-direction:column;gap:0;z-index:99;border-top:1px solid rgba(255,255,255,.1);}
        .nav-mobile.open{display:flex;}
        .nav-mobile a{color:rgba(255,255,255,.8);text-decoration:none;font-size:.9rem;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.07);}
        .nav-mobile a:last-child{border-bottom:none;margin-top:8px;}
        .nav-mobile .mob-cta{background:#2563eb;color:#fff;text-align:center;border-radius:8px;padding:12px;border:none !important;}

        /* HERO */
        .hero{min-height:100vh;background:linear-gradient(150deg,#0a2463 0%,#1e40af 60%,#0369a1 100%);padding:100px 6% 60px;display:flex;align-items:center;position:relative;overflow:hidden;}
        .hero-bg-circle{position:absolute;border-radius:50%;background:rgba(255,255,255,.04);}
        .hero-bg-circle.c1{width:500px;height:500px;top:-150px;right:-150px;}
        .hero-bg-circle.c2{width:300px;height:300px;bottom:-80px;left:-80px;}
        .hero-inner{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center;width:100%;max-width:1100px;margin:0 auto;position:relative;z-index:1;}
        .hero-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.1);color:#bfdbfe;padding:6px 14px;border-radius:20px;font-size:.75rem;font-weight:500;margin-bottom:18px;border:1px solid rgba(255,255,255,.15);}
        .hero-badge-dot{width:6px;height:6px;border-radius:50%;background:#22c55e;}
        .hero h1{font-size:2.8rem;font-weight:800;color:#fff;line-height:1.2;margin-bottom:18px;}
        .hero h1 .accent{color:#38bdf8;display:block;}
        .hero-desc{font-size:.95rem;color:rgba(255,255,255,.78);line-height:1.75;margin-bottom:32px;max-width:480px;}
        .hero-btns{display:flex;gap:12px;flex-wrap:wrap;}
        .btn-hero-primary{background:#2563eb;color:#fff;padding:13px 28px;border-radius:8px;font-size:.92rem;font-weight:600;text-decoration:none;transition:background .2s;}
        .btn-hero-primary:hover{background:#1d4ed8;}
        .btn-hero-secondary{background:rgba(255,255,255,.1);color:#fff;padding:13px 28px;border-radius:8px;font-size:.92rem;font-weight:500;text-decoration:none;border:1px solid rgba(255,255,255,.25);transition:background .2s;}
        .btn-hero-secondary:hover{background:rgba(255,255,255,.18);}
        .hero-stats{display:flex;gap:36px;margin-top:44px;padding-top:36px;border-top:1px solid rgba(255,255,255,.12);}
        .hero-stat-num{font-size:1.6rem;font-weight:700;color:#fff;}
        .hero-stat-lbl{font-size:.72rem;color:rgba(255,255,255,.55);margin-top:2px;}
        .hero-img-wrap{position:relative;display:flex;justify-content:flex-end;}
        .hero-img-wrap img{width:100%;max-width:460px;border-radius:16px;object-fit:cover;}

        /* SECCIONES */
        .section{padding:72px 6%;}
        .section-inner{max-width:1100px;margin:0 auto;}
        .section-header{text-align:center;margin-bottom:48px;}
        .section-eyebrow{font-size:.72rem;font-weight:600;color:#2563eb;text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px;}
        .section-title{font-size:1.8rem;font-weight:700;color:#0f172a;margin-bottom:12px;}
        .section-sub{font-size:.95rem;color:#64748b;max-width:520px;margin:0 auto;line-height:1.7;}

        /* BENEFICIOS */
        .beneficios-bg{background:#f8fafc;}
        .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
        .ben-card{background:#fff;border-radius:12px;padding:24px;border:1px solid #e2e8f0;transition:box-shadow .2s,transform .2s;}
        .ben-card:hover{box-shadow:0 8px 24px rgba(0,0,0,.08);transform:translateY(-3px);}
        .ben-ico-wrap{width:46px;height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;}
        .ben-ico-wrap svg{width:22px;height:22px;}
        .ben-card h3{font-size:.95rem;font-weight:600;color:#0f172a;margin-bottom:6px;}
        .ben-card p{font-size:.84rem;color:#64748b;line-height:1.6;}

        /* PRODUCTOS */
        .grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;}
        .prod-card{background:#fff;border-radius:14px;padding:24px;border:1px solid #e2e8f0;text-align:center;transition:box-shadow .2s,transform .2s;position:relative;overflow:hidden;}
        .prod-card:hover{box-shadow:0 8px 24px rgba(0,0,0,.08);transform:translateY(-3px);}
        .prod-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
        .prod-card.p1::before{background:#2563eb;}
        .prod-card.p2::before{background:#059669;}
        .prod-card.p3::before{background:#d97706;}
        .prod-card.p4::before{background:#7c3aed;}
        .prod-icon{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;}
        .prod-icon svg{width:24px;height:24px;}
        .prod-card h3{font-size:.9rem;font-weight:600;color:#0f172a;margin-bottom:10px;}
        .prod-tasa{font-size:2rem;font-weight:700;margin-bottom:2px;}
        .prod-tasa-lbl{font-size:.72rem;color:#94a3b8;margin-bottom:10px;}
        .prod-card p{font-size:.8rem;color:#64748b;line-height:1.5;margin-bottom:12px;}
        .prod-rango{font-size:.75rem;font-weight:600;padding:5px 10px;border-radius:6px;display:inline-block;}

        /* PROCESO */
        .proceso-bg{background:#f8fafc;}
        .steps-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;position:relative;}
        .steps-grid::before{content:'';position:absolute;top:28px;left:12%;right:12%;height:1px;background:#cbd5e1;z-index:0;}
        .step-card{text-align:center;position:relative;z-index:1;}
        .step-num{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.2rem;font-weight:700;color:#fff;}
        .step-card h3{font-size:.9rem;font-weight:600;color:#0f172a;margin-bottom:6px;}
        .step-card p{font-size:.8rem;color:#64748b;line-height:1.5;}

        /* TASAS */
        .tasas-bg{background:linear-gradient(135deg,#0a2463,#1e40af);}
        .tasas-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;max-width:800px;margin:0 auto 36px;}
        .tasa-box{background:rgba(255,255,255,.08);border-radius:12px;padding:22px;text-align:center;border:1px solid rgba(255,255,255,.12);}
        .tasa-num{font-size:2rem;font-weight:700;color:#38bdf8;}
        .tasa-lbl{font-size:.75rem;color:rgba(255,255,255,.6);margin-top:4px;line-height:1.4;}

        /* CTA */
        .cta-box{background:#eff6ff;border-radius:16px;padding:56px 40px;text-align:center;max-width:700px;margin:0 auto;border:1px solid #bfdbfe;}
        .cta-box h2{font-size:1.7rem;font-weight:700;color:#0f172a;margin-bottom:12px;}
        .cta-box p{font-size:.95rem;color:#475569;margin-bottom:28px;line-height:1.7;}
        .cta-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
        .btn-cta-primary{background:#2563eb;color:#fff;padding:13px 28px;border-radius:8px;font-size:.92rem;font-weight:600;text-decoration:none;}
        .btn-cta-secondary{background:#fff;color:#2563eb;padding:13px 28px;border-radius:8px;font-size:.92rem;font-weight:500;text-decoration:none;border:1px solid #bfdbfe;}

        /* FOOTER */
        footer{background:#0a2463;padding:48px 6% 24px;}
        .footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;max-width:1100px;margin:0 auto 36px;}
        .footer-brand h2{color:#fff;font-size:1.1rem;font-weight:700;margin-bottom:8px;}
        .footer-brand p{color:rgba(255,255,255,.55);font-size:.83rem;line-height:1.7;}
        .footer-col h3{color:#fff;font-size:.83rem;font-weight:600;margin-bottom:14px;text-transform:uppercase;letter-spacing:.06em;}
        .footer-col a{display:block;color:rgba(255,255,255,.55);text-decoration:none;font-size:.83rem;margin-bottom:8px;transition:color .2s;}
        .footer-col a:hover{color:#fff;}
        .footer-bottom{border-top:1px solid rgba(255,255,255,.08);padding-top:20px;text-align:center;font-size:.78rem;color:rgba(255,255,255,.4);max-width:1100px;margin:0 auto;}

        /* WA */
        .wa-float{position:fixed;bottom:24px;right:24px;width:52px;height:52px;background:#25d366;border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:200;text-decoration:none;box-shadow:0 4px 14px rgba(37,211,102,.45);}
        .wa-float svg{width:28px;height:28px;fill:#fff;}

        /* RESPONSIVE */
        @media(max-width:960px){
            .nav-links{display:none;}
            .menu-btn{display:block;}
            .hero-inner{grid-template-columns:1fr;}
            .hero-img-wrap{display:none;}
            .hero{min-height:auto;padding:88px 6% 52px;}
            .hero h1{font-size:2rem;}
            .hero-stats{gap:24px;}
            .grid-3{grid-template-columns:1fr 1fr;}
            .grid-4{grid-template-columns:1fr 1fr;}
            .steps-grid{grid-template-columns:1fr 1fr;gap:16px;}
            .steps-grid::before{display:none;}
            .tasas-grid{grid-template-columns:1fr 1fr;}
            .footer-grid{grid-template-columns:1fr 1fr;}
            .cta-box{padding:36px 24px;}
        }
        @media(max-width:560px){
            .hero h1{font-size:1.7rem;}
            .hero-desc{font-size:.88rem;}
            .hero-stats{gap:20px;}
            .hero-stat-num{font-size:1.3rem;}
            .grid-3{grid-template-columns:1fr;}
            .grid-4{grid-template-columns:1fr 1fr;}
            .section{padding:52px 5%;}
            .section-title{font-size:1.4rem;}
            .footer-grid{grid-template-columns:1fr;}
            .cta-btns{flex-direction:column;}
            .btn-cta-primary,.btn-cta-secondary{text-align:center;}
            .hero-btns{flex-direction:column;}
            .btn-hero-primary,.btn-hero-secondary{text-align:center;}
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <a href="#" class="nav-brand">
        <img src="public/img/logo.png" alt="CREDISOL" class="nav-logo">
        <div class="nav-brand-text"><h2>CREDISOL</h2><span>Cooperativa de Ahorro y Crédito</span></div>
    </a>
    <div class="nav-links">
        <a href="#productos">Productos</a>
        <a href="#proceso">¿Cómo funciona?</a>
        <a href="#tasas">Tasas</a>
        <a href="#contacto">Contacto</a>
        <a href="views/auth/login.php" class="nav-login">Iniciar sesión</a>
        <a href="views/auth/registro.php" class="nav-cta">Registrarse</a>
    </div>
    <button class="menu-btn" onclick="toggleMenu()" aria-label="Menú">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
</nav>

<div class="nav-mobile" id="navMobile">
    <a href="#productos" onclick="closeMenu()">Productos</a>
    <a href="#proceso" onclick="closeMenu()">¿Cómo funciona?</a>
    <a href="#tasas" onclick="closeMenu()">Tasas</a>
    <a href="#contacto" onclick="closeMenu()">Contacto</a>
    <a href="views/auth/login.php">Iniciar sesión</a>
    <a href="views/auth/registro.php" class="mob-cta">Registrarse gratis</a>
</div>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg-circle c1"></div>
    <div class="hero-bg-circle c2"></div>
    <div class="hero-inner">
        <div>
            <div class="hero-badge"><span class="hero-badge-dot"></span> Cooperativa confiable — Supervisada por la SBS</div>
            <h1>Tu préstamo,<br><span class="accent">rápido y seguro</span></h1>
            <p class="hero-desc">En CREDISOL ofrecemos préstamos personales, microempresariales y de vivienda con las tasas más competitivas del mercado. Proceso 100% digital.</p>
            <div class="hero-btns">
                <a href="views/auth/registro.php" class="btn-hero-primary">Solicitar préstamo</a>
                <a href="#proceso" class="btn-hero-secondary">¿Cómo funciona?</a>
            </div>
            <div class="hero-stats">
                <div><div class="hero-stat-num">500+</div><div class="hero-stat-lbl">Clientes atendidos</div></div>
                <div><div class="hero-stat-num">S/ 2M+</div><div class="hero-stat-lbl">Desembolsados</div></div>
                <div><div class="hero-stat-num">48 hrs</div><div class="hero-stat-lbl">Tiempo de aprobación</div></div>
            </div>
        </div>
        <div class="hero-img-wrap">
            <img src="public/img/ejecutiva.png" alt="Asesora CREDISOL">
        </div>
    </div>
</section>

<!-- BENEFICIOS -->
<section class="section beneficios-bg" id="beneficios">
    <div class="section-inner">
        <div class="section-header">
            <div class="section-eyebrow">Por qué elegirnos</div>
            <h2 class="section-title">Ventajas de ser socio CREDISOL</h2>
            <p class="section-sub">Somos una cooperativa comprometida con el bienestar financiero de nuestros asociados</p>
        </div>
        <div class="grid-3">
            <div class="ben-card">
                <div class="ben-ico-wrap" style="background:#dbeafe;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#2563eb" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h3>Aprobación en 48 horas</h3>
                <p>Evaluamos tu solicitud de forma ágil. Sin largas esperas ni trámites presenciales innecesarios.</p>
            </div>
            <div class="ben-card">
                <div class="ben-ico-wrap" style="background:#d1fae5;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3>Tasas competitivas</h3>
                <p>Ofrecemos las mejores tasas del mercado cooperativo. Desde 12% anual para préstamos educativos.</p>
            </div>
            <div class="ben-card">
                <div class="ben-ico-wrap" style="background:#fef3c7;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#d97706" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <h3>100% seguro y cifrado</h3>
                <p>Tu información está protegida con encriptación de datos. Operamos bajo supervisión de la SBS.</p>
            </div>
            <div class="ben-card">
                <div class="ben-ico-wrap" style="background:#ede9fe;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#7c3aed" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <h3>Gestión 100% digital</h3>
                <p>Solicita, sube documentos y haz seguimiento desde tu celular o computadora en cualquier momento.</p>
            </div>
            <div class="ben-card">
                <div class="ben-ico-wrap" style="background:#fee2e2;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#dc2626" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h3>Asesor personalizado</h3>
                <p>Un asesor dedicado te acompaña durante todo el proceso de evaluación y aprobación.</p>
            </div>
            <div class="ben-card">
                <div class="ben-ico-wrap" style="background:#e0f2fe;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#0284c7" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <h3>Cuenta de ahorros</h3>
                <p>Abre tu cuenta de ahorros y genera rentabilidad de hasta 3.5% anual sobre tu saldo disponible.</p>
            </div>
        </div>
    </div>
</section>

<!-- PRODUCTOS -->
<section class="section" id="productos">
    <div class="section-inner">
        <div class="section-header">
            <div class="section-eyebrow">Nuestros productos</div>
            <h2 class="section-title">Préstamos para cada necesidad</h2>
            <p class="section-sub">Condiciones flexibles y transparentes, sin costos ocultos</p>
        </div>
        <div class="grid-4">
            <div class="prod-card p1">
                <div class="prod-icon" style="background:#dbeafe;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#2563eb" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <h3>Préstamo de Consumo</h3>
                <div class="prod-tasa" style="color:#2563eb;">18%</div>
                <div class="prod-tasa-lbl">Tasa efectiva anual</div>
                <p>Para gastos personales, salud, viajes o emergencias familiares.</p>
                <span class="prod-rango" style="background:#eff6ff;color:#1d4ed8;">S/ 500 — S/ 20,000</span>
            </div>
            <div class="prod-card p2">
                <div class="prod-icon" style="background:#d1fae5;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <h3>Microempresa</h3>
                <div class="prod-tasa" style="color:#059669;">24%</div>
                <div class="prod-tasa-lbl">Tasa efectiva anual</div>
                <p>Capital de trabajo para hacer crecer tu negocio o emprendimiento.</p>
                <span class="prod-rango" style="background:#f0fdf4;color:#065f46;">S/ 1,000 — S/ 50,000</span>
            </div>
            <div class="prod-card p3">
                <div class="prod-icon" style="background:#fef3c7;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#d97706" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                </div>
                <h3>Vivienda</h3>
                <div class="prod-tasa" style="color:#d97706;">15%</div>
                <div class="prod-tasa-lbl">Tasa efectiva anual</div>
                <p>Construye, mejora o adquiere tu vivienda con plazos extendidos.</p>
                <span class="prod-rango" style="background:#fffbeb;color:#92400e;">S/ 5,000 — S/ 100,000</span>
            </div>
            <div class="prod-card p4">
                <div class="prod-icon" style="background:#ede9fe;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#7c3aed" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                </div>
                <h3>Educativo</h3>
                <div class="prod-tasa" style="color:#7c3aed;">12%</div>
                <div class="prod-tasa-lbl">Tasa efectiva anual</div>
                <p>Invierte en tu formación profesional o la educación de tu familia.</p>
                <span class="prod-rango" style="background:#f5f3ff;color:#5b21b6;">S/ 500 — S/ 15,000</span>
            </div>
        </div>
    </div>
</section>

<!-- PROCESO -->
<section class="section proceso-bg" id="proceso">
    <div class="section-inner">
        <div class="section-header">
            <div class="section-eyebrow">Proceso simple</div>
            <h2 class="section-title">Tu préstamo en 4 pasos</h2>
            <p class="section-sub">Sin complicaciones ni papeleo innecesario</p>
        </div>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-num" style="background:#2563eb;">1</div>
                <h3>Regístrate</h3>
                <p>Crea tu cuenta con tu DNI y datos personales en menos de 3 minutos.</p>
            </div>
            <div class="step-card">
                <div class="step-num" style="background:#0369a1;">2</div>
                <h3>Solicita</h3>
                <p>Elige el tipo, monto y plazo de préstamo que se ajuste a tu necesidad.</p>
            </div>
            <div class="step-card">
                <div class="step-num" style="background:#7c3aed;">3</div>
                <h3>Evaluación</h3>
                <p>Un asesor revisa tu solicitud, valida documentos y te notifica el resultado.</p>
            </div>
            <div class="step-card">
                <div class="step-num" style="background:#059669;">4</div>
                <h3>Recibe el dinero</h3>
                <p>Aprobado el préstamo, el desembolso se realiza de forma inmediata.</p>
            </div>
        </div>
    </div>
</section>

<!-- TASAS -->
<section class="section tasas-bg" id="tasas">
    <div class="section-inner">
        <div class="section-header">
            <div class="section-eyebrow" style="color:#7dd3fc;">Transparencia total</div>
            <h2 class="section-title" style="color:#fff;">Tasas y condiciones</h2>
            <p class="section-sub" style="color:rgba(255,255,255,.65);">Sin costos ocultos — lo que ves es lo que pagas</p>
        </div>
        <div class="tasas-grid">
            <div class="tasa-box">
                <div class="tasa-num">12%</div>
                <div class="tasa-lbl">Préstamo Educativo<br>TEA anual</div>
            </div>
            <div class="tasa-box">
                <div class="tasa-num">15%</div>
                <div class="tasa-lbl">Préstamo Vivienda<br>TEA anual</div>
            </div>
            <div class="tasa-box">
                <div class="tasa-num">18%</div>
                <div class="tasa-lbl">Préstamo Consumo<br>TEA anual</div>
            </div>
            <div class="tasa-box">
                <div class="tasa-num">3.5%</div>
                <div class="tasa-lbl">Cuenta de Ahorros<br>TEA anual</div>
            </div>
        </div>
        <div style="text-align:center;">
            <a href="views/auth/registro.php" class="btn-hero-primary">Solicitar préstamo ahora</a>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="section" id="contacto">
    <div class="section-inner">
        <div class="cta-box">
            <h2>Comienza hoy mismo</h2>
            <p>Regístrate de forma gratuita y accede a todos los productos financieros de CREDISOL. Sin costo de afiliación, sin comisiones ocultas.</p>
            <div class="cta-btns">
                <a href="views/auth/registro.php" class="btn-cta-primary">Crear mi cuenta</a>
                <a href="views/auth/login.php" class="btn-cta-secondary">Ya tengo cuenta</a>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div class="footer-brand">
            <h2>CREDISOL</h2>
            <p>Cooperativa de Ahorro y Crédito comprometida con el bienestar financiero de sus asociados. Supervisada por la Superintendencia de Banca y Seguros del Perú.</p>
        </div>
        <div class="footer-col">
            <h3>Productos</h3>
            <a href="#productos">Préstamo Consumo</a>
            <a href="#productos">Préstamo Microempresa</a>
            <a href="#productos">Préstamo Vivienda</a>
            <a href="#productos">Préstamo Educativo</a>
            <a href="#productos">Cuenta de Ahorros</a>
        </div>
        <div class="footer-col">
            <h3>Acceso</h3>
            <a href="views/auth/registro.php">Registrarse</a>
            <a href="views/auth/login.php">Iniciar Sesión</a>
        </div>
        <div class="footer-col">
            <h3>Contacto</h3>
            <a href="#">Av. Principal 123, Huaraz</a>
            <a href="#">(043) 123-456</a>
            <a href="#">info@credisol.pe</a>
            <a href="#">Lun — Vie: 8:00am — 6:00pm</a>
        </div>
    </div>
    <div class="footer-bottom">
        <p>© <?php echo date('Y'); ?> CREDISOL — Cooperativa de Ahorro y Crédito. Todos los derechos reservados.</p>
    </div>
</footer>

<!-- BOTÓN WHATSAPP -->
<a href="https://wa.me/51999999999?text=Hola,%20me%20interesa%20obtener%20información%20sobre%20los%20préstamos%20de%20CREDISOL"
   target="_blank" class="wa-float" aria-label="Contactar por WhatsApp">
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
</a>

<script>
var menuOpen = false;
function toggleMenu(){
    menuOpen = !menuOpen;
    document.getElementById('navMobile').classList.toggle('open', menuOpen);
}
function closeMenu(){
    menuOpen = false;
    document.getElementById('navMobile').classList.remove('open');
}
document.querySelectorAll('a[href^="#"]').forEach(function(a){
    a.addEventListener('click',function(e){
        var t=document.querySelector(this.getAttribute('href'));
        if(t){e.preventDefault();t.scrollIntoView({behavior:'smooth'});}
    });
});
var obs = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
        if(e.isIntersecting){
            e.target.style.opacity='1';
            e.target.style.transform='translateY(0)';
        }
    });
},{threshold:0.1});
document.querySelectorAll('.ben-card,.prod-card,.step-card,.tasa-box').forEach(function(el){
    el.style.opacity='0';
    el.style.transform='translateY(24px)';
    el.style.transition='opacity .5s ease, transform .5s ease';
    obs.observe(el);
});
</script>
</body>
</html>