<?php

/**
 * Page d'authentification
 *
 * @package PLX
 * @author    Stephane F, Florent MONTHEL, Pedro "P3ter" CADETE
 **/

# Constante pour retrouver la page d'authentification
const PLX_AUTHPAGE = true;

include __DIR__ . '/prepend.php';

# Control du token du formulaire
plxToken::validateFormToken($_POST);

# Protection anti brute force
$maxlogin['counter'] = 99; # nombre de tentative de connexion autorisé dans la limite de temps autorisé
$maxlogin['timer'] = 3 * 60; # temps d'attente limite si nombre de tentative de connexion atteint (en minutes)

# Initialiser les messages d'alerte
$msg = '';
$css = '';

# Hook Plugins
eval($plxAdmin->plxPlugins->callHook('AdminAuthPrepend'));

# Identifier une erreur de connexion
if (isset($_SESSION['maxtry'])) {
    if (intval($_SESSION['maxtry']['counter']) >= $maxlogin['counter'] and (time() < $_SESSION['maxtry']['timer'] + $maxlogin['timer'])) {
        # écriture dans les logs du dépassement des 3 tentatives successives de connexion
        @error_log("PluXml: Max login failed. IP : " . plxUtils::getIp());
        # message à affiche sur le mire de connexion
        $msg = sprintf(L_ERR_MAXLOGIN, ($maxlogin['timer'] / 60));
        $css = 'alert red';
    }
    if (time() > ($_SESSION['maxtry']['timer'] + $maxlogin['timer'])) {
        # on réinitialise le control brute force quand le temps d'attente limite est atteint
        $_SESSION['maxtry']['counter'] = 0;
        $_SESSION['maxtry']['timer'] = time();
    }
} else {
    # initialisation de la variable qui compte les tentatives de connexion
    $_SESSION['maxtry']['counter'] = 0;
    $_SESSION['maxtry']['timer'] = time();
}

# Incrémente le nombre de tentative
$redirect = $plxAdmin->aConf['racine'] . 'core/admin/';
if (!empty($_GET['p']) and $css == '') {

    # on incremente la variable de session qui compte les tentatives de connexion
    $_SESSION['maxtry']['counter']++;

    $racine = parse_url($plxAdmin->aConf['racine']);
    $get_p = parse_url(urldecode($_GET['p']));
    $css = (!$get_p or (isset($get_p['host']) and $racine['host'] != $get_p['host']));
    if (!$css and !empty($get_p['path']) and file_exists(PLX_ROOT . 'core/admin/' . basename($get_p['path']))) {
        # filtrage des parametres de l'url
        $query = '';
        if (isset($get_p['query'])) {
            $query = strtok($get_p['query'], '=');
            $query = ($query[0] != 'd' ? '?' . $get_p['query'] : '');
        }
        # url de redirection
        $redirect = $get_p['path'] . $query;
    }
}

# Déconnexion (paramètre url : ?d=1)
if (!empty($_GET['d']) and $_GET['d'] == 1) {

    $_SESSION = array();
    session_destroy();
    header('Location: auth.php');
    exit;
}

# Authentification
if (!empty($_POST['login']) and !empty($_POST['password']) and $css == '') {

    $connected = false;
    foreach ($plxAdmin->aUsers as $userid => $user) {
        if ($_POST['login'] == $user['login'] and sha1($user['salt'] . md5($_POST['password'])) === $user['password'] and $user['active'] and !$user['delete']) {
            $_SESSION['user'] = $userid;
            $_SESSION['profil'] = $user['profil'];
            $_SESSION['hash'] = plxUtils::charAleatoire(10);
            $_SESSION['domain'] = $session_domain;
            # on définit $_SESSION['admin_lang'] pour stocker la langue à utiliser la 1ere fois dans le chargement des plugins une fois connecté à l'admin
            # ordre des traitements:
            # page administration : chargement fichier prepend.php
            # => creation instance plxAdmin : chargement des plugins, chargement des prefs utilisateurs
            # => chargement des langues en fonction du profil de l'utilisateur connecté déterminé précédemment
            $_SESSION['admin_lang'] = $user['lang'];
            $connected = true;
            break;
        }
    }
    if ($connected) {
        unset($_SESSION['maxtry']);
        header('Location: ' . htmlentities($redirect));
        exit;
    } else {
        $msg = L_ERR_WRONG_PASSWORD;
        $css = 'alert red';
    }
}

# Password change
if ($plxAdmin->aConf['lostpassword']) {
    # Send lost password e-mail
    if (!empty($_POST['lostpassword_id'])) {
        if (!empty($plxAdmin->sendLostPasswordEmail($_POST['lostpassword_id']))) {
            $msg = L_LOST_PASSWORD_SUCCESS;
            $css = 'alert green';
        } else {
            @error_log("Lost password error. ID : " . $_POST['lostpassword_id'] . " IP : " . plxUtils::getIp());
            $msg = L_UNKNOWN_ERROR;
            $css = 'alert red';
        }
    }
    # Change password
    if (!empty($_POST['editpassword'])) {
        unset($_SESSION['error']);
        unset($_SESSION['info']);
        $plxAdmin->editPassword($_POST);
        if (!empty($msg = isset($_SESSION['error']) ? $_SESSION['error'] : '')) {
            $css = 'alert red';
        } else {
            if (!empty($msg = isset($_SESSION['info']) ? $_SESSION['info'] : '')) {
                $css = 'alert green';
            }
        }
        unset($_SESSION['error']);
        unset($_SESSION['info']);
    }
}

