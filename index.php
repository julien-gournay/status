<?php

// Chargement des paramètres
$settings = require 'settings.php';
$sites = $settings['sites'];

// Fonction pour tester un site
function checkSiteStatus($url) {
    global $settings;
    $start = microtime(true);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => $settings['timeout'],
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $totalTime = round((microtime(true) - $start) * 1000);

    curl_close($curl);

    return [
        'status' => $httpCode,
        'time' => $totalTime
    ];
}

// Fonction pour mettre à jour l'historique
function updateSiteHistory($site, $status) {
    global $settings;
    $historyFile = 'sites_status.json';
    $history = [];
    
    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true);
    }
    
    $now = time();
    $siteData = [
        'last_check' => $now,
        'status' => $status['status'],
        'response_time' => $status['time']
    ];
    
    if (!isset($history['sites'][$site])) {
        $history['sites'][$site] = [
            'history' => [],
            'last_up' => null,
            'last_down' => null,
            'down_since' => null
        ];
    }
    
    // Mise à jour des timestamps
    if ($status['status'] == 200) {
        $history['sites'][$site]['last_up'] = $now;
        if ($history['sites'][$site]['down_since']) {
            $history['sites'][$site]['down_since'] = null;
        }
    } else {
        $history['sites'][$site]['last_down'] = $now;
        if (!$history['sites'][$site]['down_since']) {
            $history['sites'][$site]['down_since'] = $now;
        }
    }
    
    // Ajout à l'historique
    $history['sites'][$site]['history'][] = [
        'timestamp' => $now,
        'status' => $status['status'],
        'response_time' => $status['time']
    ];
    
    // Garder seulement les X derniers enregistrements
    if (count($history['sites'][$site]['history']) > $settings['history_limit']) {
        array_shift($history['sites'][$site]['history']);
    }
    
    $history['last_update'] = $now;
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
    
    return $history['sites'][$site];
}

// Fonction pour formater la durée
function formatDuration($seconds) {
    if (!$seconds) return null;
    
    $minutes = floor($seconds / 60);
    $hours = floor($minutes / 60);
    $days = floor($hours / 24);
    
    if ($days > 0) return $days . ' jour' . ($days > 1 ? 's' : '');
    if ($hours > 0) return $hours . ' heure' . ($hours > 1 ? 's' : '');
    if ($minutes > 0) return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    return $seconds . ' seconde' . ($seconds > 1 ? 's' : '');
}

// Fonction pour calculer le taux de disponibilité
function calculateUptime($history) {
    if (empty($history)) return 100;
    
    $totalChecks = count($history);
    $successfulChecks = 0;
    
    foreach ($history as $check) {
        if ($check['status'] == 200) {
            $successfulChecks++;
        }
    }
    
    return round(($successfulChecks / $totalChecks) * 100, 2);
}

// Fonction pour obtenir les pannes du mois
function getMonthlyDowntimes($history) {
    $downtimes = [];
    $currentMonth = date('m');
    $currentYear = date('Y');
    
    foreach ($history as $check) {
        $checkMonth = date('m', $check['timestamp']);
        $checkYear = date('Y', $check['timestamp']);
        
        if ($checkMonth == $currentMonth && $checkYear == $currentYear && $check['status'] != 200) {
            $downtimes[] = [
                'timestamp' => $check['timestamp'],
                'status' => $check['status'],
                'response_time' => $check['response_time']
            ];
        }
    }
    
    return $downtimes;
}

