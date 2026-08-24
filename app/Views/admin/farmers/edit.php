<!-- Formulaire de modification d'agriculteur -->

<div class="max-w-2xl mx-auto">
    <div class="mb-8 flex items-center gap-4">
        <a href="index.php?route=farmers" class="flex items-center justify-center w-10 h-10 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-slate-200 transition-colors">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">Modifier l'Agriculteur</h1>
            <p class="text-xs text-slate-400 mt-1">Mettre à jour les informations du compte agriculteur.</p>
        </div>
    </div>

    <div class="glass-card rounded-2xl p-8 shadow-xl">
        <form action="index.php?route=farmers/update" method="POST" class="space-y-6">
            <input type="hidden" name="id" value="<?= (int) $farmer['id'] ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Nom -->
                <div>
                    <label for="nom" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Nom de famille *</label>
                    <input type="text" name="nom" id="nom" required
                           value="<?= htmlspecialchars($farmer['nom'] ?? '') ?>"
                           placeholder="ex: Ben Ali"
                           class="block w-full px-4 py-3 bg-slate-900/60 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-slate-100 placeholder-slate-500 text-sm rounded-xl transition-all duration-300 outline-none">
                </div>

                <!-- Prénom -->
                <div>
                    <label for="prenom" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Prénom *</label>
                    <input type="text" name="prenom" id="prenom" required
                           value="<?= htmlspecialchars($farmer['prenom'] ?? '') ?>"
                           placeholder="ex: Mohamed"
                           class="block w-full px-4 py-3 bg-slate-900/60 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-slate-100 placeholder-slate-500 text-sm rounded-xl transition-all duration-300 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Téléphone -->
                <div>
                    <label for="telephone" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Téléphone *</label>
                    <input type="text" name="telephone" id="telephone" required
                           value="<?= htmlspecialchars($farmer['telephone'] ?? '') ?>"
                           placeholder="ex: +21698765432"
                           class="block w-full px-4 py-3 bg-slate-900/60 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-slate-100 placeholder-slate-500 text-sm font-mono rounded-xl transition-all duration-300 outline-none">
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Adresse Email</label>
                    <input type="email" name="email" id="email"
                           value="<?= htmlspecialchars($farmer['email'] ?? '') ?>"
                           placeholder="ex: mohamed.benali@email.com"
                           class="block w-full px-4 py-3 bg-slate-900/60 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-slate-100 placeholder-slate-500 text-sm rounded-xl transition-all duration-300 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Statut -->
                <div>
                    <label for="statut" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Statut du compte</label>
                    <select name="statut" id="statut"
                            class="block w-full px-4 py-3 bg-slate-900/60 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-slate-100 text-sm rounded-xl transition-all duration-300 outline-none">
                        <option value="actif" <?= ($farmer['statut'] ?? '') === 'actif' ? 'selected' : '' ?>>Actif (Autorisé à distribuer)</option>
                        <option value="suspendu" <?= ($farmer['statut'] ?? '') === 'suspendu' ? 'selected' : '' ?>>Suspendu (Bloqué temporairement)</option>
                    </select>
                </div>

                <!-- UID Carte RFID -->
                <div>
                    <label for="uid" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">UID Carte RFID *</label>
                    <input type="text" name="uid" id="uid" required
                           value="<?= htmlspecialchars($farmer['uid'] ?? '') ?>"
                           placeholder="ex: 04A32B125C6D80"
                           class="block w-full px-4 py-3 bg-slate-900/60 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-slate-100 font-mono text-sm rounded-xl transition-all duration-300 outline-none">
                </div>
            </div>

            <!-- Boutons -->
            <div class="flex items-center justify-end gap-4 border-t border-slate-800/80 pt-6">
                <a href="index.php?route=farmers" class="px-5 py-3 text-sm font-semibold text-slate-400 hover:text-slate-200 transition-colors">
                    Annuler
                </a>
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-xl shadow-lg shadow-blue-600/20 hover:shadow-blue-500/30 transition-all duration-300 flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Mettre à jour</span>
                </button>
            </div>
        </form>
    </div>
</div>
