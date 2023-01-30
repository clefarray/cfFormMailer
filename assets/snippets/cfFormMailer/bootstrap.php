<?php
/**
 * cfFormMailer
 * 
 * @author  Clefarray Factory
 * @link  http://www.clefarray-web.net/
 * @version 1.6
 *
 * Documentation: http://www.clefarray-web.net/blog/manual/cfFormMailer_manual.html
 * LICENSE: GNU General Public License (GPL) (http://www.gnu.org/copyleft/gpl.html)
 */

/** @var documentParser $modx */

if ($modx->isBackend()) {
    return '';
}

if (!isset($config)) {
    return '<strong>ERROR!:</strong> 「config」パラメータは必須です';
}

define('CFM_PATH', __DIR__ . '/');

include_once(CFM_PATH . 'class.cfFormMailer.inc.php');

$mf = new Class_cfFormMailer($modx);

$mf->parseConfig($config);

if ($mf->hasSystemError()) {
    return '<strong>ERROR!</strong> ' . $mf->getSystemError();
}

/**
 * read validate & filter methods
 */
if (is_file(CFM_PATH . 'additionalMethods.inc.php')) {
    include_once CFM_PATH . 'additionalMethods.inc.php';
}


/**
 * Action
 */
if ($_POST['_mode'] === 'conf') {
    $pageType = ($mf->validate()) ? 'conf' : 'error';
} elseif ($_POST['_mode'] === 'send') {
    if (isset($_POST['return'])) {
        if (!$mf->validate()) {
            return $mf->raiseError('未知のエラーです');
        }
        $pageType = 'return';
    }elseif ($mf->validate()) {
        if ($mf->isMultiple())    return $mf->raiseError('すでに送信しています');
        if (!$mf->isValidToken()) return $mf->raiseError('画面遷移が正常に行われませんでした');
        if (!$mf->sendMail())     return $mf->raiseError($mf->getError());

        $mf->storeDataInSession();
        $mf->storeDB();
        $pageType = 'comp';
    } else {
        $pageType = 'error';
    }
} else {
    $pageType = 'input';
}

/**
 * Display page
 */
$html = $mf->createPageHtml($pageType);
if ($html) {
    return $html;
}

return $mf->raiseError($mf->getError());