// Vérification si c'est une requête AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
    $results = [];
    foreach ($sites as $category => $categorySites) {
        $results[$category] = [];
        foreach ($categorySites as $site) {
            $status = checkSiteStatus($site);
            $history = updateSiteHistory($site, $status);
            $results[$category][$site] = [
                'status' => $status,
                'history' => $history,
                'uptime' => calculateUptime($history['history'])
            ];
        }
    }
    echo json_encode($results);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statut des sites - Julien Gournay</title>
    
    <!-- Flowbite CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Aceternity UI -->
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {"50":"#eff6ff","100":"#dbeafe","200":"#bfdbfe","300":"#93c5fd","400":"#60a5fa","500":"#3b82f6","600":"#2563eb","700":"#1d4ed8","800":"#1e40af","900":"#1e3a8a","950":"#172554"}
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen">
    <header class="header">
        <div class="nav-container">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-8">
                    <img src="https://juliengournay.fr/img/logoName.png" alt="Logo" class="logo">
                    <div class="category-tabs">
                        <div class="category-tab active" data-category="web">Sites Web</div>
                        <div class="category-tab" data-category="redirect">Redirections</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8">
        <div class="text-center mb-12 flex flex-col items-center mt-0">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4 animate-gradient bg-gradient-to-r from-primary-500 via-primary-600 to-primary-700 bg-clip-text text-transparent">
                État des sites Julien Gournay
            </h1>
            <p class="text-gray-600 dark:text-gray-400">Surveillance en temps réel de mes services</p>
            <button id="refreshButton" class="refresh-button w-min mt-12">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span>Rafraîchir</span>
            </button>
        </div>

        <?php foreach ($sites as $category => $categorySites): ?>
        <div id="<?= $category ?>Container" class="category-content <?= $category === 'web' ? 'active' : '' ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($categorySites as $site):
                    $result = checkSiteStatus($site);
                    $history = updateSiteHistory($site, $result);
                    $statusClass = ($result['status'] == 200) ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 
                                (($result['status'] >= 500) ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' : 
                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300');
                    $statusIcon = ($result['status'] == 200) ? '✅' : (($result['status'] >= 500) ? '❌' : '⚠️');
                ?>
                    <div class="card-hover bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow duration-300 cursor-pointer" onclick="showDowntimes('<?= htmlspecialchars($site) ?>')">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white truncate">
                                <?= htmlspecialchars(parse_url($site, PHP_URL_HOST)) ?>
                            </h2>
                            <span class="text-2xl"><?= $statusIcon ?></span>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Statut HTTP</span>
                                <span class="px-3 py-1 text-sm rounded-full <?= $statusClass ?>">
                                    <?= $result['status'] ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Temps de réponse</span>
                                <span class="text-gray-900 dark:text-white font-medium">
                                    <?= $result['time'] ?> ms
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Disponibilité (30j)</span>
                                <span class="text-gray-900 dark:text-white font-medium">
                                    <?= calculateUptime($history['history']) ?>%
                                </span>
                            </div>

                            <?php if ($history['down_since']): ?>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Down depuis</span>
                                <span class="text-red-600 dark:text-red-400 font-medium">
                                    <?= formatDuration(time() - $history['down_since']) ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <?php if ($history['last_up']): ?>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Dernier up</span>
                                <span class="text-gray-900 dark:text-white">
                                    <?= date('d/m/Y H:i', $history['last_up']) ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <?php if ($history['last_down']): ?>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Dernier down</span>
                                <span class="text-gray-900 dark:text-white">
                                    <?= date('d/m/Y H:i', $history['last_down']) ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <a href="<?= htmlspecialchars($site) ?>" target="_blank" 
                               class="block w-full mt-4 px-4 py-2 text-center text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors duration-300">
                                Visiter le site
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal pour afficher les pannes -->
    <div id="downtimeModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 class="text-2xl font-bold mb-4" id="modalTitle"></h2>
            <div id="downtimeContent"></div>
        </div>
    </div>

    <!-- Flowbite JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    
    <script>
        // Gestion des onglets de catégories
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.category-tab');
            const contents = document.querySelectorAll('.category-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Mettre à jour les onglets actifs
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    // Afficher le contenu correspondant
                    const category = tab.dataset.category;
                    contents.forEach(content => {
                        content.classList.remove('active');
                    });
                    document.getElementById(`${category}Container`).classList.add('active');
                });
            });
            
            // Rafraîchissement manuel
            document.getElementById('refreshButton').addEventListener('click', updateSites);
        });
        
        function updateSites() {
            fetch(window.location.href, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                for (const [category, sites] of Object.entries(data)) {
                    const container = document.getElementById(`${category}Container`);
                    const grid = container.querySelector('.grid');
                    grid.innerHTML = '';
                    
                    for (const [site, info] of Object.entries(sites)) {
                        const status = info.status;
                        const history = info.history;
                        const uptime = info.uptime;
                        const host = new URL(site).host;
                        
                        const statusClass = status.status === 200 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' :
                            (status.status >= 500 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' :
                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300');
                        
                        const statusIcon = status.status === 200 ? '✅' : (status.status >= 500 ? '❌' : '⚠️');
                        
                        let downSince = '';
                        if (history.down_since) {
                            const duration = Math.floor((Date.now()/1000 - history.down_since));
                            const minutes = Math.floor(duration / 60);
                            const hours = Math.floor(minutes / 60);
                            const days = Math.floor(hours / 24);
                            
                            if (days > 0) downSince = `${days} jour${days > 1 ? 's' : ''}`;
                            else if (hours > 0) downSince = `${hours} heure${hours > 1 ? 's' : ''}`;
                            else if (minutes > 0) downSince = `${minutes} minute${minutes > 1 ? 's' : ''}`;
                            else downSince = `${duration} seconde${duration > 1 ? 's' : ''}`;
                        }
                        
                        const card = document.createElement('div');
                        card.className = 'card-hover bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow duration-300 cursor-pointer';
                        card.onclick = () => showDowntimes(site);
                        card.innerHTML = `
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-white truncate">${host}</h2>
                                <span class="text-2xl">${statusIcon}</span>
                            </div>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Statut HTTP</span>
                                    <span class="px-3 py-1 text-sm rounded-full ${statusClass}">${status.status}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Temps de réponse</span>
                                    <span class="text-gray-900 dark:text-white font-medium">${status.time} ms</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Disponibilité (30j)</span>
                                    <span class="text-gray-900 dark:text-white font-medium">${uptime}%</span>
                                </div>
                                ${history.down_since ? `
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Down depuis</span>
                                    <span class="text-red-600 dark:text-red-400 font-medium">${downSince}</span>
                                </div>
                                ` : ''}
                                ${history.last_up ? `
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Dernier up</span>
                                    <span class="text-gray-900 dark:text-white">${new Date(history.last_up * 1000).toLocaleString('fr-FR')}</span>
                                </div>
                                ` : ''}
                                ${history.last_down ? `
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Dernier down</span>
                                    <span class="text-gray-900 dark:text-white">${new Date(history.last_down * 1000).toLocaleString('fr-FR')}</span>
                                </div>
                                ` : ''}
                                <a href="${site}" target="_blank" class="block w-full mt-4 px-4 py-2 text-center text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors duration-300">
                                    Visiter le site
                                </a>
                            </div>
                        `;
                        grid.appendChild(card);
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Auto-refresh avec l'intervalle défini dans les paramètres
        setInterval(updateSites, <?= $settings['refresh_interval'] ?>);

        function showDowntimes(site) {
            const modal = document.getElementById('downtimeModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('downtimeContent');
            
            // Récupérer les données du site
            const siteData = data[site];
            const downtimes = siteData.history.filter(check => check.status !== 200);
            
            title.textContent = `Pannes détectées - ${new URL(site).host}`;
            
            if (downtimes.length === 0) {
                content.innerHTML = '<p class="text-gray-600 dark:text-gray-400">Aucune panne détectée ce mois-ci.</p>';
            } else {
                let html = `
                    <div class="mb-4">
                        <p class="text-lg font-semibold">Total des pannes ce mois-ci : ${downtimes.length}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2">Date</th>
                                    <th class="text-left py-2">Statut</th>
                                    <th class="text-left py-2">Temps de réponse</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                downtimes.forEach(downtime => {
                    html += `
                        <tr class="border-b">
                            <td class="py-2">${new Date(downtime.timestamp * 1000).toLocaleString('fr-FR')}</td>
                            <td class="py-2">${downtime.status}</td>
                            <td class="py-2">${downtime.response_time} ms</td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                content.innerHTML = html;
            }
            
            modal.style.display = 'block';
        }
        
        // Fermer le modal
        document.querySelector('.close-modal').addEventListener('click', function() {
            document.getElementById('downtimeModal').style.display = 'none';
        });
        
        // Fermer le modal en cliquant en dehors
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('downtimeModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
</body>
</html>
