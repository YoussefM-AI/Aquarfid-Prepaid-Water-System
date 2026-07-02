<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur <?= $code ?> - Système RFID Eau</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full bg-slate-900 text-white flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center bg-slate-800 border border-slate-700 rounded-2xl p-8 shadow-2xl">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-500/10 text-red-500 mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>
        <h1 class="text-6xl font-extrabold text-slate-100 tracking-tight mb-2"><?= $code ?></h1>
        <h2 class="text-xl font-semibold text-slate-300 mb-4"><?= htmlspecialchars($message) ?></h2>
        <p class="text-slate-400 mb-8">Désolé, l'action demandée n'a pas pu être effectuée ou la ressource est introuvable.</p>
        <a href="index.php?route=dashboard" class="inline-flex items-center justify-center w-full px-5 py-3 text-sm font-medium text-white bg-blue-600 hover:bg-blue-500 transition-colors rounded-xl shadow-lg shadow-blue-600/20">
            Retour au Tableau de Bord
        </a>
    </div>
</body>
</html>
