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

class Class_cfFormMailer {

  /**
   * modxオブジェクト
   */
  var $modx;

  /**
   * postされたデータ
   * @var array
   */
  var $form;

  /**
   * 検証エラーのメッセージ
   * @var array
   */
  var $formError;

  /**
   * システムエラーメッセージ
   * @var string
   */
  var $error_message;

  /**
   * フォームの valid 要素
   * @var array
   */
  var $parsedForm;
  
  /**
   * 送信先動的変更のための送信先情報
   * @var array
   */
  var $dynamic_send_to = array();
  
  /**
   * バージョン番号
   * @var string
   */
  var $version = '1.3';

  /** */
  var $lf = "\n";


  /**
   * コンストラクタ
   *
   */
  function Class_cfFormMailer(&$modx) {
    if (!$modx) {
      return false;
    }
    $this->modx = & $modx;

    // 変数初期設定
    $this->form = array();
    $this->error_message ="";
    $this->formError = array();

    // postされたデータを読み取り
    $this->form = $this->getFormVariables($_POST);

    // uploadクラス読み込み
    if (file_exists(dirname(__FILE__) . "/class.upload.php")) {
      include_once "class.upload.php";
    }
  }

  /**
   * 現在のモードからHTML文書を取得・作成
   * 
   * @access public
   * @param  string $mode 現在のモード(input / conf / comp / error / return)
   * @return string HTML文書
   */
  function createPageHtml($mode) {

    // ページテンプレート読み込み
    switch ($mode) {
      case "input":
      case "error":
      case "return":
        $html = $this->loadTemplate(TMPL_INPUT);
        break;
      case "conf":
        $html = $this->loadTemplate(TMPL_CONF);
        break;
      case "comp":
        if (defined('COMPLETE_REDIRECT') && COMPLETE_REDIRECT) {
          if (preg_match("/^[1-9][0-9]*$/", COMPLETE_REDIRECT)) {
            $this->modx->sendRedirect($this->modx->makeUrl(COMPLETE_REDIRECT));
          } elseif (preg_match("/^https?:\/\//i", COMPLETE_REDIRECT)) {
            $this->modx->sendRedirect(COMPLETE_REDIRECT);
          }
        }
        $html = $this->loadTemplate(TMPL_COMP);
        break;
      default:
        $html = false;
        break;
    }
    if ($html === false) {
      return false;
    }

    // ポストされた内容を一時的に退避（事故対策）
    if ($mode == 'error' || $mode == 'conf') {
        $_SESSION['_cf_autosave'] = $this->form;
    }
    
    // アクションごとの処理
    switch ($mode) {
      case "input":
      case "error":
      case "return":
        $nextMode = 'conf';
        
        // CAPTCHA  # Added in v0.0.5
        if (VERICODE) {
          $captcha_uri = $this->getCaptchaUri();
          $html = $this->replacePlaceHolder($html, array('verimageurl' => $captcha_uri));
        }
        
        // 検証フィールドを削除
        $html = preg_replace("/\svalid=([\"\']).+?\\1/i", "", $html);
        // 送信先情報を削除
        $html = preg_replace("/\ssendto=([\"\']).+?\\1/i", "", $html);
        
        // エラーの場合は入力値とエラーメッセージを付記
        if ($mode == 'error') {
          $html = $this->assignErrorTag($html,$this->getFormError());
          $html = $this->assignErrorClass($html, $this->getFormError());    // Added in v0.0.7
          $html = $this->replacePlaceHolder($html, $this->getFormError());
          $html = $this->restoreForm($html, $this->form);
        // 「戻り」の場合は入力値のみ復元
        } elseif ($mode == 'return') {
          $html = $this->restoreForm($html, $this->form);
          // アップロード済みのファイルを削除
          if (count($_SESSION['_cf_uploaded'])) {
            foreach ($_SESSION['_cf_uploaded'] as $filedata) {
              @unlink($filedata['path']);
            }
            unset($_SESSION['_cf_uploaded']);
          }
        } elseif ($mode == 'input' && isset($_SESSION['_cf_autosave'])) {
            $html = $this->restoreForm($html, $_SESSION['_cf_autosave']);
        }

        break;
      case "conf":
        $nextMode = 'send';
        $values = $this->encodeHTML($this->form, true);
        $values = $this->convertNullToStr($values, '&nbsp;');
        if (AUTO_REPLY) $values['reply_to'] = $this->getAutoReplyAddress();
        // アップロードファイル関連
        if (count($_FILES)) {
          unset($_SESSION['_cf_uploaded']);
          foreach ($_FILES as $field => $vals) {
            if ($_FILES[$field]['error'] == UPLOAD_ERR_OK) {
              if (defined('UPLOAD_TMP_PATH') && UPLOAD_TMP_PATH) {
                $new_filepath = $this->modx->config['base_path'] . "assets/snippets/cfFormMailer/tmp/" . urlencode($_FILES[$field]['name']);
              } else {
                $new_filepath = dirname($_FILES[$field]['tmp_name']) . DIRECTORY_SEPARATOR . urlencode($_FILES[$field]['name']);
              }
              move_uploaded_file($_FILES[$field]['tmp_name'], $new_filepath);
              $mime = $this->_getMimeType($new_filepath, $field);
              $_SESSION['_cf_uploaded'][$field] = array(
                'path' => $new_filepath,
                'mime' => $mime
              );
              // プレースホルダ定義
              if (substr($mime, 0, 6) == 'image/') {
                $values[$field . '.' . 'imagewidth'] = $size[0];
                $values[$field . '.' . 'imageheight'] = $size[1];
                $values[$field . '.' . 'imagename'] = htmlspecialchars($_FILES[$field]['name'], ENT_QUOTES);
                $values[$field . '.' . 'imagetype'] = strtoupper($this->_getType($mime));
              } else {
                $values[$field . '.' . 'filetype'] = strtoupper($this->_getType($mime));
                $values[$field . '.' . 'filename'] = htmlspecialchars($_FILES[$field]['name'], ENT_QUOTES);
              }
            }
          }
        }
        $html = $this->replacePlaceHolder($html, $values);
        $html = $this->addHiddenTags($html, $this->form);
       
        // ワンタイムトークンを生成
        list($usec, $sec) = explode(' ', microtime());
        mt_srand((float) $sec + ((float) $usec * 100000) + getmypid());
        $tmp_time = gettimeofday();
        $tmp_value = mt_rand() . $tmp_time['usec'] . mt_rand(0, time());
        $token = md5($tmp_value);
        $ticket = sha1($token);
        $_SESSION['_cffm_token'] = $token;
        $html = str_replace("</form>", "<input type=\"hidden\" name=\"_ticket\" value=\"{$ticket}\" />\n</form>", $html);
        
        break;
      case "comp":
        $nextMode = '';
        $html = $this->replacePlaceHolder($html, $this->encodeHTML($this->form));
        break;
    }

    // 余った<iferror>タグ、プレースホルダを削除
    $html = preg_replace("/<iferror.*?>.+?<\/iferror>/ism", "", $html);
    $html = $this->clearPlaceHolder($html);

    // 次の処理名をフォームに付記
    if ($nextMode) {
      $html = preg_replace("/(<form.*?>)/i", "\\1<input type=\"hidden\" name=\"_mode\" value=\"".$nextMode."\" />", $html);
    }

    return $html;
  }

