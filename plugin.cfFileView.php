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

if ($modx->event->name!=='OnPageNotFound') return;
if ($modx->documentIdentifier !== $viewFileName) return;

$field = getv('field', '');

if (!$field) exit;

$uploaded_file = sessionv("_cf_uploaded.{$field}");
if (!$uploaded_file) exit;
if (!is_file($uploaded_file['path'])) exit;

header('P3P: CP="NOI NID ADMa OUR IND UNI COM NAV"');
header('Cache-Control: private, must-revalidate');
header('Content-type: ' . $uploaded_file['mime']);
readfile($uploaded_file['path']);
exit;
