<?php

/**
 * Listing des commentaires en attente de validation
 *
 * @package PLX
 * @author	Stephane F
 **/

include __DIR__ .'/prepend.php';

# Contrôle du token du formulaire
plxToken::validateFormToken($_POST);

# Hook Plugins
eval($plxAdmin->plxPlugins->callHook('AdminCommentsPrepend'));

# Contrôle de l'accès à la page en fonction du profil de l'utilisateur connecté
$plxAdmin->checkProfil(PROFIL_ADMIN, PROFIL_MANAGER, PROFIL_MODERATOR);

# validation de l'id de l'article si passé en paramètre
if(isset($_GET['a']) AND !preg_match('/^_?[0-9]{4}$/',$_GET['a'])) {
	plxMsg::Error(L_ERR_UNKNOWN_ARTICLE); # Article inexistant
	header('Location: index.php');
	exit;
}

# Suppression des commentaires sélectionnés
if(isset($_POST['selection']) AND !empty($_POST['btn_ok']) AND ($_POST['selection']=='delete') AND isset($_POST['idCom'])) {
	foreach ($_POST['idCom'] as $k => $v) $plxAdmin->delCommentaire($v);
	header('Location: comments.php'.(!empty($_GET['a'])?'?a='.$_GET['a']:''));
	exit;
}
# Validation des commentaires sélectionnés
elseif(isset($_POST['selection']) AND !empty($_POST['btn_ok']) AND ($_POST['selection']=='online') AND isset($_POST['idCom'])) {
	foreach ($_POST['idCom'] as $k => $v) $plxAdmin->modCommentaire($v, 'online');
	header('Location: comments.php'.(!empty($_GET['a'])?'?a='.$_GET['a']:''));
	exit;
}
# Mise hors-ligne des commentaires sélectionnés
elseif (isset($_POST['selection']) AND !empty($_POST['btn_ok']) AND ($_POST['selection']=='offline') AND isset($_POST['idCom'])) {
	foreach ($_POST['idCom'] as $k => $v) $plxAdmin->modCommentaire($v, 'offline');
	header('Location: comments.php'.(!empty($_GET['a'])?'?a='.$_GET['a']:''));
	exit;
}

# Récupération des infos sur l'article attaché au commentaire si passé en paramètre
if(!empty($_GET['a'])) {
	# Infos sur notre article
	if(!$globArt = $plxAdmin->plxGlob_arts->query('/^'.$_GET['a'].'.(.*).xml$/','','sort',0,1)) {
		plxMsg::Error(L_ERR_UNKNOWN_ARTICLE); # Article inexistant
		header('Location: index.php');
		exit;
	}
	# Infos sur l'article
	$aArt = $plxAdmin->parseArticle(PLX_ROOT.$plxAdmin->aConf['racine_articles'].$globArt['0']);
	$portee = L_COMMENTS_ARTICLE_SCOPE.' &laquo;'.$aArt['title'].'&raquo;';
} else { # Commentaires globaux
	$portee = '';
}

# On inclut le header
include __DIR__ .'/top.php';

# Récupération du type de commentaire à afficher
$_GET['sel'] = !empty($_GET['sel']) ? $_GET['sel'] : '';
if(in_array($_GET['sel'], array('online', 'offline', 'all')))
	$comSel = plxUtils::nullbyteRemove($_GET['sel']);
else
	$comSel = ((isset($_SESSION['selCom']) AND !empty($_SESSION['selCom'])) ? $_SESSION['selCom'] : 'all');