  /**
   * 入力値を検証
   *
   * @access public
   * @param  void
   * @return boolean result
   */
  function validate() {
    $this->formError = array();

    // 検証メソッドを取得
    if (!$tmp_html = $this->loadTemplate(TMPL_INPUT)) {
      $this->raiseError($this->convertText('入力画面テンプレートの読み込みに失敗しました'));
    }
    if ($this->parseForm($tmp_html) === false) {
      $this->raiseError($this->getError());
      return false;
    }

    foreach ($this->parsedForm as $field => $method) {

      // 初期変換
      if ($method['type'] != 'textarea') {
        // <textarea>タグ以外は改行を削除
        if (is_array($this->form[$field])) {
          array_walk($this->form[$field], create_function('&$v,$k', '$v = strtr($v, array("\r" => "", "\n" => ""));'));
        } else {
          $this->form[$field] = strtr($this->form[$field], array("\r" => "", "\n" => ""));
        }
      }
      $methods = explode(",", $method['method']);

      // 入力必須項目のチェック
      if ($method['required']) {
        if ($method['type'] == 'file') {
          if ( !(isset($_SESSION['_cf_uploaded'][$field]) && is_file($_SESSION['_cf_uploaded'][$field]['path'])) && (!isset($_POST['return']) && empty($_FILES[$field]['tmp_name'])) ) {
            $this->setFormError($field, $this->adaptEncoding($method['label']), "選択必須項目です");
          }
        } elseif ((is_array($this->form[$field]) && !count($this->form[$field])) || $this->form[$field] == '') {
          $this->setFormError($field, $this->adaptEncoding($method['label']), (($method['type'] == 'radio' || $method['type'] == 'select') ? "選択" : "入力") . "必須項目です");
        }
      }
      
      // 入力値の検証
      if (!empty($this->form[$field]) || !empty($_FILES[$field]['tmp_name']) || $this->form[$field]==='0') {
        foreach ($methods as $indiv_m) {
          preg_match("/^([^\(]+)(\(([^\)]*)\))?$/", $indiv_m, $method_name);
          // 標準メソッドを処理
          $funcName = '_def_' . $method_name[1];
          if (is_callable(array($this, $funcName))) {
            if (($result = $this->$funcName($this->form[$field], $method_name[3], $field)) !== true) {
              $this->setFormError($field, $this->adaptEncoding($method['label']), $result);
            }
          }
          // ユーザー追加メソッドを処理
          $funcName = '_validate_' . $method_name[1];
          if (is_callable($funcName)) {
            if (($result = $funcName($this->form[$field], $method_name[3])) !== true) {
              $this->setFormError($field, $this->adaptEncoding($method['label']), $this->adaptEncoding($result));
            }
          }
        }
      }
    }

    // 自動返信先メールアドレスをチェック
    if (AUTO_REPLY) {
      if (!$this->_isValidEmail($this->getAutoReplyAddress())) {
        $this->setFormError('reply_to', 'メールアドレス', '形式が正しくありません');
      }
    }

    return (!count($this->formError));
  }

  /**
   * 入力値を復元
   *
   * @param  string $html   HTML text
   * @param  array  $params 再現する値の配列
   * @return void
   */
  function restoreForm($html, $params) {
    preg_match_all("/<(input|textarea|select)(.+?)([\s\/]*?)>(.*?<\/\\1>)?/ism", $html, $match, PREG_SET_ORDER);

    // タグごとに処理
    foreach ($match as $i => $tag) {
      preg_match("/type=(\"|\')(.+?)\\1/i", $tag[0], $m_type);
      preg_match("/name=(\"|\')(.+?)\\1/i", $tag[0], $m_name);
      preg_match("/value=(\"|\')(.*?)\\1/i", $tag[0], $m_value);
      $fieldName = str_replace("[]", "", $m_name[2]);
      switch($m_type[2])
      {
        case 'submit';
        case 'reset';
        case 'image';
        case 'file';
        case 'button';
        case 'checkbox';
        case 'radio';
      $fieldType = $m_type[2];
          break;
        default:
          $fieldType = 'text';
      }

      // 復元処理しないタグ
      if ($fieldName == '_mode' || $fieldType == 'submit' || $fieldType == 'image' || $fieldType == 'file' || $fieldType == 'button') continue;

      // テキストボックス
      if ($tag[1] == 'input' && $fieldType == 'text') {
        if (count($m_value) > 1) {
          $pat = $m_value[0];
          $rep = 'value="' . $this->encodeHTML($params[$fieldName]).'"';
        } else {
          $pat = $tag[2];
          $rep = $tag[2] . ' value="' . $this->encodeHTML($params[$fieldName]).'"';
        }
      // チェックボックス
      } elseif ($tag[1] == 'input' && $fieldType == 'checkbox') {
        if ($m_value[2] == $params[$fieldName] || (is_array($params[$fieldName]) && in_array($m_value[2], $params[$fieldName]))) {
          $pat = $tag[2];
          $rep = $tag[2] . ' checked="checked"';
        }
      // ラジオボタン
      } elseif ($tag[1] == 'input' && $fieldType == 'radio') {
        if ($m_value[2] == $params[$fieldName]) {
          $pat = $tag[2];
          $rep = $tag[2] . ' checked="checked"';
        }
      // プルダウンリスト
      } elseif ($tag[1] == 'select') {
        $pat = $rep = "";
        preg_match_all("/<option(.*?)value=('|\")(.*?)\\2(.*?>)/ism", $tag[4], $tag_opt, PREG_SET_ORDER);
        if (count($tag_opt) > 1) {
          $old = $tag[0];
          foreach ($tag_opt as $opt_k => $opt_v) {
            $def_deleted = preg_replace("/selected(=('|\")selected\\2)?/ism", "", $opt_v[0]);
            $tag[0] = str_replace($opt_v[0], $def_deleted, $tag[0]);
            if ($opt_v[3] == $params[$fieldName]) {
              $tag[0] = str_replace($opt_v[0], str_replace($opt_v[4], ' selected="selected"'.$opt_v[4], $opt_v[0]), $tag[0]);
            }
          }
          $new = $tag[0];
          $html = str_replace($old, $new, $html);
        }
      // 複数行テキスト
      } elseif ($tag[1] == 'textarea') {
        if ($params[$fieldName]) {
          $pat = $tag[0];
          $rep = "<" . $tag[1] . $tag[2] . $tag[3] . ">" . $this->encodeHTML($params[$fieldName]) . "</textarea>";
        }
      }

      // HTMLタグのみを置換
      $tag_new = ($rep && $pat) ? str_replace($pat, $rep, $tag[0]) : "";
      // HTML全文を置換
      $html = ($tag_new) ? str_replace($tag[0], $tag_new, $html) : $html;
    }
    return $html;
  }

