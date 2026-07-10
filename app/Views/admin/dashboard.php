<?php
// Rôles et permissions de l'administrateur connecté
$isAdmin = isset($currentAdmin) && $currentAdmin['role'] === 'admin';
$viewClients = $isAdmin || (isset($currentAdmin) && (bool)$currentAdmin['perm_view_clients']);
$viewBalances = $isAdmin || (isset($currentAdmin) && (bool)$currentAdmin['perm_view_balances']);
$viewTransactions = $isAdmin || (isset($currentAdmin) && (bool)$currentAdmin['perm_view_transactions']);
$manageClients = $isAdmin || (isset($currentAdmin) && (bool)$currentAdmin['perm_manage_clients']);
$canRecharge = $isAdmin || (isset($currentAdmin) && (bool)$currentAdmin['perm_can_recharge']);
?>

<script src="https://unpkg.com/lucide@latest"></script>
<style>
    .tab-content { animation: fadeIn 0.3s ease-in-out; }
    .animate-fade-in { animation: fadeIn 0.2s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="max-w-7xl mx-auto space-y-6 pb-12">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Vue d'ensemble du Réseau</h1>
            <p class="text-sm text-slate-500 mt-1">Supervision de la distribution d'eau RFID</p>
        </div>
        <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2">
            <div class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
            </div>
            <div class="text-xs">
                <span class="font-bold text-slate-700 block"><?= $stats['online_bornes'] ?? 0 ?> / <?= $stats['total_bornes'] ?? 0 ?> Bornes</span>
                <span class="text-slate-500">En ligne aujourd'hui</span>
            </div>
        </div>
    </div>

    <nav class="flex space-x-2 border-b border-slate-200 pb-px overflow-x-auto">
        <button onclick="switchTab('supervision')" id="tab-btn-supervision" class="tab-btn px-4 py-2.5 text-sm font-bold rounded-t-lg border-b-2 border-blue-600 text-blue-600 bg-blue-50/50 flex items-center transition-colors">
            <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2"></i> Supervision
        </button>
        <?php if ($isAdmin): ?>
            <button onclick="switchTab('config')" id="tab-btn-config" class="tab-btn px-4 py-2.5 text-sm font-semibold rounded-t-lg border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50 flex items-center transition-colors">
                <i data-lucide="sliders" class="w-4 h-4 mr-2"></i> Paramètres Système
            </button>
            <button onclick="switchTab('users')" id="tab-btn-users" class="tab-btn px-4 py-2.5 text-sm font-semibold rounded-t-lg border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50 flex items-center transition-colors">
                <i data-lucide="shield-check" class="w-4 h-4 mr-2"></i> Comptes & Accès
            </button>
        <?php endif; ?>
    </nav>

    <div id="tab-supervision" class="tab-content block space-y-6">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Volume Distribué</span>
                    <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                        <i data-lucide="droplet" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-extrabold text-slate-800"><?= number_format($stats['total_volume'] ?? 0, 1) ?></span>
                    <span class="text-xs font-semibold text-slate-500">L</span>
                </div>
            </div>

            <?php if ($viewBalances): ?>
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Crédits Débités</span>
                        <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <i data-lucide="coins" class="w-4 h-4"></i>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl font-extrabold text-slate-800"><?= number_format($stats['total_revenue'] ?? 0, 3) ?></span>
                        <span class="text-xs font-semibold text-slate-500">DT</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($viewClients): ?>
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Cartes Actives</span>
                        <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <i data-lucide="credit-card" class="w-4 h-4"></i>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl font-extrabold text-slate-800"><?= $stats['active_cards'] ?? 0 ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-white border <?= ($stats['active_alerts'] ?? 0) > 0 ? 'border-red-300 ring-2 ring-red-50' : 'border-slate-200' ?> rounded-2xl p-5 shadow-sm transition-all">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Alertes Actives</span>
                    <div class="w-8 h-8 rounded-full <?= ($stats['active_alerts'] ?? 0) > 0 ? 'bg-red-100 text-red-600 animate-pulse' : 'bg-slate-100 text-slate-400' ?> flex items-center justify-center">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-extrabold <?= ($stats['active_alerts'] ?? 0) > 0 ? 'text-red-600' : 'text-slate-800' ?>"><?= $stats['active_alerts'] ?? 0 ?></span>
                    <span class="text-xs font-semibold text-slate-500">incidents</span>
                </div>
            </div>
        </div>

        <!-- Section Bornes de Distribution (IoT) -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden p-6 mb-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="wifi" class="w-5 h-5 text-blue-500"></i> Supervision des Bornes IoT
                </h2>
                <span class="text-xs text-slate-500 font-medium">Mise à jour en temps réel</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="bornes-grid-container">
                <div class="col-span-full text-center py-6 text-slate-500 text-xs">
                    Chargement des bornes en cours...
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            
            <?php if ($viewTransactions): ?>
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm xl:col-span-2 flex flex-col overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
                        <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <i data-lucide="history" class="w-4 h-4 text-blue-500"></i> Dernières Consommations
                        </h2>
                    </div>
                    <div class="overflow-x-auto flex-1 p-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                    <th class="px-4 py-3">Agriculteur</th>
                                    <th class="px-4 py-3">Borne</th>
                                    <th class="px-4 py-3 text-right">Volume</th>
                                    <?php if ($viewBalances): ?>
                                        <th class="px-4 py-3 text-right">Montant</th>
                                    <?php endif; ?>
                                    <th class="px-4 py-3 text-center">Statut</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-50">
                                <?php if (empty($recentTransactions)): ?>
                                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Aucune transaction enregistrée.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentTransactions as $tx): ?>
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="px-4 py-2.5">
                                                <span class="font-bold text-slate-800 block"><?= htmlspecialchars($tx['farmer_prenom'] . ' ' . $tx['farmer_nom']) ?></span>
                                                <span class="text-[11px] text-slate-500 font-mono"><?= htmlspecialchars($tx['carte_rfid_uid']) ?></span>
                                            </td>
                                            <td class="px-4 py-2.5 text-slate-600 text-[11px]"><?= htmlspecialchars($tx['borne_nom']) ?></td>
                                            <td class="px-4 py-2.5 text-right font-semibold text-slate-700"><?= number_format($tx['volume_distribue'], 1) ?> L</td>
                                            <?php if ($viewBalances): ?>
                                                <td class="px-4 py-2.5 text-right font-bold text-blue-600"><?= number_format($tx['montant_debite'], 3) ?> DT</td>
                                            <?php endif; ?>
                                            <td class="px-4 py-2.5 text-center">
                                                <?php if ($tx['statut'] === 'reussie'): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">Réussie</span>
                                                <?php elseif ($tx['statut'] === 'interrompue_erreur'): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700">Interrompue</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">Annulée</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm flex flex-col <?= $viewTransactions ? '' : 'xl:col-span-3' ?>">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
                    <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i data-lucide="bell-ring" class="w-4 h-4 text-red-500"></i> Journal des Alertes
                    </h2>
                </div>
                <div class="p-3 space-y-2 max-h-[350px] overflow-y-auto">
                    <?php if (empty($alerts)): ?>
                        <div class="text-center py-8">
                            <div class="inline-flex w-10 h-10 rounded-full bg-slate-50 text-slate-400 items-center justify-center mb-2 border border-slate-100">
                                <i data-lucide="check-circle" class="w-5 h-5"></i>
                            </div>
                            <p class="text-xs font-medium text-slate-500">Aucun incident technique.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): ?>
                            <div class="p-3 rounded-xl border transition-all <?= $alert['resolu'] ? 'bg-slate-50 border-slate-100 text-slate-500' : 'bg-red-50 border-red-200 text-slate-800 shadow-sm' ?>">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 <?= $alert['resolu'] ? 'text-slate-500' : 'text-red-600' ?>">
                                        <i data-lucide="alert-circle" class="w-3 h-3"></i> <?= str_replace('_', ' ', $alert['type_alerte']) ?>
                                    </span>
                                    <span class="text-[9px] font-mono <?= $alert['resolu'] ? 'text-slate-400' : 'text-red-400' ?>">
                                        <?= date('d/m H:i', strtotime($alert['date_heure'])) ?>
                                    </span>
                                </div>
                                <p class="text-[11px] leading-relaxed mb-2.5 <?= $alert['resolu'] ? 'text-slate-500' : 'text-slate-700 font-medium' ?>"><?= htmlspecialchars($alert['description']) ?></p>
                                <div class="flex items-center justify-between text-[10px] border-t <?= $alert['resolu'] ? 'border-slate-200' : 'border-red-200' ?> pt-2">
                                    <span class="font-bold font-mono truncate mr-2 <?= $alert['resolu'] ? 'text-slate-400' : 'text-slate-600' ?>"><?= htmlspecialchars($alert['borne_nom']) ?></span>
                                    <?php if ($alert['resolu']): ?>
                                        <span class="text-emerald-600 font-bold flex items-center gap-1 shrink-0 bg-emerald-50 px-1.5 py-0.5 rounded">
                                            <i data-lucide="check" class="w-3 h-3"></i> Résolue
                                        </span>
                                    <?php else: ?>
                                        <a href="index.php?route=alertes/resolve&id=<?= (int)$alert['id'] ?>" class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded font-bold bg-white text-emerald-600 border border-emerald-200 hover:bg-emerald-50 shadow-sm transition-all">
                                            <i data-lucide="check-circle" class="w-3 h-3"></i> Marquer Résolue
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section Logs MQTT & Historique Pompe -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Journal MQTT -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm flex flex-col overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
                    <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i data-lucide="terminal" class="w-4 h-4 text-slate-600"></i> Trafic MQTT en temps réel
                    </h2>
                </div>
                <div class="p-4 overflow-y-auto max-h-[350px] font-mono text-xs space-y-2 bg-slate-950 text-slate-300" id="mqtt-logs-container">
                    <div class="text-center text-slate-500 py-6">Aucun message MQTT.</div>
                </div>
            </div>

            <!-- Historique Relais Pompe -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm flex flex-col overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
                    <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i data-lucide="history" class="w-4 h-4 text-emerald-500"></i> Historique Relais & Pompe
                    </h2>
                </div>
                <div class="p-1 overflow-x-auto flex-1 max-h-[350px]">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                <th class="px-4 py-3">Station</th>
                                <th class="px-4 py-3">Action</th>
                                <th class="px-4 py-3">Origine</th>
                                <th class="px-4 py-3 text-right">Date/Heure</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs divide-y divide-slate-50" id="pump-logs-container">
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Aucun log de pompe.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($viewClients): ?>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-5 py-4 border-b border-slate-100 bg-slate-50">
                    <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i data-lucide="users" class="w-4 h-4 text-blue-500"></i> Agriculteurs & Cartes RFID
                    </h2>
                    <?php if ($manageClients): ?>
                        <button onclick="openClientModal()" class="px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-all flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Ajouter Client
                        </button>
                    <?php endif; ?>
                </div>

                <div class="overflow-x-auto p-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                <th class="px-4 py-3">Client</th>
                                <th class="px-4 py-3">Carte & PIN</th>
                                <?php if ($viewBalances): ?>
                                    <th class="px-4 py-3 text-right">Solde</th>
                                <?php endif; ?>
                                <th class="px-4 py-3 text-right">Total Distribué</th>
                                <th class="px-4 py-3 text-center">Statut</th>
                                <?php if ($canRecharge || $manageClients): ?>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-50">
                            <?php if (empty($cards)): ?>
                                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Aucun client enregistré.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cards as $c): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-4 py-2.5">
                                            <span class="font-bold text-slate-800 block"><?= htmlspecialchars($c['farmer_prenom'] . ' ' . $c['farmer_nom']) ?></span>
                                            <span class="text-[11px] text-slate-500"><?= htmlspecialchars($c['farmer_telephone']) ?></span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="font-mono text-[11px] text-slate-600 font-bold block"><?= htmlspecialchars($c['uid']) ?></span>
                                            <span class="text-[9px] text-slate-400 font-medium"><?= !empty($c['code_pin']) ? (strlen($c['code_pin']) === 4 ? 'PIN: '.htmlspecialchars($c['code_pin']) : 'PIN Sécurisé') : 'Sans PIN' ?></span>
                                        </td>
                                        <?php if ($viewBalances): ?>
                                            <td class="px-4 py-2.5 text-right font-bold text-emerald-600"><?= number_format($c['solde'], 3) ?> DT</td>
                                        <?php endif; ?>
                                        <td class="px-4 py-2.5 text-right font-semibold text-slate-600"><?= number_format($c['total_distribue'], 1) ?> L</td>
                                        <td class="px-4 py-2.5 text-center">
                                            <?php if ($c['statut'] === 'active'): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">Active</span>
                                            <?php elseif ($c['statut'] === 'suspendue'): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700">Suspendue</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700">Perdue</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($canRecharge || $manageClients): ?>
                                            <td class="px-4 py-2.5 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    <?php if ($canRecharge): ?>
                                                        <button onclick="openRechargeModal('<?= $c['uid'] ?>', '<?= htmlspecialchars($c['farmer_prenom'] . ' ' . $c['farmer_nom']) ?>', '<?= $c['solde'] ?>')" class="p-1.5 bg-white border border-slate-200 hover:bg-emerald-50 text-emerald-600 hover:border-emerald-300 rounded-lg transition-all" title="Recharger">
                                                            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($manageClients): ?>
                                                        <?php if ($c['statut'] === 'active'): ?>
                                                            <a href="index.php?route=cards/status&uid=<?= $c['uid'] ?>&status=suspendue" class="p-1.5 bg-white border border-slate-200 hover:bg-amber-50 text-amber-600 hover:border-amber-300 rounded-lg transition-all" title="Suspendre"><i data-lucide="slash" class="w-3.5 h-3.5"></i></a>
                                                        <?php else: ?>
                                                            <a href="index.php?route=cards/status&uid=<?= $c['uid'] ?>&status=active" class="p-1.5 bg-white border border-slate-200 hover:bg-emerald-50 text-emerald-600 hover:border-emerald-300 rounded-lg transition-all" title="Activer"><i data-lucide="check" class="w-3.5 h-3.5"></i></a>
                                                        <?php endif; ?>
                                                        <button onclick="openEditClientModal(<?= $c['agriculteur_id'] ?>, '<?= htmlspecialchars($c['farmer_nom']) ?>', '<?= htmlspecialchars($c['farmer_prenom']) ?>', '<?= htmlspecialchars($c['farmer_telephone']) ?>', '<?= htmlspecialchars($c['farmer_email']) ?>', '<?= htmlspecialchars($c['statut']) ?>', '<?= (strlen($c['code_pin']) === 4) ? htmlspecialchars($c['code_pin']) : '' ?>')" class="p-1.5 bg-white border border-slate-200 hover:bg-blue-50 text-blue-600 hover:border-blue-300 rounded-lg transition-all" title="Modifier"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i></button>
                                                        <a href="index.php?route=farmers/delete&id=<?= $c['agriculteur_id'] ?>" onclick="return confirm('Supprimer cet agriculteur ?')" class="p-1.5 bg-white border border-slate-200 hover:bg-red-50 text-red-600 hover:border-red-300 rounded-lg transition-all" title="Supprimer"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($isAdmin): ?>
        <div id="tab-config" class="tab-content hidden">
            <div class="max-w-xl">
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <h2 class="text-base font-bold text-slate-800 mb-6 flex items-center gap-2 border-b border-slate-100 pb-3">
                        <i data-lucide="sliders" class="w-5 h-5 text-blue-500"></i> Configuration Globale
                    </h2>
                    <form action="index.php?route=settings/save" method="POST" class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Tarif de l'eau (DT / Litre)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 font-bold text-sm">DT</span>
                                <input type="text" name="price_per_litre" required value="<?= htmlspecialchars(number_format($configs['price_per_litre'] ?? 0.200, 3)) ?>" class="block w-full pl-10 pr-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-100 text-slate-800 text-sm font-semibold rounded-lg outline-none transition-all">
                            </div>
                            <p class="text-[10px] text-slate-500 mt-1.5 flex items-center gap-1"><i data-lucide="info" class="w-3 h-3"></i> Ex: 0.200 DT/Litre = 200 DT pour 1 m³.</p>
                        </div>
                        <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-sm transition-all shadow-sm">
                            Enregistrer les modifications
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <div id="tab-users" class="tab-content hidden">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-5 py-4 border-b border-slate-100 bg-slate-50">
                    <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-4 h-4 text-blue-500"></i> Comptes Administrateurs
                    </h2>
                    <button onclick="openAdminModal()" class="px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-all flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Nouveau Compte
                    </button>
                </div>
                <div class="overflow-x-auto p-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                <th class="px-4 py-3">Personnel</th>
                                <th class="px-4 py-3">Email & Rôle</th>
                                <th class="px-4 py-3 text-center">Statut</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-50">
                            <?php if (empty($admins)): ?>
                                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Aucun compte trouvé.</td></tr>
                            <?php else: ?>
                                <?php foreach ($admins as $ad): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-4 py-2.5">
                                            <span class="font-bold text-slate-800 block"><?= htmlspecialchars($ad['prenom'] . ' ' . $ad['nom']) ?></span>
                                            <span class="text-[10px] text-slate-500">Ajouté le <?= date('d/m/Y', strtotime($ad['created_at'])) ?></span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="text-slate-600 font-medium text-xs block"><?= htmlspecialchars($ad['email']) ?></span>
                                            <div class="mt-0.5"><span class="px-1.5 py-0.5 text-[9px] font-bold bg-slate-100 text-slate-600 rounded uppercase border border-slate-200"><?= htmlspecialchars($ad['role']) ?></span></div>
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <?php if ($ad['statut'] === 'actif'): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">Actif</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700">Suspendu</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <button onclick="openAdminModal(<?= $ad['id'] ?>, '<?= htmlspecialchars($ad['nom']) ?>', '<?= htmlspecialchars($ad['prenom']) ?>', '<?= htmlspecialchars($ad['email']) ?>', '<?= htmlspecialchars($ad['statut']) ?>', '<?= htmlspecialchars($ad['role']) ?>', <?= $ad['perm_view_clients'] ?>, <?= $ad['perm_view_balances'] ?>, <?= $ad['perm_view_transactions'] ?>, <?= $ad['perm_manage_clients'] ?>, <?= $ad['perm_can_recharge'] ?>)" class="p-1.5 bg-white border border-slate-200 hover:bg-blue-50 text-blue-600 hover:border-blue-300 rounded-lg transition-all shadow-sm" title="Modifier"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i></button>
                                            <a href="index.php?route=admins/delete&id=<?= $ad['id'] ?>" onclick="return confirm('Supprimer ce compte ?')" class="p-1.5 bg-white border border-slate-200 hover:bg-red-50 text-red-600 hover:border-red-300 rounded-lg transition-all shadow-sm" title="Supprimer"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<div id="recharge-modal" class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm hidden">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-2xl p-6 shadow-2xl animate-fade-in mx-4">
        <div class="flex items-center justify-between mb-5 border-b border-slate-100 pb-3">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="coins" class="w-5 h-5 text-emerald-500"></i> Recharger le solde
            </h3>
            <button onclick="closeRechargeModal()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <form action="index.php?route=cards/recharge" method="POST" class="space-y-5">
            <input type="hidden" name="uid" id="recharge-uid">
            <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 mb-4">
                <p class="text-xs text-slate-500 mb-1">Agriculteur : <strong id="recharge-farmer-name" class="text-slate-800">--</strong></p>
                <p class="text-xs text-slate-500">Solde actuel : <strong id="recharge-current-balance" class="text-emerald-600 font-bold">0.000 DT</strong></p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Montant à ajouter (DT)</label>
                <input type="text" name="amount" id="recharge-amount" required placeholder="0.000" class="block w-full px-3 py-2 bg-white border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-slate-800 text-sm font-bold rounded-lg outline-none transition-all">
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeRechargeModal()" class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition-all text-center">Annuler</button>
                <button type="submit" class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs transition-all shadow-sm">Valider</button>
            </div>
        </form>
    </div>