# Construction de la page HTML
plxUtils::cleanHeaders();
?>
<!DOCTYPE html>
<html lang="<?php echo $plxAdmin->aConf['default_lang'] ?>">
<head>
    <meta name="robots" content="noindex, nofollow"/>
    <meta name="viewport" content="width=device-width, user-scalable=yes, initial-scale=1.0">
    <title>PluXml - <?php echo L_AUTH_PAGE_TITLE ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo strtolower(PLX_CHARSET); ?>"/>
    <link rel="stylesheet" type="text/css"
          href="<?php echo PLX_CORE ?>admin/theme/plucss.css?v=<?php echo PLX_VERSION ?>" media="screen"/>
    <link rel="stylesheet" type="text/css"
          href="<?php echo PLX_CORE ?>admin/theme/theme.css?v=<?php echo PLX_VERSION ?>" media="screen"/>
    <link rel="stylesheet" type="text/css"
          href="<?php echo PLX_CORE ?>admin/theme/fonts/fontello.css?v=<?php echo PLX_VERSION ?>" media="screen"/>
    <link rel="icon" href="<?php echo PLX_CORE ?>admin/theme/images/favicon.png"/>
    <?php
    plxUtils::printLinkCss($plxAdmin->aConf['custom_admincss_file'], true);
    plxUtils::printLinkCss($plxAdmin->aConf['racine_plugins'] . 'admin.css', true);
    # Hook Plugins
    eval($plxAdmin->plxPlugins->callHook('AdminAuthEndHead'));
    ?>
    <script src="<?php echo PLX_CORE ?>lib/visual.js?v=<?php echo PLX_VERSION ?>"></script>