  /**
   * メール送信
   * 
   * @access public
   * @param  void
   * @return boolean 結果 
   */
  function sendMail() {
    
    // send_mail環境設定が0の場合は送信しない
    if (defined('SEND_MAIL') && !SEND_MAIL) {
      return true;
    }

    // 改行コードの設定
    if (defined('LF_STYLE') && LF_STYLE == 1) {
      $this->lf = "\r\n";
    } else {
      $this->lf = "\n";
    }

    $upload_flag = false;

    if (!count($this->form)) {
      $this->setError('フォームが取得できません');return false;
    }
    // 送信メールの文字コード
    if (defined('MAIL_CHARSET') && MAIL_CHARSET) {
      $mailCharset = MAIL_CHARSET;
    } else {
      $mailCharset = 'iso-2022-jp';
    }
    
    // 管理者メールアドレス特定
    $admin_addresses = array();
    if (defined('DYNAMIC_SEND_TO_FIELD') && DYNAMIC_SEND_TO_FIELD && count($this->dynamic_send_to)) {
        if ($this->form[DYNAMIC_SEND_TO_FIELD]) {
          $mails = explode(",", $this->dynamic_send_to[$this->form[DYNAMIC_SEND_TO_FIELD]]);
        }
    } else {
      $mails = explode(",", ADMIN_MAIL);
    }
    foreach ($mails as $buf) {
      $buf = trim($buf);
      if ($this->_isValidEmail($buf)) {
        $admin_addresses[] = $buf;
      }
    }
    
    // 本文の準備
    $additional = array(
      'senddate'    => date("Y-m-d H:i:s"),
      'adminmail'   => $admin_addresses[0],
      // Added in v0.0.6
      'sender_ip'   => $_SERVER['REMOTE_ADDR'],
      'sender_host' => gethostbyaddr($_SERVER['REMOTE_ADDR']),
      'sender_ua'   => $this->encodeHTML($_SERVER['HTTP_USER_AGENT']),
      // Added in v0.0.7
      'reply_to'    => $this->getAutoReplyAddress(),
    );
    
    $reply_to = $this->getAutoReplyAddress();

    $join = ALLOW_HTML ? '<br />' : "\n";

    // 管理者宛メールの本文生成
    if (!$tmpl = $this->loadTemplate(TMPL_MAIL_ADMIN)) {
      $this->setError('メールテンプレートの読み込みに失敗しました');
      return false;
    }
    $tmpl = str_replace(array("\r\n", "\r"), "\n", $tmpl);
    $form = $this->form;
    if (ADMIN_ISHTML) {
      $form = ALLOW_HTML ? $this->nl2br_array($form) : $this->encodeHTML($form, 'true');
    }
    $tmpl = $this->replacePlaceHolder($tmpl, ($form + $additional), $join);
    $tmpl = $this->clearPlaceHolder($tmpl);
    
    // 自動返信メールの本文生成
    if (AUTO_REPLY && $reply_to) {
      // モバイル用のテンプレート切り替え
      if (defined('TMPL_MAIL_REPLY_MOBILE') && TMPL_MAIL_REPLY_MOBILE && preg_match("/(docomo\.ne\.jp|ezweb\.ne\.jp|softbank\.ne\.jp|vodafone\.ne\.jp|disney\.ne\.jp|pdx\.ne\.jp|willcom\.com|emnet\.ne\.jp)$/", $reply_to)) {
        $template_filename = TMPL_MAIL_REPLY_MOBILE;
      } else {
        $template_filename = TMPL_MAIL_REPLY;
      }
      if (!$tmpl_u = $this->loadTemplate($template_filename)) {
        $this->setError('メールテンプレートの読み込みに失敗しました');
        return false;
      }
      $tmpl_u = str_replace(array("\r\n", "\r"), "\n", $tmpl_u);
      $form_u = $this->form;
      if (REPLY_ISHTML) {
        $form_u = ALLOW_HTML ? $this->nl2br_array($form_u) : $this->encodeHTML($form_u, 'true');
      }
      $tmpl_u = $this->replacePlaceHolder($tmpl_u, ($form_u + $additional), $join);
      $tmpl_u = $this->clearPlaceHolder($tmpl_u);
    }
    
    // 管理者宛送信
    $this->modx->loadExtension("MODxMailer");
    $pm = &$this->modx->mail;
    foreach ($admin_addresses as $v) {
        $pm->AddAddress($v);
    }
    if (defined('ADMIN_MAIL_CC') && ADMIN_MAIL_CC) {
      foreach (explode(",", ADMIN_MAIL_CC) as $v) {
        $v = trim($v);
        if ($this->_isValidEmail($v)) {
          $pm->AddCC($v);
        }
      }
    }
    if (defined('ADMIN_MAIL_BCC') && ADMIN_MAIL_BCC) {
      foreach (explode(",", ADMIN_MAIL_BCC) as $v) {
        $v = trim($v);
        if ($this->_isValidEmail($v)) {
          $pm->AddBCC($v);
        }
      }
    }
    $subject = (ADMIN_SUBJECT) ? ADMIN_SUBJECT : "サイトから送信されたメール";
    $pm->Subject = $subject;
    if (defined('ADMIN_NAME') && ADMIN_NAME) {
        $pm->FromName = $this->modx->parseText(ADMIN_NAME,$this->form);
    } else {
      $pm->FromName = '';
    }
    //$pm->From = ($reply_to ? $reply_to : ADMIN_MAIL);
    $pm->From = defined('REPLY_FROM') && REPLY_FROM ? REPLY_FROM : ADMIN_MAIL;  // #通知メールの差出人は自動返信と同様をデフォルトに。
    $pm->Sender = $pm->From;
    $pm->Body = mb_convert_encoding($tmpl, $mailCharset, CHARSET);
    $pm->Encoding = '7bit';
    // ユーザーからのファイル送信
    if (isset($_SESSION['_cf_uploaded']) && count($_SESSION['_cf_uploaded'])) {
      $upload_flag = true;
      foreach ($_SESSION['_cf_uploaded'] as $attach_file) {
        if (is_file($attach_file['path'])) {
          $filename = urldecode(basename($attach_file['path']));
          $pm->AddAttachment($attach_file['path'], mb_convert_encoding($filename, $mailCharset, CHARSET));
        }
      }
    }
    if ($pm->Send() == false) {
      $errormsg = 'メール送信に失敗しました::' . $pm->ErrorInfo;
      $this->setError($errormsg);
      $vars = var_export($pm,true);
      $vars = nl2br(htmlspecialchars($vars));
      $this->modx->logEvent(1, 3,$errormsg.$vars);
      return false;
    } else {
        if(isset($_SESSION['_cf_autosave'])) unset($_SESSION['_cf_autosave']);
    }

    // 自動返信
    if (AUTO_REPLY && $reply_to) {
      $this->modx->loadExtension("MODxMailer");
      $pm = &$this->modx->mail;
      $reply_from = defined('REPLY_FROM') && REPLY_FROM ? REPLY_FROM : $admin_addresses[0];
      $this->modx->loadExtension("MODxMailer");
      $pm = &$this->modx->mail;
      $pm->AddAddress($reply_to);
      $subject = (REPLY_SUBJECT) ? REPLY_SUBJECT : "自動返信メール";
      $pm->Subject = $subject;
      $pm->FromName = REPLY_FROMNAME;
      $pm->From = $reply_from;
      $pm->Sender = $reply_from;
      $pm->Body = mb_convert_encoding($tmpl_u, $mailCharset, CHARSET);
      $pm->Encoding = '7bit';
      // 添付ファイル処理
      if (defined('ATTACH_FILE') && ATTACH_FILE && @file_exists(ATTACH_FILE)) {
        if (defined('ATTACH_FILE_NAME') && ATTACH_FILE_NAME) {
          $pm->AddAttachment(ATTACH_FILE, mb_convert_encoding(ATTACH_FILE_NAME, $mailCharset, CHARSET));
        } else {
          $pm->AddAttachment(ATTACH_FILE);
        }
      }
      // ユーザーからのファイル送信
      if ($upload_flag) {
        foreach ($_SESSION['_cf_uploaded'] as $attach_file) {
          if (is_file($attach_file['path'])) {
            $filename = urldecode(basename($attach_file['path']));
            $pm->AddAttachment($attach_file['path'], mb_convert_encoding($filename, $mailCharset, CHARSET));
          }
        }
      }

      $send_flag = $pm->Send();
      
      // 送信したファイルを削除
      if ($upload_flag) {
        foreach ($_SESSION['_cf_uploaded'] as $attach_file) {
          unlink($attach_file['path']);
        }
        unset($_SESSION['_cf_uploaded']);
      }

      if (!$send_flag) {
        $errormsg = 'メール送信に失敗しました::' . $pm->ErrorInfo;
        $this->setError($errormsg);
        $vars = var_export($pm,true);
        $vars = nl2br(htmlspecialchars($vars));
        $this->modx->logEvent(1, 3,$errormsg.$vars);
        return false;
      }
    }
    
    return true;
  }