if(!empty($_GET['a'])) {

	switch ($comSel) {
		case 'online':
			$mod = '';
			break;
		case 'offline':
			$mod = '_';
			break;
		default:
			$mod = '[[:punct:]]?';
	}
	$comSelMotif = '/^'.$mod.str_replace('_','',$_GET['a']).'.(.*).xml$/';
	$_SESSION['selCom'] = 'all';
	$nbComPagination=$plxAdmin->nbComments($comSelMotif);
	$h2 = '<h2>'.L_COMMENTS_ALL_LIST.'</h2>';
}
elseif($comSel=='online') {
	$comSelMotif = '/^\d{4}.(.*).xml$/';
	$_SESSION['selCom'] = 'online';
	$nbComPagination=$plxAdmin->nbComments('online');
	$h2 = '<h2>'.L_COMMENTS_ONLINE_LIST.'</h2>';
}
elseif($comSel=='offline') {
	$comSelMotif = '/^_\d{4}.(.*).xml$/';
	$_SESSION['selCom'] = 'offline';
	$nbComPagination=$plxAdmin->nbComments('offline');
	$h2 = '<h2>'.L_COMMENTS_OFFLINE_LIST.'</h2>';
}
elseif($comSel=='all') { // all
	$comSelMotif = '/^[[:punct:]]?\d{4}.(.*).xml$/';
	$_SESSION['selCom'] = 'all';
	$nbComPagination=$plxAdmin->nbComments('all');
	$h2 = '<h2>'.L_COMMENTS_ALL_LIST.'</h2>';
}

if($portee!='') {
	$h3 = '<h3>'.$portee.'</h3>';
}

$breadcrumbs = array();
$breadcrumbs[] = '<li><a '.($_SESSION['selCom']=='all'?'class="selected" ':'').'href="comments.php?sel=all&amp;page=1">'.L_ALL.'</a>&nbsp;('.$plxAdmin->nbComments('all').')</li>';
$breadcrumbs[] = '<li><a '.($_SESSION['selCom']=='online'?'class="selected" ':'').'href="comments.php?sel=online&amp;page=1">'.L_COMMENT_ONLINE.'</a>&nbsp;('.$plxAdmin->nbComments('online').')</li>';
$breadcrumbs[] = '<li><a '.($_SESSION['selCom']=='offline'?'class="selected" ':'').'href="comments.php?sel=offline&amp;page=1">'.L_COMMENT_OFFLINE.'</a>&nbsp;('.$plxAdmin->nbComments('offline').')</li>';
if(!empty($_GET['a'])) {
	$breadcrumbs[] = '<a href="comment_new.php?a='.$_GET['a'].'" title="'.L_COMMENT_NEW_COMMENT_TITLE.'">'.L_COMMENT_NEW_COMMENT.'</a>';
}

function selector($comSel, $id) {
	ob_start();
	if($comSel=='online')
		plxUtils::printSelect('selection', array(''=> L_FOR_SELECTION, 'offline' => L_COMMENT_SET_OFFLINE, '-'=>'-----', 'delete' => L_COMMENT_DELETE), '', false,'no-margin',$id);
	elseif($comSel=='offline')
		plxUtils::printSelect('selection', array(''=> L_FOR_SELECTION, 'online' => L_COMMENT_SET_ONLINE, '-'=>'-----', 'delete' => L_COMMENT_DELETE), '', false,'no-margin',$id);
	elseif($comSel=='all')
		plxUtils::printSelect('selection', array(''=> L_FOR_SELECTION, 'online' => L_COMMENT_SET_ONLINE, 'offline' => L_COMMENT_SET_OFFLINE,  '-'=>'-----','delete' => L_COMMENT_DELETE), '', false,'no-margin',$id);
	return ob_get_clean();
}

$selector=selector($comSel, 'id_selection');

?>

<?php eval($plxAdmin->plxPlugins->callHook('AdminCommentsTop')) # Hook Plugins ?>

<form action="comments.php<?php echo htmlentities(!empty($_GET['a'])?'?a='.$_GET['a']:'', ENT_QUOTES, 'UTF-8') ?>" method="post" id="form_comments">

	<div class="inline-form action-bar">
		<?php echo $h2 ?>
		<ul class="menu">
			<?php echo htmlentities(implode($breadcrumbs), ENT_QUOTES, 'UTF-8'); ?>
		</ul>
		<?php echo $selector ?>
		<?php echo plxToken::getTokenPostMethod() ?>
		<input type="submit" name="btn_ok" value="<?php echo L_OK ?>" onclick="return confirmAction(this.form, 'id_selection', 'delete', 'idCom[]', '<?php echo L_CONFIRM_DELETE ?>')" />
	</div>

	<?php if(isset($h3)) echo htmlentities($h3, ENT_QUOTES, 'UTF-8'); ?>

	<div class="scrollable-table">
		<table id="comments-table" class="full-width">
			<thead>
				<tr>
					<th class="checkbox"><input type="checkbox" onclick="checkAll(this.form, 'idCom[]')" /></th>
					<th class="datetime"><?= L_COMMENTS_LIST_DATE ?></th>