</head>
<body id="auth">
<main class="container">
    <section class="grid">
        <div class="logo"></div>
        <div class="auth col sml-12 sml-centered med-5 lrg-3">
            <?php
            # Hook plugins
            eval($plxAdmin->plxPlugins->callHook('AdminAuthBegin'));
            switch (isset($_GET['action']) ? $_GET['action'] : false) {
                case 'lostpassword': # Affichage du formulaire d'envoi du mail de changement de mot de passe
                    # Hook plugins
                    eval($plxAdmin->plxPlugins->callHook('AdminAuthTopLostPassword'));
                    ?>
                    <form action="auth.php<?php echo !empty($redirect) ? '?p=' . plxUtils::strCheck(urlencode($redirect)) : '' ?>"
                          method="post" id="form_auth">
                        <fieldset>
                            <?php echo plxToken::getTokenPostMethod() ?>
                            <h1 class="h5 text-center"><strong><?php echo L_LOST_PASSWORD ?></strong></h1>
                            <div class="grid">
                                <div class="col sml-12">
                                    <i class="ico icon-user"></i>
                                    <?php plxUtils::printInput('lostpassword_id', (!empty($_POST['lostpassword_id'])) ? plxUtils::strCheck($_POST['lostpassword_id']) : '', 'text', '10-255', false, 'full-width', L_AUTH_LOST_FIELD, 'autofocus'); ?>
                                </div>
                            </div>
                            <div class="grid">
                                <div class="col sml-12">
                                    <small><a href="?p=/core/admin"><?php echo L_LOST_PASSWORD_LOGIN ?></a></small>
                                </div>
                            </div>
                            <?php
                            # Hook plugins
                            eval($plxAdmin->plxPlugins->callHook('AdminAuthLostPassword'));
                            ?>
                            <div class="grid">
                                <div class="col sml-12 text-center">
                                    <input class="blue" type="submit" value="<?php echo L_SUBMIT_BUTTON ?>"/>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                    <p class="text-center">
                        <small><a class="back" href="<?php echo PLX_ROOT; ?>"><?php echo L_BACK_TO_SITE ?></a>
                            - <?php echo L_POWERED_BY ?></small>
                    </p>
                    <?php
                    break;
                case 'changepassword': # Affichage du formulaire de changement de mot passe
                    $lostPasswordToken = $_GET['token'];
                    if ($plxAdmin->verifyLostPasswordToken($lostPasswordToken)) {
                        # Hook plugins
                        eval($plxAdmin->plxPlugins->callHook('AdminAuthTopChangePassword'));
                        ?>
                        <form action="auth.php<?php echo !empty($redirect) ? '?p=' . plxUtils::strCheck(urlencode($redirect)) : '' ?>"
                              method="post" id="form_auth">
                            <fieldset>
                                <?php echo plxToken::getTokenPostMethod() ?>
                                <input name="lostPasswordToken" value="<?php echo htmlentities($lostPasswordToken, ENT_QUOTES, 'UTF-8') ?>" type="hidden"/>
                                <h1 class="h5 text-center"><strong><?php echo L_PROFIL_CHANGE_PASSWORD ?></strong></h1>
                                <div class="grid">
                                    <div class="col sml-12">
                                        <i class="ico icon-lock"></i>
                                        <?php plxUtils::printInput('password1', '', 'password', '10-255', false, 'full-width', L_PROFIL_PASSWORD, 'onkeyup="pwdStrength(this.id)"') ?>
                                    </div>
                                </div>
                                <div class="grid">
                                    <div class="col sml-12">
                                        <i class="ico icon-lock"></i>
                                        <?php plxUtils::printInput('password2', '', 'password', '10-255', false, 'full-width', L_PROFIL_CONFIRM_PASSWORD) ?>
                                    </div>
                                </div>
                                <div class="grid">
                                    <div class="col sml-12">
                                        <small><a href="?p=/core/admin"><?php echo L_LOST_PASSWORD_LOGIN ?></a></small>
                                    </div>
                                </div>
                                <?php
                                # Hook plugins
                                eval($plxAdmin->plxPlugins->callHook('AdminAuthChangePassword'));
                                ?>
                                <div class="grid">
                                    <div class="col sml-12 text-center">
                                        <input type="submit" name="editpassword"
                                               value="<?php echo L_PROFIL_UPDATE_PASSWORD ?>"/>
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                        <p class="text-center">
                            <small><a class="back" href="<?php echo PLX_ROOT; ?>"><?php echo L_BACK_TO_SITE ?></a>
                                - <?php echo L_POWERED_BY ?></small>
                        </p>
                        <?php
                    } else {
                        # Hook plugins
                        eval($plxAdmin->plxPlugins->callHook('AdminAuthTopChangePasswordError'));
                        ?>
                        <h1 class="h5 text-center"><strong><?php echo L_PROFIL_CHANGE_PASSWORD ?></strong></h1>
                        <div class="alert red">
                            <?php echo L_LOST_PASSWORD_ERROR ?>
                        </div>
                        <small><a href="?p=/core/admin"><?php echo L_LOST_PASSWORD_LOGIN ?></a></small>
                        <?php eval($plxAdmin->plxPlugins->callHook('AdminAuthChangePasswordError')) # Hook plugins ?>
                        <p class="text-center">
                            <small><a class="back" href="<?php echo PLX_ROOT; ?>"><?php echo L_BACK_TO_SITE ?></a>
                                - <?php echo L_POWERED_BY ?></small>
                        </p>
                        <?php
                    }
                    break;
                default: # Affichage du formulaire de connexion à l'administration
                    ?>
                    <?php eval($plxAdmin->plxPlugins->callHook('AdminAuthTop')) # Hook plugins
                    ?>
                    <form action="auth.php<?php echo !empty($redirect) ? '?p=' . plxUtils::strCheck(urlencode($redirect)) : '' ?>"
                          method="post" id="form_auth">
                        <fieldset>
                            <?php echo plxToken::getTokenPostMethod() ?>
                            <h1 class="h5 text-center"><strong><?php echo L_LOGIN_PAGE ?></strong></h1>
                            <?php (!empty($msg)) ? plxUtils::showMsg($msg, $css) : ''; ?>
                            <div class="grid">
                                <div class="col sml-12">
                                    <i class="ico icon-user"></i>
                                    <?php plxUtils::printInput('login', (!empty($_POST['login'])) ? plxUtils::strCheck($_POST['login']) : '', 'text', '10-255', false, 'full-width', L_AUTH_LOGIN_FIELD, 'autofocus'); ?>
                                </div>
                            </div>
                            <div class="grid">
                                <div class="col sml-12">
                                    <i class="ico icon-lock"></i>
                                    <?php plxUtils::printInput('password', '', 'password', '10-255', false, 'full-width', L_AUTH_PASSWORD_FIELD); ?>
                                </div>
                            </div>
                            <?php
                            if ($plxAdmin->aConf['lostpassword']) {
                                ?>
                                <div class="grid">
                                    <div class="col sml-12">
                                        <small><a href="?action=lostpassword"><?php echo L_LOST_PASSWORD ?></a></small>
                                    </div>
                                </div>
                                <?php
                            }
                            # Hook Plugins
                            eval($plxAdmin->plxPlugins->callHook('AdminAuth'));
                            ?>
                            <div class="grid">
                                <div class="col sml-12 text-center">
                                    <input class="blue" type="submit" value="<?php echo L_SUBMIT_BUTTON ?>"/>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                    <p class="text-center">
                        <small><a class="back" href="<?php echo PLX_ROOT; ?>"><span><?php echo L_BACK_TO_SITE ?></span></a>
                            - <?php echo L_POWERED_BY ?></small>
                    </p>
                <?php
            }
            ?>
        </div>

    </section>
</main>

<?php eval($plxAdmin->plxPlugins->callHook('AdminAuthEndBody')) # Hook Plugins ?>
</body>
</html>