  /**
   * mb_encode_mimeheaderのバグ対策
   *
   * @See http://www.php.net/manual/ja/function.mb-encode-mimeheader.php#34532
   */
  function _encodeMimeHeader($text, $encode = 'iso-2022-jp', $flag = true) {
    $pos = 0;
    $split = 14;  // ( 76byte - header20byte ) / 133% / 3byte
    $_string = '';
    while ($pos < mb_strlen($text, CHARSET)) {
      $output = mb_strimwidth($text, $pos, $split, "", CHARSET);
      $pos += mb_strlen($output, CHARSET);
      $_string .= (($_string) && $flag ? $this->lf . " " : (!$flag ? " " : "")) . "=?{$encode}?B?" . base64_encode(mb_convert_encoding($output, $encode, CHARSET)) . '?=';
    }
    return $_string;
  }

  /**
   * トークンをチェック
   * 
   * @access public
   * @param  void
   * @return boolean 結果
   */
  function isValidToken() {
    $token = @$_SESSION['_cffm_token'];
    unset($_SESSION['_cffm_token']);
    return (sha1($token) == $_POST['_ticket']);
  }

  /**
   * すでに送信済みかどうかをチェック
   * 
   * @access public
   * @param  void
   * @return boolean 結果
   */
  function isMultiple() {
    return ($this->form === $_SESSION['_cffm_recently_send']);
  }

  /**
   * <iferror></iferror> タグの処理
   * 
   * @access private
   * @param  string $html   HTML text of a substitution object
   * @param  array  $errors error messages
   * @return string HTML text after substitution
   */
  function assignErrorTag($html, $errors) {
    if (!is_array($errors)) {return $html;}
    preg_match_all("/<iferror\.?([^>]+?)?>(.+?)<\/iferror>/ism", $html, $match, PREG_SET_ORDER);
    if (count($match)) {
      foreach ($match as $tag) {
        if (!empty($tag[1])) {
          // グルーピングされたタグの処理
          if (preg_match("/^\((.+?)\)$/", $tag[1], $g_match)) {
            $groups = explode(",", $g_match[1]);
            $isErr = 0;
            foreach($groups as $group) {
              $group = strtr($group, array(" " => ""));
              $isErr = ($errors['error.' . $group]) ? 1 : $isErr;
            }
            if ($isErr) {
              $html = str_replace($tag[0], $tag[2], $html);
            }
          // 個別タグの処理
          } elseif (isset($errors['error.' . $tag[1]])) {
            $html = str_replace($tag[0], $tag[2], $html);
          }
        } else {
          // エラー全体の処理
          if (count($errors)) {
            $html = str_replace($tag[0], $tag[2], $html);
          }
        }
      }
    }
    return $html;
  }

  /**
   * エラーのあるフォーム項目にクラスセレクタを付加
   *
   * @access private
   * @param string $html   付加対象のHTML文書
   * @param array  $errors フォームエラーメッセージ
   * @return string 処理後のHTML文書
   */
  function assignErrorClass($html, $errors) {
    if (!defined('INVALID_CLASS') || INVALID_CLASS == '') return $html;

    if (count($errors) > 1) {
      // エラーのあるフィールド名リストを作成
      if (isset($errors['errors'])) unset($errors['errors']);
      $keys = array_unique(array_keys($errors));
      $keys = array_map(create_function('$a', 'return str_replace("error.", "", $a);'), $keys);

      foreach ($keys as $field) {
        $pattern = "#<(input|textarea|select)[^>]*?name=(\"|\'){$field}\\2[^/>]*/?>#im";
        if (preg_match_all($pattern, $html, $match, PREG_SET_ORDER)) {
          foreach ($match as $m) {
            // クラスを定義済みの場合は最後に追加
            if (preg_match("/class=(\"|\')(.+?)\\1/", $m[0], $match_classes)) {
              $newClass = "class=" . $match_classes[1] . $match_classes[2] . " " . INVALID_CLASS . $match_classes[1];
              $rep = str_replace($match_classes[0], $newClass, $m[0]);
            // そうでなければ class 要素を追加
            } else {
              $rep = preg_replace("#\s*/?>$#", "", $m[0]) . ' class="' . INVALID_CLASS .'"' . ($m[1] == 'input' ? " /" : "") . ">";
            }
            $html = str_replace($m[0], $rep, $html);
          }
        }
      }
    }
    return $html;
  }

  /**
   * サイトで使用する文字コードに変換
   * 
   * @access private
   * @param  mixed $text 変換するテキスト
   * @return mixed 変換後のテキスト
   */
  function convertText($text) {
    if (is_array($text)) {
      foreach ($text as $k => $v) {
        $text[$k] = $this->convertText($v);
      }
    } elseif (strtolower(CHARSET) != 'utf-8') {
      $text = mb_convert_encoding($text, CHARSET, 'utf-8');
    }
    return $text;
  }

  /**
   * 文字コードをシステムに合わせる
   *  - （UTF-8 以外の文字コードを UTF-8 に変換）
   * 
   * @param  string 変換するテキスト
   * @return string 変換後のテキスト 
   */
  function adaptEncoding($text) {
    if (is_array($text)) {
      $text = array_map($this->adaptEncoding, $text);
    } elseif (strtolower(CHARSET) != 'utf-8') {
      $text = mb_convert_encoding($text, 'utf-8', CHARSET);
    }
    return $text;
  }