<?php
			$all = ($_SESSION['selCom'] == 'all');
			if($all) {
?>
					<th class="status"><?= L_COMMENT_STATUS_FIELD ?></th>
<?php
			}
?>
					<th class="message"><?= L_COMMENTS_LIST_MESSAGE ?></th>
					<th class="author"><?= L_COMMENTS_LIST_AUTHOR ?> <?= L_COMMENT_EMAIL_FIELD ?></th>
					<th class="site"><?= L_COMMENT_SITE_FIELD ?></th>
					<th class="action"><?= L_COMMENTS_LIST_ACTION ?></th>
				</tr>
			</thead>
			<tbody>

<?php
			# On va récupérer les commentaires
			$plxAdmin->getPage();
			$start = $plxAdmin->aConf['bypage_admin_coms']*($plxAdmin->page-1);
			$coms = $plxAdmin->getCommentaires($comSelMotif,'rsort',$start,$plxAdmin->aConf['bypage_admin_coms'],'all');
			if($coms) {
				while($plxAdmin->plxRecord_coms->loop()) { # On boucle
					$artId = $plxAdmin->plxRecord_coms->f('article');
					$status = $plxAdmin->plxRecord_coms->f('status');
					$id = $status.$artId.'.'.$plxAdmin->plxRecord_coms->f('numero');
					$query = 'c=' . $id;
					if(isset($_GET['a'])) {
						$query .= '&a=' . $_GET['a'];
					}
					# On génère notre ligne
?>
				<tr class="top type-<?= $plxAdmin->plxRecord_coms->f('type') ?>">
					<td><input type="checkbox" name="idCom[]" value="<?= $id ?>" /></td>
					<td class="datetime"><?= plxDate::formatDate($plxAdmin->plxRecord_coms->f('date')) ?></td>
<?php
				if($all) {
?>
					<td class="status"><?= empty($status) ? L_COMMENT_ONLINE : L_COMMENT_OFFLINE ?></td>
<?php
				}
?>
					<td class="wrap"><?= nl2br($plxAdmin->plxRecord_coms->f('content')) ?></td>
					<td class="author"><?php
					$author = $plxAdmin->plxRecord_coms->f('author');
					$mail = $plxAdmin->plxRecord_coms->f('mail');
					if(!empty($mail)) {
?><a href="mailto:<?= $mail ?>"><?= $author ?></a><?php
					} else {
						echo $author;
					}
?></td>
					<td class="site"><?php
					$site = $plxAdmin->plxRecord_coms->f('site');
					if(!empty($site)) {
?><a href="<?= $site ?>" target="_blank"><?= $site ?></a><?php
					} else {
						echo '&nbsp;';
					}
?></td>
					<td class="action">
						<a href="comment_new.php?<?= htmlentities($query, ENT_QUOTES, 'UTF-8') ?>" title="<?= L_COMMENT_ANSWER ?>"><?= L_COMMENT_ANSWER ?></a>
						<a href="comment.php?<?= htmlentities($query, ENT_QUOTES, 'UTF-8') ?>" title="<?= L_COMMENT_EDIT_TITLE ?>"><?= L_COMMENT_EDIT ?></a>
						<a href="article.php?a=<?= $artId ?>" title="<?= L_COMMENT_ARTICLE_LINKED_TITLE ?>"><?= L_COMMENT_ARTICLE_LINKED ?></a>
					</td>
				</tr>
<?php
				}
			} else { # Pas de commentaires
?>
				<tr>
					<td colspan="5" class="center"><?= L_NO_COMMENT ?></td>
				</tr>
<?php
			}
			?>
			</tbody>
		</table>
	</div>

</form>

