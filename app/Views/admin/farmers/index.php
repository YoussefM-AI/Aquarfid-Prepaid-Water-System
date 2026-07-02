<!-- Liste des agriculteurs -->

<div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">Gestion des Agriculteurs</h1>
        <p class="text-xs text-slate-400 mt-1">Créez, modifiez ou suspendez les profils des agriculteurs autorisés.</p>
    </div>
    <a href="index.php?route=farmers/create" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold shadow-lg shadow-blue-600/20 hover:shadow-blue-500/30 transition-all duration-300 gap-2">
        <i data-lucide="user-plus" class="w-4 h-4"></i>
        <span>Nouvel Agriculteur</span>
    </a>
</div>

<div class="glass-card rounded-2xl p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-800 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <th class="pb-4">Nom Complet</th>
                    <th class="pb-4">Téléphone</th>
                    <th class="pb-4">Adresse Email</th>
                    <th class="pb-4 text-center">Date d'inscription</th>
                    <th class="pb-4 text-center">Statut</th>
                    <th class="pb-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50 text-sm">
                <?php if (empty($farmers)): ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-500">Aucun agriculteur enregistré.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($farmers as $farmer): ?>
                        <tr class="hover:bg-slate-800/10 transition-colors">
                            <td class="py-4">
                                <span class="font-semibold text-slate-200 block"><?= htmlspecialchars($farmer['nom'] . ' ' . $farmer['prenom']) ?></span>
                                <span class="text-[10px] text-slate-500">ID Client: #<?= $farmer['id'] ?></span>
                            </td>
                            <td class="py-4 text-slate-300 font-mono text-xs">
                                <?= htmlspecialchars($farmer['telephone']) ?>
                            </td>
                            <td class="py-4 text-slate-400 text-xs">
                                <?= htmlspecialchars($farmer['email'] ?? 'Non fourni') ?>
                            </td>
                            <td class="py-4 text-center text-slate-400 text-xs font-mono">
                                <?= !empty($farmer['created_at']) ? date('d/m/Y', strtotime($farmer['created_at'])) : 'N/A' ?>
                            </td>
                            <td class="py-4 text-center">
                                <?php if ($farmer['statut'] === 'actif'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/10">
                                        Actif
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/10">
                                        Suspendu
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="index.php?route=farmers/edit&id=<?= $farmer['id'] ?>" class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors" title="Modifier">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </a>
                                    <a href="index.php?route=farmers/delete&id=<?= $farmer['id'] ?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet agriculteur ? Ses cartes associées seront également supprimées.')" class="flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 hover:border-red-500/30 transition-colors" title="Supprimer">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