  /**
   * HTMLエンコード
   *
   * @access private
   * @param  mixed   $text  変換するテキスト（または配列）
   * @param  boolean $nl2br TRUE の場合、改行コードを<br />に変換　（Default: FALSE）
   * @return mixed 変換後のテキストまたは配列
   */
  function encodeHTML($text, $nl2br = '') {
    if (is_array($text)) {
      foreach ($text as $k => $v) {
        $text[$k] = $this->encodeHTML($v, $nl2br);
      }
    } else {
      $text = htmlspecialchars($text, ENT_QUOTES);
      $text = $this->convertjp($text);
      $text = ($nl2br) ? nl2br($text) : $text;
    }
    return $text;
  }

function convertjp($text)
{
	$text = mb_convert_kana($text, 'KV', 'UTF-8');
	
	$char['①'] = '(1)';
	$char['②'] = '(2)';
	$char['③'] = '(3)';
	$char['④'] = '(4)';
	$char['⑤'] = '(5)';
	$char['⑥'] = '(6)';
	$char['⑦'] = '(7)';
	$char['⑧'] = '(8)';
	$char['⑨'] = '(9)';
	$char['⑩'] = '(10)';
	$char['⑪'] = '(11)';
	$char['⑫'] = '(12)';
	$char['⑬'] = '(13)';
	$char['⑭'] = '(14)';
	$char['⑮'] = '(15)';
	$char['⑯'] = '(16)';
	$char['⑰'] = '(17)';
	$char['⑱'] = '(18)';
	$char['⑲'] = '(19)';
	$char['⑳'] = '(20)';
	$char['Ⅰ'] = 'I';
	$char['Ⅱ'] = 'II';
	$char['Ⅲ'] = 'III';
	$char['Ⅳ'] = 'IV';
	$char['Ⅴ'] = 'V';
	$char['Ⅵ'] = 'VI';
	$char['Ⅶ'] = 'VII';
	$char['Ⅷ'] = 'VIII';
	$char['Ⅸ'] = 'IX';
	$char['Ⅹ'] = 'X';
	$char['㍉'] = 'ミリ';
	$char['㌔'] = 'キロ';
	$char['㌢'] = 'センチ';
	$char['㍍'] = 'メートル';
	$char['㌘'] = 'グラム';
	$char['㌧'] = 'トン';
	$char['㌃'] = 'アール';
	$char['㌶'] = 'ヘクタール';
	$char['㍑'] = 'リットル';
	$char['㍗'] = 'ワット';
	$char['㌍'] = 'カロリー';
	$char['㌦'] = 'ドル';
	$char['㌣'] = 'セント';
	$char['㌫'] = 'パーセント';
	$char['㍊'] = 'ミリバール';
	$char['㌻'] = 'ページ';
	$char['㎜'] = 'mm';
	$char['㎝'] = 'cm';
	$char['㎞'] = 'km';
	$char['㎎'] = 'mg';
	$char['㎏'] = 'kg';
	$char['㏄'] = 'cc';
	$char['㎡'] = '平方メートル';
	$char['㍻'] = '平成';
	$char['〝'] = '「';
	$char['〟'] = '」';
	$char['№'] = 'No.';
	$char['㏍'] = 'k.k.';
	$char['℡'] = 'Tel';
	$char['㊤'] = '(上)';
	$char['㊥'] = '(中)';
	$char['㊦'] = '(下)';
	$char['㊧'] = '(左)';
	$char['㊨'] = '(右)';
	$char['㈱'] = '(株)';
	$char['㈲'] = '(有)';
	$char['㈹'] = '(代)';
	$char['㍾'] = '明治';
	$char['㍽'] = '大正';
	$char['㍼'] = '昭和';
	return str_replace(array_keys($char), array_values($char), $text);
}

  /**
   * 改行コードを<br />タグに変換
   * 
   * @param mixed $text 変換するテキスト（または配列）
   * @return mixed 変換後のテキストまたは配列 
   */
  function nl2br_array($text) {
    if (is_array($text)) {
      $text = array_map(array($this, "nl2br_array"), $text);
    } else {
      $text = nl2br($text);
    }
    return $text;
  }

  /**
   * テンプレートチャンクの読み込み
   *
   * @access private
   * @param  string $name チャンク名・またはリソースID
   * @return string 読み込んだデータ
   */
  function loadTemplate($name) {
    if (preg_match('/^[1-9][0-9]*$/', $name)) {
      $html = ($resource = $this->modx->getDocumentObject('id', $name)) ? $resource['content'] : false; // thanks to yama
    } else {
      $html = ($tmpl = $this->modx->getChunk($name)) ? $tmpl : false;
    }
    if ($html) {
      if(strpos($html,'[!')!==false) $html = str_replace(array('[!','!]'),array('[[',']]'),$html);
      $html = $this->modx->parseDocumentSource($html);
      return $html;
    } else {
      $this->setError('Chunk read error');
      return false;
    }
  }

  /**
   * プレースホルダを置換
   *
   * @access private
   * @param  string $text HTMLテキスト
   * @param  array  $params 置換するデータ
   *                         (プレースホルダ名) => (値)
   * @param  string $join 値が配列の場合に連結に使用する文字列
   * @return string プレースホルダが置換された文字列
   */
  function replacePlaceHolder($text, $params, $join = '<br />') {
    global $modx;
    
    if (!is_array($params) || !$text) return false;

    preg_match_all("/\[\+([^\+\|]+)(\|(.*?)(\((.+?)\))?)?\+\]/is", $text, $match, PREG_SET_ORDER);
    if (!count($match)) return $text;

    //1.0.15J以降 $modx->config['output_filter']は廃止
    $toFilter = true;
    //旧バージョン用
    if(isset($modx->config['output_filter']) &&$modx->config['output_filter']==='0') $toFilter = false;

    if($toFilter) $modx->loadExtension('PHx') or die('Could not load PHx class.');
    
    // 基本プレースホルダ
    $replaceKeys = array_keys($params);
    foreach ($match as $m) {
      if($toFilter && strpos($m[1],':')!==false)
          list($m[1],$modifiers) = explode(':', $m[1], 2);
      else $modifiers = false;
      if (!in_array($m[1], $replaceKeys)) continue;
      $fType = $m[3];
      $val = $params[$m[1]];
      if($toFilter && $modifiers!==false)
      {
          if($val==='&nbsp;') $val = '';
          $val = $modx->filter->phxFilter($m[1],$val,$modifiers);
          if($val==='') $val = '&nbsp;';
      }
      $rep = "";
      
      // テキストフィルターの処理
      if (!empty($fType)) {
        if (is_callable(array($this, '_f_' . $fType))) {
          $funcName = '_f_' . $fType;
          $rep = $this->$funcName($val, $m[5]);
        } elseif (is_callable('_filter_' . $fType)) {
          $funcName = '_filter_' . $fType;
          $rep = $funcName($val, $m[5]);
        }
      // フィルター無し
      } else {
        $rep = (is_array($val)) ? implode($join, $val) : $val;
      }
      
      $text = str_replace($m[0], $rep, $text);
    }
    
    return $text;
  }

  /**
   * 全てのプレースホルダを削除
   * 
   * @param string $text 対象となるHTML
   * @return string [+variable_name+]削除後のHTML
   */
  function clearPlaceHolder($text) {
    return preg_replace("/\[\+.+?\+\]/ism", "", $text);
  }

  /**
   * NULL 値を文字列に変換
   *
   * @param mixed $data 変換するデータ
   * @param string $string 変換される文字列(Default: &nbsp;)
   * @return mixed 変換後のデータ
   */
  function convertNullToStr($data, $string = '&nbsp;') {
    if (is_array($data)) {
      foreach ($data as $k => $v) {
        $data[$k] = $this->convertNullToStr($v, $string);
      }
    } else {
      $data = (empty($data)) ? $string : $data;
    }
    return $data;
  }

  /**
   * 認証コード用画像の URI を取得
   * 
   * @access private
   * @param void
   * @return string 認証コード画像の URI
   */
  function getCaptchaUri() {
    if(is_file($this->modx->config['base_path'] . 'captcha.php'))
      $captchalib = 'captcha.php';
    elseif(is_file(MODX_MANAGER_PATH.'media/captcha/veriword.php'))
      $captchalib = 'index.php?get=captcha';
    else
      $captchalib = 'manager/includes/veriword.php?tmp=' . mt_rand();
    return $this->modx->config['base_url']  . $captchalib;
  }