<p id="pagination">
<?php
	# Hook Plugins
	eval($plxAdmin->plxPlugins->callHook('AdminCommentsPagination'));
	# Affichage de la pagination
	if($coms) { # Si on a des articles (hors page)
		# Calcul des pages
		$last_page = ceil($nbComPagination/$plxAdmin->aConf['bypage_admin_coms']);
		$stop = $plxAdmin->page + 2;
		if($stop<5) $stop=5;
		if($stop>$last_page) $stop=$last_page;
		$start = $stop - 4;
		if($start<1) $start=1;
		# Génération des URLs
		$sel = '&amp;sel='.$_SESSION['selCom'].(!empty($_GET['a'])?'&amp;a='.$_GET['a']:'');
		$p_url = 'comments.php?page='.($plxAdmin->page-1).$sel;
		$n_url = 'comments.php?page='.($plxAdmin->page+1).$sel;
		$l_url = 'comments.php?page='.$last_page.$sel;
		$f_url = 'comments.php?page=1'.$sel;
		# Affichage des liens de pagination
		printf('<span class="p_page">'.L_PAGINATION.'</span>', '<input style="text-align:right;width:35px" onchange="window.location.href=\'comments.php?page=\'+this.value+\''.$sel.'\'" value="'.$plxAdmin->page.'" />', $last_page);
		$s = $plxAdmin->page>2 ? '<a href="'.$f_url.'" title="'.L_PAGINATION_FIRST_TITLE.'">&laquo;</a>' : '&laquo;';
		echo htmlentities('<span class="p_first">'.$s.'</span>', ENT_QUOTES, 'UTF-8');
		$s = $plxAdmin->page>1 ? '<a href="'.$p_url.'" title="'.L_PAGINATION_PREVIOUS_TITLE.'">&lsaquo;</a>' : '&lsaquo;';
		echo htmlentities('<span class="p_prev">'.$s.'</span>', ENT_QUOTES, 'UTF-8');
		for($i=$start;$i<=$stop;$i++) {
			$s = $i==$plxAdmin->page ? $i : '<a href="'.('comments.php?page='.$i.$sel).'" title="'.$i.'">'.$i.'</a>';
			echo '<span class="p_current">'.$s.'</span>';
		}
		$s = $plxAdmin->page<$last_page ? '<a href="'.$n_url.'" title="'.L_PAGINATION_NEXT_TITLE.'">&rsaquo;</a>' : '&rsaquo;';
		echo htmlentities('<span class="p_next">'.$s.'</span>', ENT_QUOTES, 'UTF-8');
		$s = $plxAdmin->page<($last_page-1) ? '<a href="'.$l_url.'" title="'.L_PAGINATION_LAST_TITLE.'">&raquo;</a>' : '&raquo;';
		echo htmlentities('<span class="p_last">'.$s.'</span>', ENT_QUOTES, 'UTF-8');
	}
?>
</p>

<?php if(!empty($plxAdmin->aConf['clef'])) : ?>

<ul class="unstyled-list">
	<li><?php echo L_COMMENTS_PRIVATE_FEEDS ?> :</li>
	<?php $urlp_hl = $plxAdmin->racine.'feed.php?admin'.$plxAdmin->aConf['clef'].'/commentaires/hors-ligne'; ?>
	<li><a href="<?php echo $urlp_hl ?>" title="<?php echo L_COMMENT_OFFLINE_FEEDS_TITLE ?>"><?php echo L_COMMENT_OFFLINE_FEEDS ?></a></li>
	<?php $urlp_el = $plxAdmin->racine.'feed.php?admin'.$plxAdmin->aConf['clef'].'/commentaires/en-ligne'; ?>
	<li><a href="<?php echo $urlp_el ?>" title="<?php echo L_COMMENT_ONLINE_FEEDS_TITLE ?>"><?php echo L_COMMENT_ONLINE_FEEDS ?></a></li>
</ul>

<?php endif; ?>

<?php
# Hook Plugins
eval($plxAdmin->plxPlugins->callHook('AdminCommentsFoot'));
# On inclut le footer
include __DIR__ .'/foot.php';
?>
