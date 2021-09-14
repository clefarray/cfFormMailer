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
if (postv('_mode') === 'conf') {
    return cf_create_view(
        ($mf->validate()) ? 'conf' : 'error',
        $mf
    );
}

if (postv('_mode') !== 'send') {
    return cf_create_view('input', $mf);
}

if (postv('return')) {
    if (!$mf->validate()) {
        return $mf->raiseError('未知のエラーです');
    }
    return cf_create_view('return', $mf);
}
if (!$mf->validate()) {
    return cf_create_view('error', $mf);
}

if ($mf->isMultiple()) {
    return $mf->raiseError('すでに送信しています');
}
if (!$mf->isValidToken()) {
    return $mf->raiseError('画面遷移が正常に行われませんでした');
}
if (!$mf->sendMail()) {
    return $mf->raiseError($mf->getError());
}

$mf->storeDataInSession();
$mf->storeDB();
return cf_create_view('comp', $mf);