  /**
   * <form></form>タグ内に入力値を hidden 属性で埋め込む
   * 
   * @access private
   * @param  string $html 対象となるHTML文書
   * @param  array  $form 埋め込むデータ
   * @return string 埋め込み後のHTML文書
   */
  function addHiddenTags($html, $form) {
    if (!is_array($form)) return $html;
    if (isset($form['_mode'])) unset($form['_mode']);
    $tag = array();
    foreach ($form as $k => $v) {
      if (is_array($v)) {
        foreach ($v as $subv) {
          $tag[] = sprintf('<input type="hidden" name="%s[]" value="%s" />',$this->encodeHTML($k),$this->encodeHTML($subv));
        }
      } else {
        $tag[] = sprintf('<input type="hidden" name="%s" value="%s" />',$this->encodeHTML($k),$this->encodeHTML($v));
      }
    }
    return str_replace("</form>", join("\n",$tag) . "</form>", $html);
  }

  /**
   * 投稿されたデータの初期処理
   *
   * @access private
   * @param  array $array データ 
   * @return array 初期処理の終わったデータ
   */
  function getFormVariables($array) {
    if (!is_array($array)) return;
    $ret = array();
    foreach ($array as $k => $v) {
      if (!is_int($k) && ($k == '_mode' || $k == '_ticket')) continue;
      if (is_array($v)) {
        $v = $this->getFormVariables($v);
      } else {
        if (get_magic_quotes_gpc()) {
          $v = stripslashes($v);
        }
        $v = str_replace("\0", "", $v);
        $v = strtr($v, array("\r\n" => "\n", "\r" => "\n"));
        $v = preg_replace("/\n+$/m", "\n", $v);
      }
      $ret[$k] = $v;
    }
    return $ret;
  }

  /**
   * <form></form>タグ内を解析し、検証メソッド等を取得する
   *
   * @access private
   * @param  string $html 解析対象のHTML文書
   * @return boolean 解析失敗の場合は false
   */
  function parseForm($html) {
    $html = $this->extractForm($html, '');
    if ($html === false) {
      return false;
    }

    $methods = array();
    preg_match_all("/<(input|textarea|select).*?name=(\"|\')(.+?)\\2.*?>/i", $html, $match, PREG_SET_ORDER);
    foreach ($match as $v) {
      
      // 項目名を取得
      $fieldName = str_replace("[]", "", $v[3]);
      
      // 項目タイプを取得
      if ($v[1] == 'input') {
        preg_match("/type=(\"|\')(.+?)\\1/", $v[0], $t_match);
        $type = $t_match[2];
      } else {
        $type = $v[1];
      }
      
      // 検証メソッドを取得
      if (preg_match("/valid=(\"|\')(.+?)\\1/", $v[0], $v_match)) {
        list($required, $method, $param) = explode(":", $v_match[2]);
      } else {
        $required = $method = $param = "";
      }
      
      // 項目名の取得
      if ($param) {
        $label = $param;
      } else {
        // label を取得  (from v0.0.4)
        if (preg_match("/id=(\"|\')(.+?)\\1/", $v[0], $l_match)) {
          $pattern = "/<label for=(\"|\'){$l_match[2]}\\1.*>(.+?)<\/label>/";
          if (preg_match($pattern, $html, $match_label)) {
            $label = $match_label[2];
          } else {
            $label = '';
          }
        }
      }
      
      if (!isset($methods[$fieldName])) {
        $methods[$fieldName] = array('type' => $type, 'required' => $required, 'method' => $method, 'param' => $param, 'label' => $label);
      }
    }
    $this->parsedForm = $methods;
    
    // 送信先動的変更のためのデータ取得(from v1.3)
    if (defined('DYNAMIC_SEND_TO_FIELD') && DYNAMIC_SEND_TO_FIELD && isset($methods[DYNAMIC_SEND_TO_FIELD])) {
      $m_options = array();
      if ($methods[DYNAMIC_SEND_TO_FIELD]['type'] == 'select') {
        preg_match("/<select.*?name=(\"|\')" . DYNAMIC_SEND_TO_FIELD . "\\1.*?>(.+?)<\/select>/ims", $html, $matches);
        if ($matches[2]) {
          preg_match_all("/<option.+?<\/option>/ims", $matches[2], $m_options);
        }
      } elseif ($methods[DYNAMIC_SEND_TO_FIELD]['type'] == 'radio') {
        preg_match_all("/<input.*?name=(\"|\')" . DYNAMIC_SEND_TO_FIELD . "\\1.*?>/im", $html, $m_options);
      }
      if ($m_options[0] && count($m_options[0])) {
        foreach ($m_options[0] as $m_option) {
          preg_match_all("/(value|sendto)=(\"|\')(.+?)\\2/i", $m_option, $buf, PREG_SET_ORDER);
          if ($buf && count($buf) == 2) {
            $key_value = ($buf[0][1] == 'value') ? $buf[0][3] : $buf[1][3];
            $this->dynamic_send_to[$key_value] = ($buf[0][1] == 'value') ? $buf[1][3] : $buf[0][3];
          }
        }
      }
    }
    return true;
  }

  /**
   * 指定したIDのフォームタグ内のみ抽出
   * 
   * @access private
   * @param string $html 対象のHTML文書
   * @param string $id   抽出対象のフォームID
   * @return mixed 抽出されたHTML文書（失敗の場合は FALSE）
   */
  function extractForm($html, $id = '') {
    if (preg_match("/<form.+?>([\S\s]+)<\/form>/m", $html, $match_form)) {
      $ret = $match_form[1];
    } else {
      $this->setError('&lt;form&gt;タグが見つかりません');
      $ret = false;
    }
    return $ret;
  }

  /**
   * 入力値をセッションに待避
   * 
   * @access public
   * @param  void
   * @return void
   */
  function storeDataInSession() {
    $_SESSION['_cffm_recently_send'] = array();
    foreach ($this->form as $k => $v) {
      if ($k != '_mode' && $k != '_ticket') {
        $_SESSION['_cffm_recently_send'][$k] = $v;
      }
    }
    return;
  }

  /**
   * エラーメッセージを設定
   *
   * @access private
   * @param  string $mes メッセージ
   * @return void
   */
  function setError($mes) {
    $this->error_message = $mes;
    return;
  }

  /**
   * エラーメッセージを取得
   *
   * @access public
   * @param  void
   * @return string 取得したメッセージ
   */
  function getError() {
    return $this->convertText($this->error_message);
  }

  /**
   * 検証エラーを設定
   *
   * @access private
   * @param  string $field   エラーのあるフィールド名
   * @param  string $label  エラーのある項目名
   * @param  string $message 割り当てるメッセージ
   * @return void
   */
  function setFormError($field, $label, $message) {
    $this->formError[$field][] = array('label' => $label, 'text' => $message);
    return true;
  }

  /**
   * 検証エラーを取得
   *
   * @access public
   * @return array 取得したメッセージ（プレースホルダ用に整形。個別用の error.field_name と 一括用の errors の両方が返される）
   */
  function getFormError() {
    if (count($this->formError) < 1) {
      return array();
    }
    $ret = array();
    foreach ($this->formError as $field => $val) {
      foreach ($val as $mes) {
        $label = $mes['label'] ? $this->convertText($mes['label']) : $field;
        $ret['error.' . $field][] = $this->convertText($mes['text']);
        $ret['errors'][] = "[" . $label . "] " . $this->convertText($mes['text']);
      }
    }
    return $ret;
  }

  /**
   * エラー制御
   *
   * @access public
   * @param  string  $mes   エラーメッセージ
   * @param  boolean $ifDie TRUE の場合、プロセス終了
   * @return void
   */
  function raiseError($mes, $ifDie = false) {
    return "<p style=\"color:#cc0000;background:#fff;font-weight:bold;\">SYSTEM ERROR::{$mes}</p>";
  }

