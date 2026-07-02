<?php
use App\Core\Session;
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — H2O RFID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Outfit', 'sans-serif'] } } }
        }
    </script>
    <style>
        :root {
            --bg-primary:   #FBF9F5;
            --bg-card:      #FFFFFF;
            --bg-input:     #F7F5F0;
            --accent:       #1A65C0;
            --accent-hover: #154FA0;
            --accent-light: rgba(26, 101, 192, 0.10);
            --data-key:     #993C1D;
            --alert:        #D85A30;
            --alert-bg:     #FAECE7;
            --alert-border: rgba(216, 90, 48, 0.30);
            --success:      #3B6D11;
            --success-bg:   #EAF3DE;
            --success-border: rgba(59, 109, 17, 0.30);
            --text-main:    #2C2C2A;
            --text-muted:   #5F5E5A;
            --text-faint:   #9A9890;
            --border:       #D3D1C7;
        }
        html, body { background-color: var(--bg-primary); color: var(--text-main); }

        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            box-shadow: 0 4px 32px rgba(44,44,42,0.10), 0 1px 4px rgba(44,44,42,0.06);
        }
        input {
            background: var(--bg-input) !important;
            border-color: var(--border) !important;
            color: var(--text-main) !important;
        }
        input::placeholder { color: var(--text-faint) !important; }
        input:focus { border-color: var(--accent) !important; outline: none; }

        @keyframes bob {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-6px); }
        }
        .logo-drop { animation: bob 3s ease-in-out infinite; }
    </style>
</head>
<body class="h-full font-sans flex items-center justify-center p-6 relative overflow-hidden"
      style="background:var(--bg-primary)">

    <!-- Blobs décoratifs -->
    <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full blur-[120px] pointer-events-none"
         style="background:rgba(15,110,86,0.08)"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 rounded-full blur-[120px] pointer-events-none"
         style="background:rgba(153,60,29,0.07)"></div>

    <div class="max-w-md w-full relative z-10">

        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="logo-drop inline-flex w-16 h-16 rounded-2xl items-center justify-center mb-4 shadow-xl"
                 style="background:linear-gradient(135deg,var(--accent),#3B8FE8);box-shadow:0 8px 24px rgba(26,101,192,0.30)">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>
                </svg>
            </div>
            <h1 class="text-3xl font-extrabold" style="color:var(--text-main)">H2O RFID</h1>
            <p class="text-sm mt-1" style="color:var(--text-muted)">Supervision &amp; Distribution d'Eau Agricole</p>
        </div>

        <!-- Carte de Connexion -->
        <div class="login-card rounded-3xl p-8">
            <h2 class="text-lg font-bold mb-6 flex items-center gap-2" style="color:var(--text-main)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0" style="color:var(--accent)">
                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Espace Administration
            </h2>

            <!-- Flash messages -->
            <?php if (Session::hasFlash('success')): ?>
                <div class="mb-5 p-4 rounded-xl border flex items-center gap-3"
                     style="background:var(--success-bg);border-color:var(--success-border);color:var(--success)">
                    <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
                    <span class="text-xs font-medium"><?= Session::getFlash('success') ?></span>
                </div>
            <?php endif; ?>
            <?php if (Session::hasFlash('error')): ?>
                <div class="mb-5 p-4 rounded-xl border flex items-center gap-3"
                     style="background:var(--alert-bg);border-color:var(--alert-border);color:var(--alert)">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
                    <span class="text-xs font-medium"><?= Session::getFlash('error') ?></span>
                </div>
            <?php endif; ?>

            <form action="index.php?route=login" method="POST" class="space-y-5">
                <!-- Email -->
                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider mb-2"
                           style="color:var(--text-muted)">Adresse Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none"
                              style="color:var(--text-faint)">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </span>
                        <input type="email" name="email" id="email" required
                               value="<?= htmlspecialchars($email ?? '') ?>"
                               placeholder="ex: admin@water.local"
                               class="block w-full pl-10 pr-4 py-3 border rounded-xl text-sm transition-all duration-200">
                    </div>
                </div>

                <!-- Mot de passe -->
                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider mb-2"
                           style="color:var(--text-muted)">Mot de passe</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none"
                              style="color:var(--text-faint)">
                            <i data-lucide="key-round" class="w-4 h-4"></i>
                        </span>
                        <input type="password" name="password" id="password" required
                               placeholder="Saisir votre mot de passe"
                               class="block w-full pl-10 pr-4 py-3 border rounded-xl text-sm transition-all duration-200">
                    </div>
                </div>

                <!-- Bouton Connexion -->
                <button type="submit"
                        class="w-full py-3.5 px-4 font-semibold rounded-xl text-sm text-white flex items-center justify-center gap-2 mt-2 transition-all duration-200"
                        style="background:var(--accent);box-shadow:0 4px 16px rgba(15,110,86,0.25)"
                        onmouseover="this.style.background='var(--accent-hover)'"
                        onmouseout="this.style.background='var(--accent)'">
                    <span>Se connecter</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>
        </div>

        <div class="text-center mt-5">
            <p class="text-xs" style="color:var(--text-faint)">
                Compte par défaut :
                <code class="px-1.5 py-0.5 rounded border font-mono"
                      style="background:var(--bg-input);border-color:var(--border);color:var(--text-muted)">admin@water.local</code>
                /
                <code class="px-1.5 py-0.5 rounded border font-mono"
                      style="background:var(--bg-input);border-color:var(--border);color:var(--text-muted)">admin123</code>
            </p>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
