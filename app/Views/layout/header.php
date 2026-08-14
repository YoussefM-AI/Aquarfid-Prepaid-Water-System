<?php
use App\Core\Session;
$currentRoute = $_GET['route'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Supervision RFID' ?> - Système RFID Eau</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Outfit + DM Serif Display -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- MQTT.js — client MQTT WebSocket direct navigateur -->
    <script src="https://unpkg.com/mqtt@5/dist/mqtt.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                        display: ['"DM Serif Display"', 'serif'],
                    },
                }
            }
        }
    </script>
    <style>
        /* ============================================================ */
        /* DESIGN TOKENS — Palette Eau & Terre Agricole                 */
        /* ============================================================ */
        :root {
            --bg-primary:   #FBF9F5;   /* Blanc cassé chaud */
            --bg-card:      #FFFFFF;   /* Blanc pur */
            --bg-input:     #F7F5F0;   /* Fond champ de saisie */
            --bg-hover:     #F0EDE6;   /* Ligne tableau survolée */
            --bg-muted:     #EEEBE3;   /* Fond badge neutre */

            --accent:       #1A65C0;   /* Bleu profond — liens, icônes actives */
            --accent-hover: #154FA0;   /* Bleu foncé au survol */
            --accent-light: rgba(26, 101, 192, 0.10); /* Fond icon accent */

            --data-key:     #993C1D;   /* Terracotta — chiffres clés */
            --data-light:   rgba(153, 60, 29, 0.08); /* Fond icon data */

            --alert:        #D85A30;   /* Rouge corail — alertes */
            --alert-bg:     #FAECE7;   /* Fond pastel alerte */
            --alert-border: rgba(216, 90, 48, 0.30);

            --success:      #3B6D11;   /* Vert olive — succès */
            --success-bg:   #EAF3DE;   /* Fond pastel succès */
            --success-border: rgba(59, 109, 17, 0.30);

            --amber:        #B5630C;   /* Ambre — suspendu */
            --amber-bg:     #FEF3DD;   /* Fond pastel ambre */

            --text-main:    #2C2C2A;   /* Anthracite chaud */
            --text-muted:   #5F5E5A;   /* Gris taupe */
            --text-faint:   #9A9890;   /* Gris très léger */

            --border:       #D3D1C7;   /* Beige grisé */
            --border-strong:#B8B6AC;   /* Bordure plus prononcée */

            --shadow-card: 0 1px 3px rgba(44, 44, 42, 0.06), 0 4px 16px rgba(44, 44, 42, 0.05);
            --shadow-sm:   0 1px 2px rgba(44, 44, 42, 0.08);
        }

        /* ============================================================ */
        /* BASE                                                         */
        /* ============================================================ */
        html { background-color: var(--bg-primary) !important; font-size: 16px; }
        body { background-color: var(--bg-primary) !important; color: var(--text-main) !important; }

        /* ============================================================ */
        /* COMPOSANTS CORE                                              */
        /* ============================================================ */
        .glass-panel {
            background: var(--bg-card) !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            border-bottom: 1px solid var(--border) !important;
            box-shadow: var(--shadow-sm) !important;
        }
        .glass-card {
            background: var(--bg-card) !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            border: 1px solid var(--border) !important;
            box-shadow: var(--shadow-card) !important;
        }

        /* ============================================================ */
        /* TEXTES                                                       */
        /* ============================================================ */
        .text-slate-100, .text-slate-200 { color: var(--text-main) !important; }
        .text-slate-300                  { color: var(--text-main) !important; }
        .text-slate-400                  { color: var(--text-muted) !important; }
        .text-slate-500                  { color: var(--text-faint) !important; }
        .text-white                      { color: var(--text-main) !important; }

        /* Dégradé texte logo → couleur plate */
        .bg-gradient-to-r.from-white.to-slate-300.bg-clip-text { -webkit-text-fill-color: var(--text-main); }

        /* ============================================================ */
        /* ACCENT — SARCELLE remplace bleu/cyan/indigo                 */
        /* ============================================================ */
        .text-blue-400, .text-cyan-400, .text-indigo-400 { color: var(--accent) !important; }

        .bg-blue-600  { background-color: var(--accent) !important; color: #fff !important; }
        .bg-blue-500  { background-color: var(--accent) !important; color: #fff !important; }

        .bg-blue-500\/10, .bg-cyan-500\/10, .bg-indigo-500\/10  { background-color: var(--accent-light) !important; }
        .bg-blue-500\/5                                          { background-color: rgba(15,110,86,0.05) !important; }

        .border-blue-500  { border-color: var(--accent) !important; }
        .border-blue-500\/20, .border-cyan-500\/20              { border-color: rgba(15,110,86,0.25) !important; }

        .hover\:bg-blue-500:hover, .hover\:bg-blue-600:hover    { background-color: var(--accent-hover) !important; }
        .hover\:bg-blue-500\/10:hover                           { background-color: rgba(15,110,86,0.08) !important; }
        .hover\:border-blue-500\/30:hover                       { border-color: rgba(15,110,86,0.35) !important; }

        .focus\:border-blue-500:focus  { border-color: var(--accent) !important; }
        .focus\:ring-blue-500          { --tw-ring-color: var(--accent) !important; }

        .shadow-blue-600\/10 { box-shadow: 0 4px 14px rgba(26, 101, 192, 0.20) !important; }

        /* Tab actif */
        .tab-btn.text-blue-400.border-blue-500 {
            color: var(--accent) !important;
            border-color: var(--accent) !important;
        }

        /* ============================================================ */
        /* CHIFFRES CLÉS — TERRACOTTA                                  */
        /* ============================================================ */
        .text-2xl.font-extrabold { color: var(--data-key) !important; }

        /* ============================================================ */
        /* ALERTES — CORAIL                                            */
        /* ============================================================ */
        .text-red-400   { color: var(--alert) !important; }
        .text-red-400.animate-pulse { color: var(--alert) !important; }

        .bg-red-500\/5, .bg-red-500\/10, .bg-red-500\/20   { background-color: var(--alert-bg) !important; }
        .border-red-500\/20, .border-red-500\/10           { border-color: var(--alert-border) !important; }

        .hover\:bg-red-500\/10:hover  { background-color: rgba(216,90,48,0.09) !important; }
        .hover\:bg-red-500\/20:hover  { background-color: rgba(216,90,48,0.14) !important; }
        .hover\:border-red-500\/30:hover { border-color: rgba(216,90,48,0.40) !important; }

        .shadow-red-500\/10 { box-shadow: 0 4px 14px rgba(216,90,48,0.15) !important; }

        /* ============================================================ */
        /* SUCCÈS — VERT OLIVE                                         */
        /* ============================================================ */
        .text-emerald-400, .text-emerald-500 { color: var(--success) !important; }

        .bg-emerald-500\/10 { background-color: var(--success-bg) !important; }
        .bg-emerald-600     { background-color: var(--success) !important; color: #fff !important; }
        .bg-emerald-500     { background-color: var(--success) !important; color: #fff !important; }

        .border-emerald-500\/20 { border-color: var(--success-border) !important; }

        .hover\:bg-emerald-500\/10:hover { background-color: rgba(59,109,17,0.08) !important; }
        .hover\:bg-emerald-500:hover     { background-color: #2D5710 !important; }
        .hover\:border-emerald-500\/30:hover { border-color: rgba(59,109,17,0.40) !important; }

        .shadow-emerald-600\/10 { box-shadow: 0 4px 14px rgba(59,109,17,0.15) !important; }

        /* Badge statut succès/erreur composés */
        .bg-emerald-500\/10.text-emerald-400 { background-color: var(--success-bg) !important; color: var(--success) !important; }
        .bg-red-500\/10.text-red-400         { background-color: var(--alert-bg)   !important; color: var(--alert) !important; }

        /* Ping vert */
        .bg-emerald-500.animate-ping { background-color: var(--success) !important; }

        /* ============================================================ */
        /* AMBRE / SUSPENDU                                            */
        /* ============================================================ */
        .text-amber-400             { color: var(--amber) !important; }
        .bg-amber-500\/10           { background-color: var(--amber-bg) !important; }
        .hover\:bg-amber-500\/10:hover { background-color: rgba(181,99,12,0.09) !important; }
        .hover\:border-amber-500\/30:hover { border-color: rgba(181,99,12,0.40) !important; }
        .bg-amber-500\/10.text-amber-400   { background-color: var(--amber-bg) !important; color: var(--amber) !important; }

        /* ============================================================ */
        /* FONDS / ARRIÈRE-PLANS                                       */
        /* ============================================================ */
        .bg-slate-950,
        .bg-slate-900 { background-color: var(--bg-card) !important; }

        .bg-slate-900\/60,
        .bg-slate-900\/30 { background-color: rgba(255,255,255,0.85) !important; }

        .bg-slate-800,
        .bg-slate-800\/80 { background-color: var(--bg-muted) !important; }

        .bg-slate-950\/80 { background-color: rgba(44,44,42,0.50) !important; }

        /* Champs de formulaire */
        input, select, textarea {
            background-color: var(--bg-input) !important;
            border-color: var(--border) !important;
            color: var(--text-main) !important;
        }
        input::placeholder { color: var(--text-faint) !important; }

        /* ============================================================ */
        /* BORDURES & SÉPARATEURS                                      */
        /* ============================================================ */
        .border-slate-800,
        .border-slate-800\/80,
        .border-slate-800\/60,
        .border-slate-800\/40    { border-color: var(--border) !important; }
        .border-slate-700\/50    { border-color: var(--border) !important; }
        .border-b.border-slate-800 { border-bottom-color: var(--border) !important; }

        /* Tableau : lignes séparatrices */
        .divide-y.divide-slate-800\/50 > * + * { border-color: var(--border) !important; }

        /* ============================================================ */
        /* SURVOL LIGNES TABLEAU & BUTTONS                             */
        /* ============================================================ */
        .hover\:bg-slate-800\/20:hover { background-color: var(--bg-hover) !important; }
        .hover\:bg-slate-800\/50:hover { background-color: var(--bg-hover) !important; }
        .hover\:bg-slate-700:hover     { background-color: #EDEAE3 !important; }
        .hover\:bg-slate-800:hover     { background-color: #EDEAE3 !important; }
        .hover\:text-slate-200:hover   { color: var(--text-main) !important; }

        /* ============================================================ */
        /* BADGES NEUTRES (permissions, labels)                        */
        /* ============================================================ */
        .bg-slate-800.text-slate-400 {
            background-color: var(--bg-muted) !important;
            color: var(--text-muted) !important;
        }

        /* ============================================================ */
        /* ICÔNE LOGO — dégradé sarcelle/terracotta                   */
        /* ============================================================ */
        .logo-icon-bg {
            background: linear-gradient(135deg, var(--accent), #3B8FE8) !important;
            box-shadow: 0 4px 14px rgba(26,101,192,0.28) !important;
        }

        /* ============================================================ */
        /* NAVIGATION HEADER                                           */
        /* ============================================================ */
        header.sticky .flex.items-center.gap-2.px-4 {
            color: var(--text-muted) !important;
        }

        /* Statut admin connecté */
        .bg-slate-800\/80.px-3 {
            background-color: var(--bg-muted) !important;
            border-color: var(--border) !important;
        }

        /* ============================================================ */
        /* DÉCOR : BULLES FLOUTÉES (sarcelle + terracotta)             */
        /* ============================================================ */
        .deco-blob-teal  { background: rgba(26, 101, 192, 0.07) !important; }
        .deco-blob-terra { background: rgba(153, 60, 29, 0.06) !important; }

        /* ============================================================ */
        /* ANIMATIONS                                                  */
        /* ============================================================ */
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fade-in 0.35s ease-out both; }

        /* ============================================================ */
        /* SCROLLBAR DISCRÈTE (fond clair)                             */
        /* ============================================================ */
        ::-webkit-scrollbar       { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-primary); }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--border-strong); }
    </style>
</head>
<body class="h-full font-sans flex flex-col" style="background:var(--bg-primary);color:var(--text-main);">

    <!-- Dégradé décoratif — sarcelle + terracotta -->
    <div class="deco-blob-teal fixed top-0 left-1/4 w-96 h-96 rounded-full blur-[120px] pointer-events-none opacity-60"></div>
    <div class="deco-blob-terra fixed bottom-0 right-1/4 w-96 h-96 rounded-full blur-[120px] pointer-events-none opacity-60"></div>

    <!-- Barre de Navigation Principale -->
    <header class="sticky top-0 z-40 w-full glass-panel">
        <div class="max-w-screen-2xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="flex items-center justify-between h-18" style="height:68px">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="logo-icon-bg w-10 h-10 rounded-xl flex items-center justify-center shadow-lg">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-lg font-bold" style="color:var(--text-main)">H2O RFID</span>
                        <span class="text-[10px] block font-semibold tracking-wider uppercase" style="color:var(--accent)">Supervision</span>
                    </div>
                </div>

                <!-- Liens de Navigation -->
                <nav class="hidden md:flex items-center gap-1">
                    <a href="index.php?route=dashboard#supervision"
                       class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200"
                       style="color:<?= $currentRoute === 'dashboard' ? 'var(--accent)' : 'var(--text-muted)' ?>;background:<?= $currentRoute === 'dashboard' ? 'var(--accent-light)' : 'transparent' ?>">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                        <span>Supervision</span>
                    </a>
                    <?php if (isset($currentAdmin) && $currentAdmin['role'] === 'admin'): ?>
                        <!-- Configuration and Personnel links removed from navbar headers -->
                    <?php endif; ?>
                </nav>

                <!-- Zone Admin / Déconnexion -->
                <div class="flex items-center gap-3">
                    <div class="hidden sm:flex items-center gap-2 text-sm px-3 py-1.5 rounded-xl border"
                         style="background:var(--bg-muted);border-color:var(--border)">
                        <div class="w-2 h-2 rounded-full animate-ping" style="background:var(--success)"></div>
                        <span class="font-medium" style="color:var(--text-main)"><?= htmlspecialchars(Session::getAdminName()) ?></span>
                    </div>
                    <a href="index.php?route=logout"
                       class="flex items-center justify-center w-10 h-10 rounded-xl border transition-all duration-200"
                       style="background:var(--alert-bg);border-color:var(--alert-border);color:var(--alert)"
                       title="Se déconnecter">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Conteneur Principal -->
    <main class="flex-grow max-w-screen-2xl w-full mx-auto px-6 sm:px-8 lg:px-12 py-10 relative z-10">
        
        <!-- Notifications Flash -->
        <?php if (Session::hasFlash('success')): ?>
            <div class="mb-6 p-4 rounded-xl border flex items-center gap-3 animate-fade-in"
                 style="background:var(--success-bg);border-color:var(--success-border);color:var(--success)">
                <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
                <span class="text-sm font-medium"><?= Session::getFlash('success') ?></span>
            </div>
        <?php endif; ?>

        <?php if (Session::hasFlash('error')): ?>
            <div class="mb-6 p-4 rounded-xl border flex items-center gap-3 animate-fade-in"
                 style="background:var(--alert-bg);border-color:var(--alert-border);color:var(--alert)">
                <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0"></i>
                <span class="text-sm font-medium"><?= Session::getFlash('error') ?></span>
            </div>
        <?php endif; ?>