  /**
   * バージョン番号を取得
   * 
   * @return string バージョン番号
   */
  function getVersion() {
    return $this->version;
  }

  /**
   * 自動返信先メールアドレスを取得
   *
   * @return string メールアドレス
   */
  function getAutoReplyAddress() {
    if (!AUTO_REPLY) return '';
    $reply_to = '';
    $tmp = explode("+", REPLY_TO);
    foreach ($tmp as $t) {
      $t = trim($t);
      $reply_to .= ($t == '@') ? '@' : $this->form[$t];
    }
    return $reply_to;
  }

  /**
   * メールアドレス妥当性チェック
   *
   * @access private
   * @param string $addr チェックするメールアドレス
   * @return boolean 結果
   */
  function _isValidEmail($addr) {
    return preg_match("/^(?:[a-z0-9+_-]+?\.)*?[a-z0-9_+-]+?@(?:[a-z0-9_-]+?\.)*?[a-z0-9_-]+?\.[a-z0-9]{2,5}$/i", $addr);
  }

  /**
   * ファイルのMIMEタイプを取得
   * class.upload.php 使用推奨
   *
   * @param string  $filename MIMEタイプを調べるファイル
   * @param string  $field  アップロードされたフィールド名
   * @return string MIMEタイプ。失敗の場合は false 
   */
  function _getMimeType($filename, $field = '') {
    $mime = false;
    if (class_exists('upload')) {
      // class.upload.php使用
      $up = new upload($filename);
      if ($up->uploaded) {
        $mime = $up->file_src_mime;
      }
    } else {
      // class.upload.php未使用。image以外の結果はあまり信用できない
      $size = @getimagesize($filename);
      if ($size === false) {
        if (isset($_FILES[$field]['type']) && $_FILES[$field]['type']) {
          $mime = $_FILES[$field]['type'];
        }
      } else {
        $mime = $size['mime'];
      }
    }
    return $mime;
  }

  /**
   * MIMEタイプからファイルの識別子を取得
   *
   * @param string  MIMEタイプ
   * @return  string  タイプ
   */
  function _getType($mime) {
    $mime_list = array(
      'image/gif'   => 'gif',
      'image/jpeg'  => 'jpg',
      'image/pjpeg' => 'jpg',
      'image/png'   => 'png',
      'application/pdf' => 'pdf',
      'text/plain'  => 'txt',
      'text/html'   => 'html',
      'application/msword'  => 'word'
    );
    return (isset($mime_list[$mime])) ? $mime_list[$mime] : '';
  }

  /**
   * 環境設定の読み込み
   * 
   * @param string $config_name 環境設定チャンク名 
   * @return mixed true || エラーメッセージ
   */
  function parseConfig($config_name) {
    if (!$conf = $this->loadTemplate($config_name)) return '環境設定チャンクの読み込みに失敗しました!';
    $conf = strtr($conf, array("\r\n" => "\n", "\r" => "\n"));
    $conf_arr = explode("\n", $conf);
    foreach ($conf_arr as $line) {
      if ((!preg_match("/[a-zA-Z0-9=]/", $line)) || strpos($line, "#") === 0) continue;
      list($param, $val) = explode("=", $line, 2);
      $param = trim($param);
      $val = trim($val);
      // UTF-8 以外の文字コードを変換
      if (strpos($param, 'subject') !== FALSE || strpos($param, 'name') !== FALSE) {
        $val = $this->adaptEncoding($val);
      }
      $param_list[$param] = $val;
    }

    // 必須項目チェック
    $err = array();
    if (empty($param_list['tmpl_input'])) $err[] = '`入力画面テンプレート`を指定してください';
    if (empty($param_list['tmpl_conf'])) $err[] = '`確認画面テンプレート`を指定してください';
    if (empty($param_list['tmpl_comp']) && empty($param_list['complete_redirect'])) $err[] = '`完了画面テンプレート`または`送信後遷移する完了画面リソースID`を指定してください';
    if (empty($param_list['tmpl_mail_admin'])) $err[] = '`管理者宛メールテンプレート`を指定してください';
    if ($param_list['auto_reply'] && empty($param_list['tmpl_mail_reply'])) $err[] = '`自動返信メールテンプレート`を指定してください';
    if (count($err)) {
        return implode("<br />", $this->convertText($err));
    }

    // 値の指定が無い場合はデフォルト値を設定
    if (empty($param_list['admin_mail'])) $param_list['admin_mail'] = $this->modx->config['emailsender'];
    //if (empty($param_list['admin_name'])) $param_list['admin_name'] = $this->modx->config['site_name']; // 仕様変更 in v1.0
    if (empty($param_list['auto_reply']))   $param_list['auto_reply']   = 0;
    if (empty($param_list['reply_to']))   $param_list['reply_to']   = 'email';
    if (empty($param_list['reply_fromname'])) $param_list['reply_fromname'] = $this->modx->config['site_name'];
    // Added in v0.0.5
    if (empty($param_list['vericode'])) $param_list['vericode'] = 0;
    // Added in v0.0.7
    if (empty($param_list['admin_ishtml'])) $param_list['admin_ishtml'] = 0;
    if (empty($param_list['reply_ishtml'])) $param_list['reply_ishtml'] = 0;
    if (empty($param_list['allow_html']))   $param_list['allow_html']   = 0;

    // 定数として設定
    foreach ($param_list as $key => $value) {
      define(strtoupper($key), $value);
    }
    return true;
  }
  
  
  /**
   * 送信内容をDBに保存
   * 動作にはcfFormDBモジュールが必要
   * 
   * @access public
   */
  function storeDB() {
    if (!defined('USE_STORE_DB') || !USE_STORE_DB) {
      return true;
    }

    if ($this->ifTableExists()) {
      $sql = "INSERT INTO " . $this->modx->getFullTableName('cfformdb') . '(created) VALUES(NOW())';
      $this->modx->db->query($sql);
      $newID = $this->modx->db->getInsertId();
      $rank = 0;
      foreach ($this->form as $key => $val) {
        if ($key == 'veri') {
          continue;
        }
        if (is_array($val)) {
          $val = implode(",", $val);
        }
        $sql = sprintf("INSERT INTO %s(postid,field,value,rank) VALUES(%d, '%s', '%s', %d)",
          $this->modx->getFullTableName('cfformdb_detail'),
          $newID,
          $this->modx->db->escape($key),
          $this->modx->db->escape($val),
          $rank
        );
        $this->modx->db->query($sql);
        $rank++;
      }
    } else {
      return true;
    }
  }

  /**
   * テーブルの存在確認
   * @access private
   */
  function ifTableExists() {
    $sql = "SHOW TABLES FROM " . $this->modx->db->config['dbase'] . " LIKE '%cfformdb%'";
    if ($rs = $this->modx->db->query($sql)) {
      if ($this->modx->db->getRecordCount($rs) == 2) {
        return true;
      }
    }
    return false;
  }

  /* ------------------------------------------------------------------ */
  /* 標準装備の検証メソッド
  /* ------------------------------------------------------------------ */

  /**
   * num : 数値？
   */
  function _def_num($value, $param, $field) {
    // 強制的に半角に変換します。
    $this->form[$field] = mb_convert_kana($this->form[$field], "n", CHARSET);
    return (is_numeric($this->form[$field])) ? true : '半角数字で入力してください';
  }

