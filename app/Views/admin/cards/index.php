<!-- Gestion des cartes RFID -->

<div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">Gestion des Cartes RFID</h1>
        <p class="text-xs text-slate-400 mt-1">Associez de nouvelles cartes, suspendez des cartes ou rechargez les soldes prépayés.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Liste des cartes -->
    <div class="glass-card rounded-2xl p-6 lg:col-span-2">
        <h2 class="text-lg font-bold text-slate-200 mb-6 flex items-center gap-2">
            <i data-lucide="credit-card" class="w-5 h-5 text-blue-400"></i>
            Cartes Associées
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="pb-3">UID / Agriculteur</th>
                        <th class="pb-3 text-right">Solde</th>
                        <th class="pb-3 text-center">Dernière Utilisation</th>
                        <th class="pb-3 text-center">Statut</th>
                        <th class="pb-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50 text-sm">
                    <?php if (empty($cards)): ?>
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-500">Aucune carte RFID enregistrée.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cards as $card): ?>
                            <tr class="hover:bg-slate-800/10 transition-colors">
                                <td class="py-4">
                                    <span class="font-mono font-bold text-slate-200 block text-xs tracking-wider"><?= htmlspecialchars($card['uid']) ?></span>
                                    <span class="text-xs text-slate-500">Assoc. à : <?= htmlspecialchars($card['farmer_prenom'] . ' ' . $card['farmer_nom']) ?></span>
                                </td>
                                <td class="py-4 text-right font-extrabold text-cyan-400 font-mono">
                                    <?= number_format($card['solde'], 3) ?> DT
                                </td>
                                <td class="py-4 text-center text-slate-400 text-xs font-mono">
                                    <?= $card['derniere_utilisation'] ? date('d/m H:i', strtotime($card['derniere_utilisation'])) : 'Jamais' ?>
                                </td>
                                <td class="py-4 text-center">
                                    <?php if ($card['statut'] === 'active'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/10">
                                            Active
                                        </span>
                                    <?php elseif ($card['statut'] === 'suspendue'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-500/10 text-yellow-400 border border-yellow-500/10">
                                            Suspendue
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-500/10 text-red-400 border border-red-500/10">
                                            Perdue
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <!-- Actions de statut -->
                                        <?php if ($card['statut'] !== 'active'): ?>
                                            <a href="index.php?route=cards/status&uid=<?= urlencode($card['uid']) ?>&status=active" class="flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/10 transition-colors" title="Activer">
                                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($card['statut'] === 'active'): ?>
                                            <a href="index.php?route=cards/status&uid=<?= urlencode($card['uid']) ?>&status=suspendue" class="flex items-center justify-center w-7 h-7 rounded-lg bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-400 border border-yellow-500/10 transition-colors" title="Suspendre (Bloquer)">
                                                <i data-lucide="slash" class="w-3.5 h-3.5"></i>
                                            </a>
                                            <a href="index.php?route=cards/status&uid=<?= urlencode($card['uid']) ?>&status=perdue" class="flex items-center justify-center w-7 h-7 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/10 transition-colors" title="Déclarer Perdue">
                                                <i data-lucide="flag" class="w-3.5 h-3.5"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Bouton pré-remplir la recharge -->
                                        <button onclick="setRechargeUid('<?= htmlspecialchars($card['uid']) ?>')" class="flex items-center justify-center w-7 h-7 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/10 transition-colors" title="Recharger">
                                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Volet d'Actions (Associer et Recharger) -->
    <div class="space-y-8">
        <!-- 1. Formulaire de Recharge Rapide -->
        <div class="glass-card rounded-2xl p-6">
            <h3 class="text-md font-bold text-slate-200 mb-4 flex items-center gap-2">
                <i data-lucide="piggy-bank" class="w-5 h-5 text-cyan-400"></i>
                Recharge de Crédit
            </h3>
            <form action="index.php?route=cards/recharge" method="POST" class="space-y-4">
                <div>
                    <label for="recharge_uid" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">UID de la carte RFID</label>
                    <input type="text" name="uid" id="recharge_uid" required
                           placeholder="ex: 04A32B125C6D80"
                           class="block w-full px-4 py-2.5 bg-slate-900/60 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-slate-100 font-mono text-sm rounded-xl outline-none">
                </div>
                <div>
                    <label for="recharge_amount" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Montant à ajouter (DT)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-500 text-sm font-semibold">DT</span>
                        <input type="number" name="amount" id="recharge_amount" required min="1" step="0.5"
                               placeholder="ex: 50"
                               class="block w-full pl-4 pr-8 py-2.5 bg-slate-900/60 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-slate-100 font-mono text-sm rounded-xl outline-none">
                    </div>
                </div>
                <button type="submit" class="w-full py-2.5 px-4 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white font-semibold rounded-xl text-sm shadow-md shadow-cyan-600/10 hover:shadow-cyan-500/20 transition-all duration-300 flex items-center justify-center gap-2">
                    <i data-lucide="zap" class="w-4 h-4"></i>
                    <span>Recharger le compte</span>
                </button>
            </form>
        </div>

        <!-- 2. Formulaire d'Association de nouvelle Carte -->
        <div class="glass-card rounded-2xl p-6">
            <h3 class="text-md font-bold text-slate-200 mb-4 flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-5 h-5 text-indigo-400"></i>
                Associer une Carte
            </h3>
            <form action="index.php?route=cards" method="POST" class="space-y-4">
                <div>
                    <label for="card_uid" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">UID Physique de la Carte</label>
                    <input type="text" name="uid" id="card_uid" required
                           placeholder="ex: 04C92B548F"
                           class="block w-full px-4 py-2.5 bg-slate-900/60 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-slate-100 font-mono text-sm rounded-xl outline-none">
                </div>
                <div>
                    <label for="agriculteur_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Attribuer à l'agriculteur</label>
                    <select name="agriculteur_id" id="agriculteur_id" required
                            class="block w-full px-4 py-2.5 bg-slate-900/60 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-slate-200 text-sm rounded-xl outline-none">
                        <option value="">Sélectionner un agriculteur...</option>
                        <?php foreach ($farmers as $farmer): ?>
                            <option value="<?= $farmer['id'] ?>" <?= $farmer['statut'] !== 'actif' ? 'disabled class="text-slate-600"' : '' ?>>
                                <?= htmlspecialchars($farmer['prenom'] . ' ' . $farmer['nom']) ?> (<?= $farmer['statut'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="card_solde" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Crédit initial (DT)</label>
                    <input type="number" name="solde" id="card_solde" min="0" step="0.5" value="0"
                           class="block w-full px-4 py-2.5 bg-slate-900/60 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-slate-100 font-mono text-sm rounded-xl outline-none">
                </div>
                <button type="submit" class="w-full py-2.5 px-4 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-semibold rounded-xl text-sm shadow-md shadow-indigo-600/10 hover:shadow-indigo-500/20 transition-all duration-300 flex items-center justify-center gap-2">
                    <i data-lucide="link" class="w-4 h-4"></i>
                    <span>Associer la Carte</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    /**
     * Renseigne l'UID de la carte dans le formulaire de recharge rapide.
     */
    function setRechargeUid(uid) {
        document.getElementById('recharge_uid').value = uid;
        document.getElementById('recharge_amount').focus();
    }
</script>
