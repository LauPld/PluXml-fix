<?php

/**
 * Gestion de l'administration d'un plugin
 *
 * @package PLX
 * @author	Stephane F
 **/
include __DIR__ .'/prepend.php';

$plugin = isset($_GET['p']) ? urldecode($_GET['p']) : '';
$plugin = plxUtils::nullbyteRemove($plugin);
$plugin = basename($plugin); // Nettoyage du nom du plugin

$output = '';
# chargement du fichier d'administration du plugin
$pluginDir = PLX_PLUGINS . $plugin;
$filename = realpath($pluginDir . '/admin.php');

# Vérification que le fichier est bien dans le répertoire des plugins
if ($filename && strpos($filename, realpath($pluginDir)) === 0 && is_file($filename)) {
    # utilisation de la variable plxPlugin pour faciliter la syntaxe dans les devs des plugins
    $plxPlugin = $plxAdmin->plxPlugins->aPlugins[$plugin];
    # Control des autorisation d'accès à l'écran admin.php du plugin
    $plxAdmin->checkProfil($plxPlugin->getAdminProfil());
    ob_start();
    echo '
    <div class="inline-form action-bar">
        <h2>' . plxUtils::strCheck($plugin) . '</h2>
    </div>';
    include $filename;
    $output = ob_get_clean();
} else {
    plxMsg::Error(L_NO_ENTRY);
    header('Location: index.php');
    exit;
}

# On inclut le header
include __DIR__ .'/top.php';
# Affichage des données
echo $output;
# On inclut le footer
include __DIR__ .'/foot.php';
?>
