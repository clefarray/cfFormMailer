//<?php
/**
 * cfFileView
 * cfFormMailer用アップロードファイル表示
 *
 * @version 1.1
 * @author Clefarray Factory
 * @internal @events OnPageNotFound
 * @internal @modx_category cfFormMailer
 * 
 */

$viewFileName = 'cfFileView';

switch ($modx->Event->name) {
  case "OnPageNotFound":
    if ($modx->documentIdentifier == $viewFileName) {
      $field = $_GET['field'];
      if (!$field) {
        exit;
      }

      if (isset($_SESSION['_cf_uploaded'][$field]) && is_file($_SESSION['_cf_uploaded'][$field]['path'])) {
        header('P3P: CP="NOI NID ADMa OUR IND UNI COM NAV"');
        header('Cache-Control: private, must-revalidate');
        header('Content-type: ' . $_SESSION['_cf_uploaded'][$field]['mime']);
        readfile($_SESSION['_cf_uploaded'][$field]['path']);
      }
      exit;
    }
    break;
}