</div>

<div id="client-modal" class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm hidden py-6">
    <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl p-6 shadow-2xl animate-fade-in mx-4 max-h-full flex flex-col">
        <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3 shrink-0">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="user-plus" class="w-5 h-5 text-blue-500"></i> Nouveau client
            </h3>
            <button onclick="closeClientModal()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <form id="create-client-form" action="index.php?route=clients/store" method="POST" onsubmit="return submitAjaxClientForm(event, this, 'create');" class="space-y-4 overflow-y-auto pr-1 flex-1">
            <div id="client-modal-alert" class="hidden p-3 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2"></div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Prénom</label>
                    <input type="text" name="prenom" id="client-prenom" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Nom</label>
                    <input type="text" name="nom" id="client-nom" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Téléphone</label>
                    <input type="text" name="telephone" id="client-phone" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Email</label>
                    <input type="email" name="email" id="client-email" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                </div>
            </div>
            
            <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 mt-2">
                <h4 class="text-[10px] font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5"><i data-lucide="credit-card" class="w-3.5 h-3.5"></i> Carte RFID</h4>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">UID (Requis)</label>
                        <input type="text" name="uid" id="client-uid" required placeholder="Ex: 04A32B..." class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded text-slate-800 text-xs font-mono focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Solde Initial</label>
                        <input type="text" name="solde" id="client-solde" value="50.000" class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded text-slate-800 text-xs focus:border-blue-500 outline-none">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Code PIN (4 chiffres)</label>
                        <input type="text" name="code_pin" id="client-pin" maxlength="4" placeholder="Optionnel" class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded text-slate-800 text-xs font-mono focus:border-blue-500 outline-none">
                    </div>
                </div>
            </div>
            
            <div class="flex gap-2 pt-4 shrink-0">
                <button type="button" onclick="closeClientModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition-all">Annuler</button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs transition-all shadow-sm">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div id="edit-client-modal" class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm hidden py-6">
    <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl p-6 shadow-2xl animate-fade-in mx-4 max-h-full flex flex-col">
        <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3 shrink-0">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="edit-3" class="w-5 h-5 text-blue-500"></i> Modifier le client
            </h3>
            <button onclick="closeEditClientModal()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <form id="edit-client-form" action="index.php?route=farmers/update" method="POST" onsubmit="return submitAjaxClientForm(event, this, 'edit');" class="space-y-4 overflow-y-auto pr-1 flex-1">
            <div id="edit-client-modal-alert" class="hidden p-3 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2"></div>
            <input type="hidden" name="id" id="edit-client-id">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Prénom</label>
                    <input type="text" name="prenom" id="edit-client-prenom" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Nom</label>
                    <input type="text" name="nom" id="edit-client-nom" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Téléphone</label>
                <input type="text" name="telephone" id="edit-client-phone" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Email</label>
                <input type="email" name="email" id="edit-client-email" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Statut</label>
                    <select name="statut" id="edit-client-status" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                        <option value="actif">Actif</option>
                        <option value="suspendu">Suspendu</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Nouveau PIN</label>
                    <input type="text" name="code_pin" id="edit-client-pin" maxlength="4" placeholder="(Inchangé si vide)" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm font-mono outline-none">
                </div>
            </div>
            <div class="flex gap-2 pt-4 shrink-0">
                <button type="button" onclick="closeEditClientModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition-all">Annuler</button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs transition-all shadow-sm">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<div id="admin-modal" class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm hidden py-6">
    <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl p-6 shadow-2xl animate-fade-in mx-4 max-h-full flex flex-col">
        <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3 shrink-0">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2" id="admin-modal-title">Nouveau compte</h3>
            <button onclick="closeAdminModal()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <form action="index.php?route=admins/store" method="POST" class="space-y-4 overflow-y-auto pr-1 flex-1">
            <input type="hidden" name="id" id="admin-id">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Prénom</label>
                    <input type="text" name="prenom" id="admin-prenom" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Nom</label>
                    <input type="text" name="nom" id="admin-nom" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                </div>
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Email</label>
                <input type="email" name="email" id="admin-email" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Mot de passe</label>
                <input type="password" name="password" id="admin-password" placeholder="••••••••" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                <span class="text-[9px] text-slate-500 mt-1 block" id="admin-password-help">Laisser vide pour ne pas modifier.</span>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Rôle</label>
                    <select name="role" id="admin-role" onchange="applyRolePreset(this.value)" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                        <option value="secretaire">Secrétaire</option>
                        <option value="assistant">Assistant technique</option>
                        <option value="admin">Administrateur</option>
                        <option value="custom">Personnalisé</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Statut</label>
                    <select name="statut" id="admin-status" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-500 rounded-lg text-slate-800 text-sm outline-none">
                        <option value="actif">Actif</option>
                        <option value="suspendu">Suspendu</option>
                    </select>
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 mt-2 space-y-2">
                <h4 class="text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-2">Permissions d'accès</h4>
                <label class="flex items-center justify-between text-xs font-semibold text-slate-700 cursor-pointer">Voir les clients <input type="checkbox" name="perm_view_clients" id="admin-perm-view-clients" class="w-3.5 h-3.5 text-blue-600 rounded"></label>
                <label class="flex items-center justify-between text-xs font-semibold text-slate-700 cursor-pointer">Voir les soldes <input type="checkbox" name="perm_view_balances" id="admin-perm-view-balances" class="w-3.5 h-3.5 text-blue-600 rounded"></label>
                <label class="flex items-center justify-between text-xs font-semibold text-slate-700 cursor-pointer">Voir les transactions <input type="checkbox" name="perm_view_transactions" id="admin-perm-view-transactions" class="w-3.5 h-3.5 text-blue-600 rounded"></label>
                <label class="flex items-center justify-between text-xs font-semibold text-slate-700 cursor-pointer">Gérer les clients <input type="checkbox" name="perm_manage_clients" id="admin-perm-manage-clients" class="w-3.5 h-3.5 text-blue-600 rounded"></label>
                <label class="flex items-center justify-between text-xs font-semibold text-slate-700 cursor-pointer">Recharger les cartes <input type="checkbox" name="perm_can_recharge" id="admin-perm-can-recharge" class="w-3.5 h-3.5 text-blue-600 rounded"></label>
            </div>

            <div class="flex gap-2 pt-4 shrink-0">
                <button type="button" onclick="closeAdminModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition-all">Annuler</button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs transition-all shadow-sm">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialiser les icônes
    lucide.createIcons();

    // Gestion des onglets
    function switchTab(tabId) {
        // Cacher tous les contenus
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
            el.classList.remove('block');
        });

        // Réinitialiser le style des boutons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.add('border-transparent', 'text-slate-500');
            btn.classList.remove('border-blue-600', 'text-blue-600', 'bg-blue-50/50');
        });

        // Afficher l'onglet actif
        const activeContent = document.getElementById('tab-' + tabId);
        if (activeContent) {
            activeContent.classList.remove('hidden');
            activeContent.classList.add('block');
        }

        // Mettre en surbrillance le bouton actif
        const activeBtn = document.getElementById('tab-btn-' + tabId);
        if (activeBtn) {
            activeBtn.classList.remove('border-transparent', 'text-slate-500');
            activeBtn.classList.add('border-blue-600', 'text-blue-600', 'bg-blue-50/50');
        }
    }

    // Gestion de l'ancre URL pour revenir au bon onglet
    window.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash.replace('#', '');
        if (hash === 'config' || hash === 'users' || hash === 'supervision') { switchTab(hash); } else { switchTab('supervision'); }
    });
    window.addEventListener('hashchange', () => {
        const hash = window.location.hash.replace('#', '');
        if (hash === 'config' || hash === 'users' || hash === 'supervision') { switchTab(hash); }
    });

    // Fonctions Modals
    function openRechargeModal(uid, name, balance) {
        document.getElementById('recharge-uid').value = uid;
        document.getElementById('recharge-farmer-name').textContent = name;
        document.getElementById('recharge-current-balance').textContent = parseFloat(balance).toFixed(3) + ' DT';
        document.getElementById('recharge-amount').value = '';
        document.getElementById('recharge-modal').classList.remove('hidden');
    }
    function closeRechargeModal() { document.getElementById('recharge-modal').classList.add('hidden'); }

    function openClientModal() {
        ['prenom', 'nom', 'phone', 'email', 'uid', 'pin'].forEach(id => {
            const el = document.getElementById('client-'+id);
            if (el) {
                el.value = '';
                el.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
            }
        });
        document.getElementById('client-solde').value = '50.000';
        const alertEl = document.getElementById('client-modal-alert');
        if (alertEl) { alertEl.classList.add('hidden'); alertEl.innerHTML = ''; }
        document.getElementById('client-modal').classList.remove('hidden');
    }
    function closeClientModal() { document.getElementById('client-modal').classList.add('hidden'); }

    function openEditClientModal(id, nom, prenom, telephone, email, status, pin = '') {
        document.getElementById('edit-client-id').value = id;
        document.getElementById('edit-client-prenom').value = prenom;
        document.getElementById('edit-client-nom').value = nom;
        document.getElementById('edit-client-phone').value = telephone;
        document.getElementById('edit-client-email').value = email;
        document.getElementById('edit-client-status').value = status;
        const pinInput = document.getElementById('edit-client-pin');
        if (pinInput) {
            pinInput.value = pin;
            pinInput.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
        }
        const alertEl = document.getElementById('edit-client-modal-alert');
        if (alertEl) { alertEl.classList.add('hidden'); alertEl.innerHTML = ''; }
        document.getElementById('edit-client-modal').classList.remove('hidden');
    }
    function closeEditClientModal() { document.getElementById('edit-client-modal').classList.add('hidden'); }

    function submitAjaxClientForm(e, formEl, type) {
        e.preventDefault();
        const alertEl = document.getElementById(type === 'create' ? 'client-modal-alert' : 'edit-client-modal-alert');
        const pinInput = document.getElementById(type === 'create' ? 'client-pin' : 'edit-client-pin');
        
        if (alertEl) {
            alertEl.classList.add('hidden');
            alertEl.innerHTML = '';
        }
        if (pinInput) {
            pinInput.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
        }

        const formData = new FormData(formEl);
        const actionUrl = formEl.getAttribute('action') + '&ajax=1';

        fetch(actionUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async r => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch(err) {
                console.error("Réponse brute du serveur:", text);
                return { status: 'error', message: "Erreur serveur : " + text.replace(/<[^>]*>?/gm, '').substring(0, 150) };
            }
        })
        .then(data => {
            if (data.status === 'success') {
                if (type === 'create') closeClientModal();
                else closeEditClientModal();
                window.location.hash = 'supervision';
                window.location.reload();
            } else {
                if (alertEl) {
                    alertEl.innerHTML = `<i data-lucide="alert-circle" class="w-4 h-4 text-red-500 shrink-0"></i> <span>${data.message || 'Erreur lors de la sauvegarde.'}</span>`;
                    alertEl.classList.remove('hidden');
                    if (window.lucide) { try { lucide.createIcons(); } catch(err){} }
                }
                if (pinInput && data.message && data.message.toLowerCase().includes('pin')) {
                    pinInput.focus();
                    pinInput.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                }
            }
        })
        .catch(err => {
            console.error("Fetch Exception:", err);
            if (alertEl) {
                alertEl.innerHTML = `<i data-lucide="alert-circle" class="w-4 h-4 text-red-500 shrink-0"></i> <span>Erreur système : ${err.message}</span>`;
                alertEl.classList.remove('hidden');
                if (window.lucide) { try { lucide.createIcons(); } catch(err){} }
            }
        });

        return false;
    }

    function applyRolePreset(role) {
        const presets = {
            admin:      { vC:true, vB:true, vT:true, mC:true, cR:true },
            secretaire: { vC:true, vB:true, vT:true, mC:false, cR:false },
            assistant:  { vC:false, vB:false, vT:true, mC:false, cR:false }
        };
        const p = presets[role];
        if (p) {
            document.getElementById('admin-perm-view-clients').checked = p.vC;
            document.getElementById('admin-perm-view-balances').checked = p.vB;
            document.getElementById('admin-perm-view-transactions').checked = p.vT;
            document.getElementById('admin-perm-manage-clients').checked = p.mC;
            document.getElementById('admin-perm-can-recharge').checked = p.cR;
        }
    }

    function openAdminModal(id=null, nom='', prenom='', email='', statut='actif', role='secretaire', vc=1, vb=1, vt=1, mc=0, cr=0) {
        document.getElementById('admin-id').value = id;
        document.getElementById('admin-prenom').value = prenom;
        document.getElementById('admin-nom').value = nom;
        document.getElementById('admin-email').value = email;
        document.getElementById('admin-status').value = statut;
        document.getElementById('admin-role').value = role;
        document.getElementById('admin-perm-view-clients').checked = parseInt(vc) === 1;
        document.getElementById('admin-perm-view-balances').checked = parseInt(vb) === 1;
        document.getElementById('admin-perm-view-transactions').checked = parseInt(vt) === 1;
        document.getElementById('admin-perm-manage-clients').checked = parseInt(mc) === 1;
        document.getElementById('admin-perm-can-recharge').checked = parseInt(cr) === 1;

        if(id) {
            document.getElementById('admin-modal-title').innerHTML = '<i data-lucide="edit-3" class="w-5 h-5 text-blue-500 inline"></i> Modifier compte';
            document.getElementById('admin-password').required = false;
            document.getElementById('admin-password-help').classList.remove('hidden');
        } else {
            document.getElementById('admin-modal-title').innerHTML = '<i data-lucide="user-plus" class="w-5 h-5 text-blue-500 inline"></i> Nouveau compte';
            document.getElementById('admin-password').required = true;
            document.getElementById('admin-password-help').classList.add('hidden');
            applyRolePreset('secretaire');
        }
        document.getElementById('admin-modal').classList.remove('hidden');
        lucide.createIcons();
    }
    function closeAdminModal() { document.getElementById('admin-modal').classList.add('hidden'); }

    // =========================================================================
    // MQTT & REAL-TIME SUPERVISION
    // =========================================================================

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function sendBorneCommand(stationId, commandType, additionalPayload = {}) {
        if (!confirm(`Envoyer la commande '${commandType}' à la borne #${stationId} ?`)) {
            return;
        }
        
        const params = {
            borne_id: stationId,
            type_commande: commandType
        };
        
        if (additionalPayload.volume) {
            params.volume = additionalPayload.volume;
        }

        fetch('index.php?route=api/bornes/commande', {
            method: 'POST',
            body: JSON.stringify(params),
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                alert(`Commande '${commandType}' enregistrée ! (ID: ${data.commande_id})`);
                pollStatusLive();
            } else {
                alert(`Erreur : ${data.message}`);
            }
        })
        .catch(err => {
            console.error("Command Error:", err);
            alert("Erreur de communication avec le serveur.");
        });
    }

    function promptVolumeCommand(stationId) {
        const vol = prompt("Entrez la quantité d'eau à distribuer (en litres) :");
        if (vol === null) return;
        const parsed = parseFloat(vol);
        if (isNaN(parsed) || parsed <= 0) {
            alert("Volume invalide.");
            return;
        }
        sendBorneCommand(stationId, 'set_volume', { volume: parsed });
    }

    function updateBornesGrid(bornes) {
        const container = document.getElementById('bornes-grid-container');
        if (!container) return;
        
        if (!bornes || bornes.length === 0) {
            container.innerHTML = `<div class="col-span-full text-center py-6 text-slate-400 text-xs">Aucune borne configurée.</div>`;
            return;
        }

        let html = '';
        bornes.forEach(b => {
            const isOnline = b.mqtt_connecte || b.statut_connexion === 'en ligne';
            const valveOpen = b.etat_vanne === 'ouverte';
            
            const badgeOnline = isOnline 
                ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>En ligne</span>` 
                : `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Hors ligne</span>`;
                
            const badgeValve = valveOpen
                ? `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 uppercase">Ouverte (ON)</span>`
                : `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 uppercase">Fermée (OFF)</span>`;

            const controlBtn = valveOpen
                ? `<button onclick="sendBorneCommand(${b.id}, 'stop_pompe')" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-xs transition-all shadow-sm">Arrêter</button>`
                : `<button onclick="sendBorneCommand(${b.id}, 'start_pompe')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs transition-all shadow-sm">Démarrer</button>`;

            html += `
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 hover:shadow-md transition-all flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <h3 class="text-sm font-bold text-slate-800">${escapeHtml(b.nom)}</h3>
                        ${badgeOnline}
                    </div>
                    <p class="text-xs text-slate-500 mb-3 flex items-center gap-1">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5"></i> ${escapeHtml(b.localisation)}
                    </p>
                    
                    <div class="bg-white border border-slate-150 rounded-xl p-3 space-y-2 mb-4 text-xs shadow-sm">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-1.5">
                          <span class="text-slate-500 font-medium">Électrovanne</span>
                          ${badgeValve}
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-100 pb-1.5">
                          <span class="text-slate-500 font-medium">Débit Instantané</span>
                          <span class="font-bold text-slate-700">${b.debit_instantane !== null ? b.debit_instantane.toFixed(1) : '0.0'} L/min</span>
                        </div>
                        <div class="flex justify-between items-center">
                          <span class="text-slate-500 font-medium">Dernier Signe de Vie</span>
                          <span class="font-mono text-[9px] text-slate-400">${b.derniere_telemerie_mqtt ? b.derniere_telemerie_mqtt : (b.dernier_ping ? b.dernier_ping : 'Aucun')}</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-1.5 border-t border-slate-150/50 pt-3 mt-1">
                    ${controlBtn}
                    <button onclick="promptVolumeCommand(${b.id})" class="p-1.5 bg-white border border-slate-200 hover:bg-blue-50 text-blue-600 rounded-lg transition-all" title="Définir volume"><i data-lucide="droplet" class="w-3.5 h-3.5"></i></button>
                    <button onclick="sendBorneCommand(${b.id}, 'restart_esp')" class="p-1.5 bg-white border border-slate-200 hover:bg-amber-50 text-amber-600 rounded-lg transition-all" title="Redémarrer ESP32"><i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i></button>
                </div>
            </div>
            `;
        });
        container.innerHTML = html;
    }

    function updateMqttLogs(logs) {
        const container = document.getElementById('mqtt-logs-container');
        if (!container) return;
        
        if (!logs || logs.length === 0) {
            container.innerHTML = `<div class="text-center text-slate-500 py-6">Aucun message MQTT.</div>`;
            return;
        }

        let html = '';
        logs.forEach(l => {
            const dirColor = (l.direction === 'in') ? 'text-emerald-400' : 'text-blue-400';
            const dirSymbol = (l.direction === 'in') ? '◀ IN' : '▶ OUT';
            html += `
            <div class="py-1 border-b border-slate-900 flex justify-between gap-4">
                <span class="shrink-0 font-mono text-[10px] text-slate-500">${l.created_at}</span>
                <span class="shrink-0 font-bold ${dirColor} text-[10px]">${dirSymbol}</span>
                <span class="font-bold text-slate-200 truncate flex-1 text-left" title="${escapeHtml(l.topic)}">${escapeHtml(l.topic)}</span>
                <span class="text-slate-400 max-w-[200px] truncate font-mono text-[10px]" title="${escapeHtml(l.payload)}">${escapeHtml(l.payload)}</span>
            </div>
            `;
        });
        container.innerHTML = html;
    }

    function updatePumpLogs(logs) {
        const container = document.getElementById('pump-logs-container');
        if (!container) return;
        
        if (!logs || logs.length === 0) {
            container.innerHTML = `<tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Aucun log de pompe.</td></tr>`;
            return;
        }

        let html = '';
        logs.forEach(l => {
            const actionBadge = (l.action === 'ON')
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700">MARCHE</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">ARRÊT</span>`;
            
            html += `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-4 py-2 text-slate-800 font-bold">${escapeHtml(l.borne_nom || 'Borne #' + l.station_id)}</td>
                <td class="px-4 py-2">${actionBadge}</td>
                <td class="px-4 py-2 font-semibold text-slate-500 uppercase text-[10px]">${escapeHtml(l.triggered_by)}</td>
                <td class="px-4 py-2 text-right text-slate-500">${l.created_at}</td>
            </tr>
            `;
        });
        container.innerHTML = html;
    }

    function pollStatusLive() {
        fetch('index.php?route=api/bornes/status-live')
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                updateBornesGrid(data.bornes);
                updateMqttLogs(data.mqtt_messages);
                updatePumpLogs(data.pump_logs);
                
                // Mettre à jour également les compteurs généraux si besoin
                if (data.bornes) {
                    const onlineCount = data.bornes.filter(b => b.mqtt_connecte || b.statut_connexion === 'en ligne').length;
                    const statsText = document.querySelector('.flex.items-center.gap-3.bg-slate-50 .text-xs .font-bold');
                    if (statsText) {
                        statsText.textContent = `${onlineCount} / ${data.bornes.length} Bornes`;
                    }
                }
                
                // Recréer les icônes Lucide nouvellement injectées
                if (window.lucide) {
                    try { lucide.createIcons(); } catch(e){}
                }
            }
        })
        .catch(err => console.error("Supervision Poll Error:", err));
    }

    // Lancer le polling périodique toutes les 3 secondes
    setInterval(pollStatusLive, 3000);
    // Charger immédiatement au démarrage
    window.addEventListener('DOMContentLoaded', pollStatusLive);
</script>