  /**
   * email : 正しいメールアドレス形式か？
   */
  function _def_email($value, $param, $field) {
    // 強制的に半角に変換します。
    $this->form[$field] = mb_convert_kana($this->form[$field], "a", CHARSET);
    return $this->_isValidEmail($this->form[$field]) ? true : 'メールアドレスの形式が正しくありません';
  }

  /**
   * len(min, max) : 文字数チェック
   */
  function _def_len($value, $param, $field) {
    if (preg_match("/([0-9]+)?(\-)?([0-9]+)?/", $param, $match)) {
      if ($match[1] && empty($match[2]) && empty($match[3])) {
        if (mb_strlen($value) != $match[1]) { return "{$match[1]}文字で入力してください";}
      } elseif (empty($match[1]) && $match[2] && $match[3]) {
        if (mb_strlen($value) > $match[3]) { return "{$match[3]}文字以内で入力してください";}
      } elseif ($match[1] && $match[2] && empty($match[3])) {
        if (mb_strlen($value) < $match[1]) { return "{$match[1]}文字以上で入力してください";}
      } elseif ($match[1] && $match[2] && $match[3]) {
        if (mb_strlen($value) < $match[1] || mb_strlen($value) > $match[3]) { return "{$match[1]}～{$match[3]}文字で入力してください";}
      }
    }
    return true;
  }

  /**
   * vericode : CAPTCHA による認証チェック
   *   Added in v0.0.5
   */
  function _def_vericode($value, $param, $field) {
    if (!VERICODE) return true;
    if ($_SESSION['veriword'] == $value) {
      return true;
    } else {
      $this->form[$field] = "";
      return '入力値が正しくありません';
    }
  }

  /**
   * range(min, max) : 値範囲チェック
   *   Added in v0.0.5
   */
  function _def_range($value, $param, $field) {
    $err = 0;
    if (preg_match("/([0-9-]+)?(~)?([0-9-]+)?/", $param, $match)) {
      if ($match[1] && empty($match[2]) && empty($match[3])) {
        if ($value > $match[1]) $err = 1;
      } elseif (empty($match[1]) && $match[2] && $match[3]) {
        if ($value > $match[3]) $err = 1;
      } elseif ($match[1] && $match[2] && empty($match[3])) {
        if ($value < $match[1]) $err = 1;
      } elseif ($match[1] && $match[2] && $match[3]) {
        if ($value > $match[3] || $value < $match[1]) $err = 1;
      }
      if ($err) return "入力値が範囲外です";
    }
    return true;
  }

  /**
   * sameas(field) : 同一確認
   *   Added in v0.0.6
   */
  function _def_sameas($value, $param, $field) {
    if ($value == $this->form[$param]) {
      return true;
    } else {
      unset($this->form[$field]);
      return "&laquo; " . $this->adaptEncoding($this->parsedForm[$param]['label']) . " &raquo; と一致しません";
    }
  }

  /**
   * tel : 電話番号？
   *   Added in v0.0.7
   */
  function _def_tel($value, $param, $field) {
    // 強制的に半角に変換します。
    $this->form[$field] = mb_convert_kana($this->form[$field], "a", CHARSET);
    $checkLen = (substr($this->form[$field],0,1)=='0') ? 10 : 5;
    $checkStr = preg_replace('/[^0-9]/','',$this->form[$field]);
    return (preg_match("/[^0-9\-+]/", $checkStr) || strlen($checkStr) < $checkLen) ? '半角数字とハイフンで正しく入力してください' : true;
  }
  
  /**
   * tel : 郵便番号
   *   Added in v1.3.x
   */
  function _def_zip($value, $param, $field) {
    // 強制的に半角に変換します。
    $this->form[$field] = mb_convert_kana($this->form[$field], 'as', CHARSET);
    $this->form[$field] = preg_replace('/[^0-9]/','',$this->form[$field]);
    $str = $this->form[$field];
    if(strlen($str) !== 7) return '半角数字とハイフンで正しく入力してください';
    $this->form[$field] = substr($str,0,3) . '-' . substr($str,-4);
    return true;
  }
  
  /**
   * allowtype(type) : アップロードを許可するファイル形式
   *   Added in v1.0
   */
  function _def_allowtype($value, $param, $field) {
    $flag = true;
    if (!isset($_FILES[$field]['tmp_name']) || empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
      return true;
    }
    $allow_list = explode("|", $param);
    if (!count($allow_list)) {
      return true;
    }
    if (($mime = $this->_getMimeType($_FILES[$field]['tmp_name'], $field)) === false) {
      $flag = false;
    } else {
      $type = $this->_getType($mime);
      if (!$type || !in_array($type, $allow_list)) {
        $flag = false;
      }
    }
    return $flag ? true : "許可されたファイル形式ではありません";
  }

  /**
   * allowsize(size) : アップロードを許可する最大ファイルサイズ
   *   Added in v1.0
   */
  function _def_allowsize($value, $param, $field) {
    if (!isset($_FILES[$field]['tmp_name']) || empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
      return true;
    }
    if (!$param || !is_numeric($param)) {
      return false;
    }
    $size = @stat($_FILES[$field]['tmp_name']);
    if ($size === false) {
      return 'ファイルのアップロードに失敗しました';
    } else {
      return ($size['size'] <= $param * 1024) ? true : $param . "キロバイト以内のファイルを指定してください";
    }
  }
  
  /**
   * convert(param)：半角英数字に変換
   *   See: http://jp2.php.net/manual/ja/function.mb-convert-kana.php
   *   Added in v1.2
   */
  function _def_convert($value, $param, $field) {
    if (!$param) {
      $param = "K";
    }
    $this->form[$field] = mb_convert_kana($this->form[$field], $param, CHARSET);
    return true;  // 常にtrueを返す
  }
  
  /**
   * url(string)：URL値検証
   *   Added in v1.2
   */
  function _def_url($value, $param, $field) {
    return preg_match("/^https?:\/\/.+\..+/", $value);
  }


  /* ------------------------------------------------------------------ */
  /* 標準装備のフィルターメソッド
  /* ------------------------------------------------------------------ */
  
  /**
   * implode : 文字列結合
   */
  function _f_implode($text, $param) {
    return (is_array($text)) ? implode(str_replace("\\n", "\n", $param), $text) : $text;
  }

  /**
   * implodetag(tag) : HTMLタグで文字列結合
   */
  function _f_implodetag($text, $param) {
    if (!is_array($text)) $text = array($text);
    $ret = "";
    foreach ($text as $v) {
      $ret .= "<{$param}>{$v}</{$param}>";
    }
    return $ret;
  }

  /**
   * num : 数値のフォーマット （※PHP関数 number_format() と同様）
   */
  function _f_num($text, $param) {
    return (is_array($text)) ? array_map($this->_f_num, $text, $param) : number_format($text);
  }

  /**
   * dateformat(format) : 日付のフォーマット （※PHP関数 strftime() と同様）
   */
  function _f_dateformat($text, $param) {
    return (is_array($text)) ? array_map($this->_f_dateformat, $text, $param) : strftime($param, strtotime($text));
  }

  /**
   * sprintf(format) : テキストのフォーマット （※PHP関数 sprintf() と同様）
   */
  function _f_sprintf($text, $param) {
    return (is_array($text)) ? array_map($this->_f_sprintf, $text, $param) : sprintf($param, $text);
  }
}

?>
