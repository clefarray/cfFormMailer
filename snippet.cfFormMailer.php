<?php
/**
 * cfFormMailer
 * 
 * @author  Clefarray Factory
 * @link  http://www.clefarray-web.net/
 * @version 1.3
 *
 * Documentation: http://www.clefarray-web.net/blog/manual/cfFormMailer_manual.html
 * LICENSE: GNU General Public License (GPL) (http://www.gnu.org/copyleft/gpl.html)
 */

if ($modx->isBackend()) {
  return '';
}
$snippet_path = $modx->config['base_path'] . "assets/snippets/cfFormMailer/";
include_once $snippet_path . "class.cfFormMailer.inc.php";

$mf = new Class_cfFormMailer($modx);

/**
 * read config
 */
$lang = (isset($language)) ? $language : $modx->config['manager_language'];
$lang = str_replace("japanese-", "", $lang);
define(CHARSET, $lang);
if (!isset($config)) {
  return '<strong>ERROR!:</strong> `config`パラメータは必須です';
}
if (($result = $mf->parseConfig($config)) !== true) {
  return '<strong>ERROR!:</strong> ' . $result;
}

/**
 * read validate & filter methods
 */
if (file_exists($snippet_path . 'additionalMethods.inc.php')) {
  include_once $snippet_path . 'additionalMethods.inc.php';
}


/**
 * Action
 */
switch($_POST['_mode']) {
  case "conf":
    $pageType = ($mf->validate()) ? 'conf' : 'error';
    break;
  case "send":
    if ($_POST['return']) {
      if (!$mf->validate()) {
        return $mf->raiseError('未知のエラーです');
      }
      $pageType = 'return';
      break;
    }
    if ($mf->validate()) {
      if ($mf->isMultiple()) {
        return $mf->raiseError('すでに送信しています');
      } elseif (!$mf->isValidToken()) {
        return $mf->raiseError('画面遷移が正常に行われませんでした');
      } elseif (!$mf->sendMail()) {
        return $mf->raiseError($mf->getError());
      } else {
        $mf->storeDataInSession();
        $mf->storeDB();
        $pageType = "comp";
      }
    } else {
      $pageType = 'error';
    }
    break;
  default:
    $pageType = 'input';
    break;
}

/**
 * Display page
 */
if ($html = $mf->createPageHtml($pageType)) {
  return $html;
} else {
  return $mf->raiseError($mf->getError());
}

?>