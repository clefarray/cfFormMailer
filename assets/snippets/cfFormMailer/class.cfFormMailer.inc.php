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

class Class_cfFormMailer {

    public $cfg = array();

    /**
     * postされたデータ
     * @private array
     */
    private $form;

    /**
     * 検証エラーのメッセージ
     * @private array
     */
    private $formError;

    /**
     * システムエラーメッセージ
     * @private string
     */
    private $error_message;

    /**
     * フォームの valid 要素
     * @private array
     */
    private $parsedForm;

    /**
     * 送信先動的変更のための送信先情報
     * @private array
     */
    private $dynamic_send_to = array();

    /**
     * バージョン番号
     * @public string
     */
    public $version = '1.6';

    private $lf = "\n";

    private $sysError;

    /**
     * コンストラクタ
     *
     */
    public function __construct(&$modx) {

        // 変数初期設定
        $this->form = array();
        $this->error_message ='';
        $this->formError = array();

        // postされたデータを読み取り
        $this->form = $this->getFormVariables($_POST);

        // uploadクラス読み込み
        if (is_file(__DIR__ . '/class.upload.php')) {
            include_once __DIR__ . '/class.upload.php';
        }
    }

    /**
     * 現在のモードからHTML文書を取得・作成
     *
     * @access public
     * @param  string $mode 現在のモード(input / conf / comp / error / return)
     * @return string HTML文書
     */
    public function createPageHtml($mode) {

        if ($mode === 'comp' && $this->config('complete_redirect')) {
            if(isset($_SESSION['_cf_autosave'])) {
                unset($_SESSION['_cf_autosave']);
            }
            if (preg_match('/^[1-9][0-9]*$/', $this->config('complete_redirect'))) {
                evo()->sendRedirect(evo()->makeUrl($this->config('complete_redirect')));
                exit;
            } else {
                evo()->sendRedirect($this->config('complete_redirect'));
            }
            exit;
        }

        // ページテンプレート読み込み
        if (in_array($mode,array('input','error','return'))) {
            $text = $this->loadTemplate($this->config('tmpl_input'));
        } elseif($mode === 'conf') {
            $text = $this->loadTemplate($this->config('tmpl_conf'));
        } elseif($mode === 'comp') {
            $text = $this->loadTemplate($this->config('tmpl_comp'));
        } else {
            return false;
        }

        if ($text === false) {
            return false;
        }

        // ポストされた内容を一時的に退避（事故対策）
        if ($mode === 'error' || $mode === 'conf') {
            if($this->config('autosave')) {
                $_SESSION['_cf_autosave'] = $this->form;
            }
        }

        // アクションごとの処理
        switch ($mode) {
            case 'input':
            case 'error':
            case 'return':
                $nextMode = 'conf';

                // CAPTCHA  # Added in v0.0.5
                if ($this->config('vericode')) {
                    $text = $this->replacePlaceHolder($text, array('verimageurl' => $this->getCaptchaUri()));
                }

                // 検証フィールドを削除
                $text = preg_replace("/\svalid=([\"']).+?\\1/i", '', $text);
                // 送信先情報を削除
                $text = preg_replace("/\ssendto=([\"']).+?\\1/i", '', $text);

                // エラーの場合は入力値とエラーメッセージを付記
                if ($mode === 'error') {
                    $text = $this->assignErrorTag($text,$this->getFormError());
                    $text = $this->assignErrorClass($text, $this->getFormError());
                    $text = $this->replacePlaceHolder($text, $this->getFormError());
                    $text = $this->restoreForm($text, $this->form);
                    // 「戻り」の場合は入力値のみ復元
                } elseif ($mode === 'return') {
                    $text = $this->restoreForm($text, $this->form);
                    // アップロード済みのファイルを削除
                    if (is_array($_SESSION['_cf_uploaded']) && count($_SESSION['_cf_uploaded'])) {
                        foreach ($_SESSION['_cf_uploaded'] as $filedata) {
                            @unlink($filedata['path']);
                        }
                        unset($_SESSION['_cf_uploaded']);
                    }
                } elseif ($mode === 'input' && isset($_SESSION['_cf_autosave'])) {
                    $text = $this->restoreForm($text, $_SESSION['_cf_autosave']);
                }
                break;
            case 'conf':
                $nextMode = 'send';
                $values = $this->encodeHTML($this->form, true);
                $values = $this->convertNullToStr($values, '&nbsp;');
                if ($this->config('auto_reply')) {
                    $values['reply_to'] = $this->getAutoReplyAddress();
                }
                // アップロードファイル関連
                if (is_array($_FILES) && count($_FILES)) {
                    unset($_SESSION['_cf_uploaded']);
                    foreach ($_FILES as $field => $vals) {
                        if ($_FILES[$field]['error'] != $this->config('upload_err_ok')) {
                            continue;
                        }
                        if ($this->config('upload_tmp_path')) {
                            $new_filepath = sprintf(
                                '%stmp/%s'
                                , CFM_PATH
                                , urlencode($_FILES[$field]['name']
                                )
                            );
                        } else {
                            $new_filepath = sprintf(
                                '%s/%s'
                                , dirname($_FILES[$field]['tmp_name'])
                                , urlencode($_FILES[$field]['name']
                                )
                            );
                        }
                        evo()->move_uploaded_file($_FILES[$field]['tmp_name'], $new_filepath);
                        $mime = $this->_getMimeType($new_filepath, $field);
                        $_SESSION['_cf_uploaded'][$field] = array(
                            'path' => $new_filepath,
                            'mime' => $mime
                        );
                        // プレースホルダ定義
                        $name =  evo()->htmlspecialchars($_FILES[$field]['name'], ENT_QUOTES);
                        $type = strtoupper($this->_getType($mime));
                        if (strpos($mime, 'image/') === 0) {
                            $values[sprintf('%d.imagename', $field)]   = $name;
                            $values[sprintf('%d.imagetype', $field)]   = $type;
                        } else {
                            $values[sprintf('%d.filename', $field)] = $name;
                            $values[sprintf('%d.filetype', $field)] = $type;
                        }
                    }
                }
                $text = $this->replacePlaceHolder($text, $values);
                $text = $this->addHiddenTags($text, $this->form);

                // ワンタイムトークンを生成
                $token = $this->getToken();
                $_SESSION['_cffm_token'] = $token;
                $text = str_ireplace(
                    '</form>'
                    , sprintf(
                        '<input type="hidden" name="_cffm_token" value="%s" /></form>'
                        , $token
                    )
                    , $text
                );
                break;
            case 'comp':
                $nextMode = '';
                $text = $this->replacePlaceHolder($text, $this->encodeHTML($this->form));
                break;
        }

        // 余った<iferror>タグ、プレースホルダを削除
        $text = preg_replace("@<iferror.*?>.+?</iferror>@ism", '', $text);
        $text = $this->clearPlaceHolder($text);

        // 次の処理名をフォームに付記
        if ($nextMode) {
            $text = preg_replace('/(<form.*?>)/i', '\\1<input type="hidden" name="_mode" value="' . $nextMode . '" />', $text);
        }

        return $text;
    }

    private function getToken() {
        return base_convert(str_shuffle(mt_rand()),10,36);
    }

    /**
     * 入力値を検証
     *
     * @access public
     * @param  void
     * @return boolean result
     */
    public function validate() {
        $this->formError = array();

        // 検証メソッドを取得
        if (!$tmp_html = $this->loadTemplate($this->config('tmpl_input'))) {
            $this->raiseError($this->convertText('入力画面テンプレートの読み込みに失敗しました'));
        }
        $this->parsedForm = $this->parseForm($tmp_html);
        if (!$this->parsedForm) {
            $this->raiseError($this->getError());
            return false;
        }

        $_SESSION['dynamic_send_to'] = $this->dynamic_send_to_field($tmp_html);

        foreach ($this->parsedForm as $field => $method) {
            // 初期変換
            if ($method['type'] !== 'textarea') {
                // <textarea>タグ以外は改行を削除
                if (is_array($this->form[$field])) {
                    foreach ($this->form[$field] as $k=>$v) {
                        $this->form[$field][$k] = strtr($v, array("\r" => '', "\n" => ''));
                    }
                } else {
                    $this->form[$field] = strtr($this->form[$field], array("\r" => '', "\n" => ''));
                }
            }
            $methods = explode(',', $method['method']);

            // 入力必須項目のチェック
            if ($method['required']) {
                if ($method['type'] === 'file') {
                    if (
                        (!isset($_SESSION['_cf_uploaded'][$field]) || !is_file($_SESSION['_cf_uploaded'][$field]['path']))
                        && (!isset($_POST['return']) && empty($_FILES[$field]['tmp_name']))
                    ) {
                        $this->setFormError($field, $this->adaptEncoding($method['label']), '選択必須項目です');
                    }
                } elseif ((is_array($this->form[$field]) && !count($this->form[$field])) || $this->form[$field] == '') {
                    $this->setFormError(
                        $field
                        , $this->adaptEncoding($method['label'])
                        , (in_array($method['type'], array('radio', 'select')) ? '選択' : '入力') . '必須項目です');
                }
            }

            // 入力値の検証
            if ($this->form[$field] || $_FILES[$field]['tmp_name'] || $this->form[$field]==='0') {
                foreach ($methods as $indiv_m) {
                    $method_name = array();
                    preg_match("/^([^(]+)(\(([^)]*)\))?$/", $indiv_m, $method_name);
                    // 標準メソッドを処理
                    $funcName = '_def_' . $method_name[1];
                    if (is_callable(array($this, $funcName))) {
                        $result = $this->$funcName($this->form[$field], $method_name[3], $field);
                        if ($result !== true) {
                            $this->setFormError($field, $this->adaptEncoding($method['label']), $result);
                        }
                    }
                    // ユーザー追加メソッドを処理
                    $funcName = '_validate_' . $method_name[1];
                    if (is_callable($funcName)) {
                        $result = $funcName($this->form[$field], $method_name[3]);
                        if ($result !== true) {
                            $this->setFormError($field, $this->adaptEncoding($method['label']), $this->adaptEncoding($result));
                        }
                    }
                }
            }
        }

        // 自動返信先メールアドレスをチェック
        if ($this->config('auto_reply')) {
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
     * @return string
     */
    private function restoreForm($html, $params) {

        $match = array();
        preg_match_all("@<(input|textarea|select)(.+?)([\s/]*?)>(.*?</\\1>)?@ism", $html, $match, PREG_SET_ORDER);

        // タグごとに処理
        foreach ($match as $i => $tag) {
            $m_type = array();
            $m_name = array();
            $m_value = array();
            preg_match("/type=([\"'])(.+?)\\1/i", $tag[0], $m_type);
            preg_match("/name=([\"'])(.+?)\\1/i", $tag[0], $m_name);
            preg_match("/value=([\"'])(.*?)\\1/i", $tag[0], $m_value);

            $fieldName = str_replace('[]', '', $m_name[2]);
            // 復元処理しないタグ
            if ($fieldName === '_mode') continue;

            switch($m_type[2]) {
                // 復元処理しないタグ
                case 'submit';
                case 'image';
                case 'file';
                case 'button';
                case 'reset';
                case 'hidden';
                    continue 2;
                case 'checkbox';
                case 'radio';
                    $fieldType = $m_type[2];
                    break;
                default:
                    $fieldType = 'text';
            }

            // テキストボックス
            if ($tag[1] === 'input' && $fieldType === 'text') {
                if (count($m_value) > 1) {
                    $pat = $m_value[0];
                    $rep = 'value="' . $this->encodeHTML($params[$fieldName]).'"';
                } else {
                    $pat = $tag[2];
                    $rep = $tag[2] . ' value="' . $this->encodeHTML($params[$fieldName]).'"';
                }
            // チェックボックス
            } elseif ($tag[1] === 'input' && $fieldType === 'checkbox') {
                if ($m_value[2] == $params[$fieldName] || (is_array($params[$fieldName]) && in_array($m_value[2], $params[$fieldName]))) {
                    $pat = $tag[2];
                    $rep = $tag[2] . ' checked="checked"';
                }
            // ラジオボタン
            } elseif ($tag[1] === 'input' && $fieldType === 'radio') {
                if ($m_value[2] == $params[$fieldName]) {
                    $pat = $tag[2];
                    $rep = $tag[2] . ' checked="checked"';
                }
            // プルダウンリスト
            } elseif ($tag[1] === 'select') {
                $pat = $rep = '';
                $tag_opt = array();
                preg_match_all("/<option(.*?)value=(['\"])(.*?)\\2(.*?>)/ism", $tag[4], $tag_opt, PREG_SET_ORDER);
                if (count($tag_opt) > 1) {
                    $old = $tag[0];
                    foreach ($tag_opt as $opt_k => $opt_v) {$tag[0] = str_replace(
                        $opt_v[0]
                        , preg_replace("/selected(=(['\"])selected\\2)?/ism", '', $opt_v[0])
                        , $tag[0]
                    );
                        if ($opt_v[3] == $params[$fieldName]) {
                            $tag[0] = str_replace(
                                $opt_v[0]
                                , str_replace(
                                    $opt_v[4]
                                    , ' selected="selected"'.$opt_v[4]
                                    , $opt_v[0]
                                )
                                , $tag[0]
                            );
                        }
                    }
                    $new = $tag[0];
                    $html = str_replace($old, $new, $html);
                }
            // 複数行テキスト
            } elseif ($tag[1] === 'textarea') {
                if ($params[$fieldName]) {
                    $pat = $tag[0];
                    $rep = sprintf(
                        '<%s%s%s>%s</textarea>'
                        , $tag[1]
                        , $tag[2]
                        , $tag[3]
                        , $this->encodeHTML($params[$fieldName])
                    );
                }
            }

            // HTMLタグのみを置換
            if ($rep && $pat) {
                $tag_new = str_replace($pat, $rep, $tag[0]);
            } else {
                $tag_new = '';
            }
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
    public function sendMail() {

        // send_mail環境設定が0の場合は送信しない
        if (!$this->config('send_mail')) {
            return false;
        }

        // 改行コードの設定
        if ($this->config('lf_style')) {
            $this->lf = "\r\n";
        } else {
            $this->lf = "\n";
        }

        $upload_flag = false;

        if (!count($this->form)) {
            $this->setError('フォームが取得できません');return false;
        }
        // 送信メールの文字コード
        if ($this->config('mail_charset')) {
            $mailCharset = $this->config('mail_charset');
        } else {
            $mailCharset = 'iso-2022-jp';
        }

        // 管理者メールアドレス特定
        $dynamic_send_to = $_SESSION['dynamic_send_to'];
        $admin_addresses = array();
        if ($this->config('dynamic_send_to_field')
            && $dynamic_send_to
            && $this->form[$this->config('dynamic_send_to_field')]) {
                $mails = explode(
                    ','
                    , $dynamic_send_to[$this->form[$this->config('dynamic_send_to_field')]]
                );
        } else {
            $mails = explode(',', $this->config('admin_mail'));
        }

        foreach ($mails as $buf) {
            $buf = trim($buf);
            if ($this->_isValidEmail($buf)) {
                $admin_addresses[] = $buf;
            }
        }

        // 本文の準備
        $additional = array(
            'senddate'    => date('Y-m-d H:i:s'),
            'adminmail'   => $admin_addresses[0],
            'sender_ip'   => $_SERVER['REMOTE_ADDR'],
            'sender_host' => gethostbyaddr($_SERVER['REMOTE_ADDR']),
            'sender_ua'   => $this->encodeHTML($_SERVER['HTTP_USER_AGENT']),
            'reply_to'    => $this->getAutoReplyAddress(),
        );

        $reply_to = $this->getAutoReplyAddress();

        // 管理者宛メールの本文生成
        if (!$tmpl = $this->loadTemplate($this->config('tmpl_mail_admin'))) {
            $this->setError('メールテンプレートの読み込みに失敗しました');
            return false;
        }
        $tmpl = str_replace(array("\r\n", "\r"), "\n", $tmpl);
        $form = $this->form;
        if ($this->config('admin_ishtml')) {
            $form = $this->config('allow_html') ? $this->nl2br_array($form) : $this->encodeHTML($form, 'true');
        }
        $join = $this->config('allow_html') ? '<br />' : "\n";
        $tmpl = $this->replacePlaceHolder($tmpl, $form + $additional, $join);
        $tmpl = $this->clearPlaceHolder($tmpl);

        // 自動返信メールの本文生成
        if ($this->config('auto_reply') && $reply_to) {
            // モバイル用のテンプレート切り替え
            if ($this->config('tmpl_mail_reply_mobile') && preg_match("/(docomo\.ne\.jp|ezweb\.ne\.jp|softbank\.ne\.jp|vodafone\.ne\.jp|disney\.ne\.jp|pdx\.ne\.jp|willcom\.com|emnet\.ne\.jp)$/", $reply_to)) {
                $template_filename = $this->config('tmpl_mail_reply_mobile');
            } else {
                $template_filename = $this->config('tmpl_mail_reply');
            }
            if (!$tmpl_u = $this->loadTemplate($template_filename)) {
                $this->setError('メールテンプレートの読み込みに失敗しました');
                return false;
            }
            $tmpl_u = str_replace(array("\r\n", "\r"), "\n", $tmpl_u);
            $form_u = $this->form;
            if ($this->config('reply_ishtml')) {
                $form_u = $this->config('allow_html') ? $this->nl2br_array($form_u) : $this->encodeHTML($form_u, 'true');
            }
            $tmpl_u = $this->replacePlaceHolder($tmpl_u, $form_u + $additional, $join);
            $tmpl_u = $this->clearPlaceHolder($tmpl_u);
        }

        // 管理者宛送信
        evo()->loadExtension('MODxMailer');
        $pm = evo()->mail;
        foreach ($admin_addresses as $v) {
            $pm->AddAddress($v);
        }
        if ($this->config('admin_mail_cc')) {
            foreach (explode(',', $this->config('admin_mail_cc')) as $v) {
                $v = trim($v);
                if ($this->_isValidEmail($v)) {
                    $pm->AddCC($v);
                }
            }
        }
        if ($this->config('admin_mail_bcc')) {
            foreach (explode(',', $this->config('admin_mail_bcc')) as $v) {
                $v = trim($v);
                if ($this->_isValidEmail($v)) {
                    $pm->AddBCC($v);
                }
            }
        }
        if($this->config('admin_subject')) {
            $pm->Subject = evo()->parseText($this->config('admin_subject'), $this->form);
        } else {
            $pm->Subject = 'サイトから送信されたメール';
        }
        $pm->setFrom(
            $admin_addresses[0]
            , ($this->config('admin_name')) ? evo()->parseText($this->config('admin_name'),$this->form) : ''
        );
        if ($reply_to) {
            $pm->addReplyTo($reply_to);
        }
        $pm->Sender = $pm->From;
        $pm->Body = mb_convert_encoding($tmpl, $mailCharset, $this->config('charset'));
        $pm->Encoding = '7bit';
        // ユーザーからのファイル送信
        if (isset($_SESSION['_cf_uploaded']) && count($_SESSION['_cf_uploaded'])) {
            $upload_flag = true;
            foreach ($_SESSION['_cf_uploaded'] as $attach_file) {
                if (is_file($attach_file['path'])) {
                    $filename = urldecode(basename($attach_file['path']));
                    $pm->AddAttachment($attach_file['path'], mb_convert_encoding($filename, $mailCharset, $this->config('charset')));
                }
            }
        }
        if (!$pm->Send()) {
            $errormsg = 'メール送信に失敗しました::' . $pm->ErrorInfo;
            $this->setError($errormsg);
            $vars = var_export($pm,true);
            $vars = nl2br(evo()->htmlspecialchars($vars));
            evo()->logEvent(1, 3,$errormsg.$vars);
            return false;
        }

        if(isset($_SESSION['_cf_autosave'])) {
            unset($_SESSION['_cf_autosave']);
        }

        // 自動返信
        if ($this->config('auto_reply') && $reply_to) {
            evo()->loadExtension('MODxMailer');
            $pm = evo()->mail;
            $pm->AddAddress($reply_to);
            if($this->config('reply_subject')) {
                $pm->Subject = evo()->parseText($this->config('reply_subject'),$this->form);
            } else {
                $pm->Subject = '自動返信メール';
            }
            $pm->setFrom(
                $this->config('reply_from') ? $this->config('reply_from') : $admin_addresses[0]
                , $this->config('reply_fromname')
            );
            $pm->Sender = $pm->From;
            $pm->Body = mb_convert_encoding($tmpl_u, $mailCharset, $this->config('charset'));
            $pm->Encoding = '7bit';
            // 添付ファイル処理
            if ($this->config('attach_file') && is_file($this->config('attach_file'))) {
                if ($this->config('attach_file_name')) {
                    $pm->AddAttachment(
                        $this->config('attach_file')
                        , mb_convert_encoding($this->config('attach_file_name')
                            , $mailCharset, $this->config('charset')
                        ));
                } else {
                    $pm->AddAttachment($this->config('attach_file'));
                }
            }
            // ユーザーからのファイル送信
            if ($upload_flag) {
                foreach ($_SESSION['_cf_uploaded'] as $attach_file) {
                    if (!is_file($attach_file['path'])) {
                        continue;
                    }
                    $filename = urldecode(basename($attach_file['path']));
                    $pm->AddAttachment(
                        $attach_file['path']
                        , mb_convert_encoding($filename, $mailCharset, $this->config('charset')));
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
                $vars = nl2br(evo()->htmlspecialchars($vars));
                evo()->logEvent(1, 3,$errormsg.$vars);
                return false;
            }
        }

        return true;
    }

    /**
     * トークンをチェック
     *
     * @access public
     * @param  void
     * @return boolean 結果
     */
    public function isValidToken() {
        $token = @$_SESSION['_cffm_token'];
        unset($_SESSION['_cffm_token']);
        return ($token == $_POST['_cffm_token']);
    }

    /**
     * すでに送信済みかどうかをチェック
     *
     * @access public
     * @param  void
     * @return boolean 結果
     */
    public function isMultiple() {
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
    private function assignErrorTag($html, $errors) {
        if (!is_array($errors)) {
            return $html;
        }
        $match = array();
        preg_match_all("@<iferror\.?([^>]+?)?>(.+?)</iferror>@ism", $html, $match, PREG_SET_ORDER);

        if (!count($match)) return $html;

        foreach ($match as $tag) {
            if (!empty($tag[1])) {
                // グルーピングされたタグの処理
                $g_match = array();
                if (preg_match("/^\((.+?)\)$/", $tag[1], $g_match)) {
                    $groups = explode(',', $g_match[1]);
                    $isErr = 0;
                    foreach($groups as $group) {
                        $group = strtr($group, array(' ' => ''));
                        $isErr = $errors['error.' . $group] ? 1 : $isErr;
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
    private function assignErrorClass($html, $errors) {
        if (!$this->config('invalid_class')) {
            return $html;
        }

        if (count($errors) < 2) return $html;

        // エラーのあるフィールド名リストを作成
        if (isset($errors['errors'])) unset($errors['errors']);
        $keys = array_unique(array_keys($errors));
        foreach ($keys as $field) {
            $field = str_replace('error.', '', $field);
            $pattern = "#<(input|textarea|select)[^>]*?name=(\"|\')" . $field . "\\2[^/>]*/?>#im";
            if (preg_match_all($pattern, $html, $match, PREG_SET_ORDER)) {
                foreach ($match as $m) {
                    // クラスを定義済みの場合は最後に追加
                    if (preg_match("/class=([\"'])(.+?)\\1/", $m[0], $match_classes)) {
                        $newClass = 'class=' . $match_classes[1] . $match_classes[2] . ' ' . $this->config('invalid_class','') . $match_classes[1];
                        $rep = str_replace($match_classes[0], $newClass, $m[0]);
                        // そうでなければ class 要素を追加
                    } else {
                        $rep = preg_replace("#\s*/?>$#", '', $m[0]) . sprintf(' class="%s"', $this->config('invalid_class','')) . ($m[1] === 'input' ? ' /' : '') . '>';
                    }
                    $html = str_replace($m[0], $rep, $html);
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
    private function convertText($text) {
        if (is_array($text)) {
            foreach ($text as $k => $v) {
                $text[$k] = $this->convertText($v);
            }
        } elseif (strtolower($this->config('charset')) !== 'utf-8') {
            $text = mb_convert_encoding($text, $this->config('charset'), 'utf-8');
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
    private function adaptEncoding($text, $charset='') {
        if(!$charset) {
            $charset = $this->charset();
        }
        if (is_array($text)) {
            foreach($text as $k=>$v) {
                $text[$k] = $this->adaptEncoding($v);
            }
            return $text;
        }
        if (strtolower($charset) === 'utf-8') {
            return $text;
        }
        return mb_convert_encoding($text, 'utf-8', $charset);
    }

    /**
     * HTMLエンコード
     *
     * @access private
     * @param  mixed   $text  変換するテキスト（または配列）
     * @param  boolean $nl2br TRUE の場合、改行コードを<br />に変換　（Default: FALSE）
     * @return mixed 変換後のテキストまたは配列
     */
    private function encodeHTML($text, $nl2br = '') {
        if (is_array($text)) {
            foreach ($text as $k => $v) {
                $text[$k] = $this->encodeHTML($v, $nl2br);
            }
            return $text;
        }
        $text = evo()->htmlspecialchars($text, ENT_QUOTES);
        $text = $this->convertjp($text);
        if ($nl2br) {
            $text = nl2br($text);
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
        $char['Ⅰ']  = 'I';
        $char['Ⅱ']  = 'II';
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
        $char['№']  = 'No.';
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
    public function nl2br_array($text) {
        if (is_array($text)) {
            return array_map(array($this, 'nl2br_array'), $text);
        }

        return nl2br($text);
    }

    /**
     * テンプレートチャンクの読み込み
     *
     * @access private
     * @param  string $tpl_name チャンク名・またはリソースID
     * @return string 読み込んだデータ
     */
    private function loadTemplate($tpl_name) {
        $tpl_name = trim($tpl_name);
        if (preg_match('/^@FILE:.+/', $tpl_name)) {
            $list = array(
                CFM_PATH . trim(substr($tpl_name, 6)),
                MODX_BASE_PATH . trim(substr($tpl_name, 6))
            );
            foreach ($list as $path) {
                if(!is_file($path)) {
                    continue;
                }
                return $this->parseDocumentSource(
                    file_get_contents($path)
                );
            }
        } elseif (preg_match('/^[1-9][0-9]*$/', $tpl_name)) {
            $doc = $this->getDocument($tpl_name);
            if(isset($doc['content']) && $doc['content']) {
                return $this->parseDocumentSource($doc['content']);
            }
        } elseif($content = evo()->getChunk($tpl_name)) {
            return $this->parseDocumentSource($content);
        }

        $error = 'tpl read error';
        if($tpl_name) {
            $error .= sprintf(' (%s)', $tpl_name);
        }
        $this->setError($error);
        return false;
    }

    private function parseDocumentSource($content) {
        if(strpos($content,'[!')!==false) {
            $content = str_replace(array('[!','!]'),array('[[',']]'),$content);
        }
        return evo()->parseDocumentSource($content);
    }

    private function getDocument($docid) {
        $rs = db()->select(
            '*'
            , '[+prefix+]site_content'
            , 'id=' . $docid
            , ''
            , 1
        );
        $doc = db()->getRow($rs);
        if(!$doc) {
            return false;
        }
        return $doc;
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
    private function replacePlaceHolder($text, $params, $join = '<br />') {
        if (!is_array($params) || !$text) {
            return false;
        }

        preg_match_all("/\[\+([^+|]+)(\|(.*?)(\((.+?)\))?)?\+]/is", $text, $match, PREG_SET_ORDER);
        if (!count($match)) {
            return $text;
        }

        $toFilter = true;
        //旧バージョン用
        if(isset(evo()->config['output_filter']) &&evo()->config['output_filter']==='0') {
            $toFilter = false;
        }

        if($toFilter) evo()->loadExtension('PHx') or die('Could not load PHx class.');

        // 基本プレースホルダ
        $replaceKeys = array_keys($params);
        foreach ($match as $m) {
            if($toFilter && strpos($m[1],':')!==false) {
                list($m[1],$modifiers) = explode(':', $m[1], 2);
            } else {
                $modifiers = false;
            }

            if (!in_array($m[1], $replaceKeys)) continue;

            $fType = $m[3];
            $val = $params[$m[1]];
            if($toFilter && $modifiers!==false) {
                if($val==='&nbsp;') {
                    $val = '';
                }
                $val = evo()->filter->phxFilter($m[1],$val,$modifiers);
                if($val==='') $val = '&nbsp;';
            }
            $rep = '';

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
                $rep = is_array($val) ? implode($join, $val) : $val;
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
    private function clearPlaceHolder($text) {
        return preg_replace("/\[\+.+?\+]/ism", '', $text);
    }

    /**
     * NULL 値を文字列に変換
     *
     * @param mixed $data 変換するデータ
     * @param string $string 変換される文字列(Default: &nbsp;)
     * @return mixed 変換後のデータ
     */
    private function convertNullToStr($data, $string = '&nbsp;') {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->convertNullToStr($v, $string);
            }
            return $data;
        }
        if(!$data) {
            return $string;
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
    private function getCaptchaUri() {
        if (is_file(MODX_BASE_PATH . 'captcha.php')) {
            return MODX_BASE_URL . 'captcha.php';
        }

        if(is_file(MODX_MANAGER_PATH.'media/captcha/veriword.php')) {
            return MODX_BASE_URL . 'index.php?get=captcha';
        }

        return MODX_BASE_URL . 'manager/includes/veriword.php?tmp=' . mt_rand();
    }

    /**
     * <form></form>タグ内に入力値を hidden 属性で埋め込む
     *
     * @access private
     * @param  string $html 対象となるHTML文書
     * @param  array  $form 埋め込むデータ
     * @return string 埋め込み後のHTML文書
     */
    private function addHiddenTags($html, $form) {
        if (!is_array($form)) {
            return $html;
        }
        if (isset($form['_mode'])) {
            unset($form['_mode']);
        }
        $tag = array();
        foreach ($form as $k => $v) {
            if (!is_array($v)) {
                $tag[] = sprintf(
                    '<input type="hidden" name="%s" value="%s" />'
                    , $this->encodeHTML($k)
                    , $this->encodeHTML($v)
                );
                continue;
            }
            foreach ($v as $subv) {
                $tag[] = sprintf(
                    '<input type="hidden" name="%s[]" value="%s" />'
                    , $this->encodeHTML($k)
                    , $this->encodeHTML($subv)
                );
            }
        }
        return str_replace('</form>', join("\n",$tag) . '</form>', $html);
    }

    /**
     * 投稿されたデータの初期処理
     *
     * @access private
     * @param  array $array データ
     * @return array 初期処理の終わったデータ
     */
    private function getFormVariables($array) {

        if (!is_array($array)) {
            return array();
        }

        $ret = array();
        foreach ($array as $k => $v) {
            if (!is_int($k) && ($k === '_mode' || $k === '_cffm_token')) continue;
            if (is_array($v)) {
                $v = $this->getFormVariables($v);
            } else {
                $v = str_replace("\0", '', $v);
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
    private function parseForm($html) {
        $html = $this->extractForm($html, '');
        if ($html === false) {
            return false;
        }

        preg_match_all(
            "/<(input|textarea|select).*?name=([\"'])(.+?)\\2.*?>/is"
            , $html
            , $match
            , PREG_SET_ORDER
        );

        $methods = array();
        foreach ($match as $v) {

            // 検証メソッドを取得
            if (preg_match("/valid=([\"'])(.+?)\\1/", $v[0], $v_match)) {
                list($required, $method, $param) = explode(':', $v_match[2]);
            } else {
                $required = $method = $param = '';
            }

            // 項目名の取得
            if ($param) {
                $label = $param;
            } elseif (preg_match("/id=([\"'])(.+?)\\1/", $v[0], $l_match)) {
                if (preg_match(
                    "@<label for=([\"']){$l_match[2]}\\1.*>(.+?)</label>@"
                    , $html
                    , $match_label
                )) {
                    $label = $match_label[2];
                } else {
                    $label = '';
                }
            } else {
                $label = '';
            }

            $fieldName = str_replace('[]', '', $v[3]); // 項目名を取得
            if (!isset($methods[$fieldName])) {
                $methods[$fieldName] = array(
                    'type'     => $this->_get_input_type($v),
                    'required' => $required,
                    'method'   => $method,
                    'param'    => $param,
                    'label'    => $label
                );
            }
        }
        return $methods;
    }
    
    private function dynamic_send_to_field($html) {
        $methods = $this->parsedForm;
        // 送信先動的変更のためのデータ取得(from v1.3)
        if (!$this->config('dynamic_send_to_field')) {
            return null;
        }
        $field_name = $this->config('dynamic_send_to_field');
        if (!isset($methods[$field_name])) {
            return null;
        }

        $m_options = array();
        if ($methods[$field_name]['type'] === 'select') {
            preg_match(
                sprintf(
                    "@<select.*?name=(\"|\')%s\\1.*?>(.+?)</select>@ims"
                    , $field_name
                )
                , $html
                , $matches
            );
            if ($matches[2]) {
                preg_match_all(
                    "@<option.+?</option>@ims"
                    , $matches[2]
                    , $m_options
                );
            }
        } elseif ($methods[$field_name]['type'] === 'radio') {
            preg_match_all(
                sprintf(
                    "/<input.*?name=(\"|\')%s\\1.*?>/im"
                    , $field_name
                )
                , $html
                , $m_options
            );
        }

        if (!$m_options[0]) {
            return null;
        }

        foreach ($m_options[0] as $m_option) {
            preg_match_all(
                "/(value|sendto)=([\"'])(.+?)\\2/i"
                , $m_option
                , $buf
                , PREG_SET_ORDER
            );
            if ($buf && count($buf) != 2) {
                continue;
            }
            if ($buf[0][1] === 'value') {
                $dynamic_send_to[$buf[0][3]] = $buf[1][3];
            } else {
                $dynamic_send_to[$buf[1][3]] = $buf[0][3];
            }
        }
        return $dynamic_send_to;
    }

    private function _get_input_type($v) {
        // 項目タイプを取得
        if ($v[1] === 'input') {
            preg_match("/type=([\"'])(.+?)\\1/", $v[0], $t_match);
            return $t_match[2];
        }

        return $v[1];
    }
    /**
     * 指定したIDのフォームタグ内のみ抽出
     *
     * @access private
     * @param string $html 対象のHTML文書
     * @param string $id   抽出対象のフォームID
     * @return mixed 抽出されたHTML文書（失敗の場合は FALSE）
     */
    private function extractForm($html, $id = '') {
        if (preg_match("@<form.+?>([\S\s]+)</form>@m", $html, $match_form)) {
            return $match_form[1];
        }

        $this->setError('&lt;form&gt;タグが見つかりません');
        return false;
    }

    /**
     * 入力値をセッションに待避
     *
     * @access public
     * @param  void
     * @return void
     */
    public function storeDataInSession() {
        $_SESSION['_cffm_recently_send'] = array();
        foreach ($this->form as $k => $v) {
            if ($k === '_mode' || $k === '_cffm_token') {
                continue;
            }
            $_SESSION['_cffm_recently_send'][$k] = $v;
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
    private function setError($mes) {
        $this->error_message = $mes;
    }

    /**
     * エラーメッセージを取得
     *
     * @access public
     * @param  void
     * @return string 取得したメッセージ
     */
    public function getError() {
        return $this->convertText($this->error_message);
    }

    /**
     * 検証エラーを設定
     *
     * @access private
     * @param  string $field   エラーのあるフィールド名
     * @param  string $label  エラーのある項目名
     * @param  string $message 割り当てるメッセージ
     * @return bool
     */
    private function setFormError($field, $label, $message) {
        $this->formError[$field][] = array('label' => $label, 'text' => $message);
        return true;
    }

    /**
     * 検証エラーを取得
     *
     * @access public
     * @return array 取得したメッセージ（プレースホルダ用に整形。個別用の error.field_name と 一括用の errors の両方が返される）
     */
    public function getFormError() {
        if (count($this->formError) < 1) {
            return array();
        }
        $ret = array();
        foreach ($this->formError as $field => $val) {
            foreach ($val as $mes) {
                if ($mes['label']) {
                    $label = $this->convertText($mes['label']);
                } else {
                    $label = $field;
                }
                $ret['error.' . $field][] = $this->convertText($mes['text']);
                $ret['errors'][] = '[' . $label . '] ' . $this->convertText($mes['text']);
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
     * @return string
     */
    public function raiseError($mes, $ifDie = false) {
        return sprintf(
            '<p style="color:#cc0000;background:#fff;font-weight:bold;">SYSTEM ERROR::%s</p>'
            , $mes
        );
    }

    /**
     * バージョン番号を取得
     *
     * @return string バージョン番号
     */
    public function getVersion() {
        return $this->version;
    }

    /**
     * 自動返信先メールアドレスを取得
     *
     * @return string メールアドレス
     */
    private function getAutoReplyAddress() {
        if (!$this->config('auto_reply')) {
            return '';
        }
        $reply_to = array();
        $tmp = explode('+', $this->config('reply_to'));
        foreach ($tmp as $t) {
            $t = trim($t);
            if ($t === '@') {
                $reply_to[] = '@';
            } else {
                $reply_to[] = $this->form[$t];
            }
        }
        return implode('', $reply_to);
    }

    /**
     * メールアドレス妥当性チェック
     *
     * @access private
     * @param string $addr チェックするメールアドレス
     * @return boolean 結果
     */
    private function _isValidEmail($addr) {
        return preg_match(
            "/^(?:[a-z0-9+_-]+?\.)*?[a-z0-9_+-]+?@(?:[a-z0-9_-]+?\.)*?[a-z0-9_-]+?\.[a-z0-9]{2,5}$/i"
            , $addr
        );
    }

    /**
     * ファイルのMIMEタイプを取得
     * class.upload.php 使用推奨
     *
     * @param string  $filename MIMEタイプを調べるファイル
     * @param string  $field  アップロードされたフィールド名
     * @return string MIMEタイプ。失敗の場合は false
     */
    private function _getMimeType($filename, $field = '') {
        if (class_exists('upload')) {
            // class.upload.php使用
            $up = new upload($filename);
            if ($up->uploaded) {
                return $up->file_src_mime;
            }
            return false;
        }

        // class.upload.php未使用。image以外の結果はあまり信用できない
        $size = @getimagesize($filename);
        if ($size === false) {
            if (isset($_FILES[$field]['type']) && $_FILES[$field]['type']) {
                return $_FILES[$field]['type'];
            }
            return false;
        }

        return $size['mime'];
    }

    /**
     * MIMEタイプからファイルの識別子を取得
     *
     * @param string  MIMEタイプ
     * @return  string  タイプ
     */
    private function _getType($mime) {
        $mime_list = array(
            'image/gif'          => 'gif',
            'image/jpeg'         => 'jpg',
            'image/pjpeg'        => 'jpg',
            'image/png'          => 'png',
            'application/pdf'    => 'pdf',
            'text/plain'         => 'txt',
            'text/html'          => 'html',
            'application/msword' => 'word'
        );

        if (isset($mime_list[$mime])) {
            return $mime_list[$mime];
        }

        return '';
    }

    private function charset() {
        if (isset(evo()->event->params['language'])) {
            $lang = evo()->event->params['language'];
        } else {
            $lang = strtolower(evo()->config['manager_language']);
        }
        if(strpos($lang,'utf')!==false) {
            return 'utf-8';
        }
        if(strpos($lang,'euc')!==false) {
            return 'euc-jp';
        }
        return $lang;
    }
    /**
     * 環境設定の読み込み
     *
     * @param string $config_name 環境設定チャンク名
     * @return mixed true || エラーメッセージ
     */
    public function parseConfig($config_name) {
        $conf = $this->loadTemplate($config_name);
        if (!$conf) {
            return '環境設定の読み込みに失敗しました。';
        }

        $cfg = $this->setDefaultConfig();

        $cfg['charset'] = $this->charset();
        if(!defined('CHARSET')) {
            define('CHARSET', $cfg['charset']);
        }
        $conf = $this->adaptEncoding($conf, $cfg['charset']);

        $conf_arr = explode("\n", $conf);
        foreach ($conf_arr as $line) {
            if (strpos($line, '#') === 0 || !preg_match('/[a-zA-Z0-9=]/', $line)) {
                continue;
            }
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            if(!$key) {
                continue;
            }
            $cfg[$key] = trim($val);
        }

        $this->cfg = $cfg;

        if ($this->getErrors()) {
            $this->setSystemError(
                join(
                    '<br />'
                    , $this->convertText($this->getErrors())
                )
            );
            return false;
        }
        return $this->cfg;
    }

    public function config($key, $default=null) {
        if(!isset($this->cfg[$key])) {
            return $default;
        }
        return $this->cfg[$key];
    }

    private function getErrors() {
        static $errors = null;

        if($errors!==null) {
            return $errors;
        }

        // 必須項目チェック
        $errors = array();
        if (!$this->config('tmpl_input')) {
            $errors[] = '`入力画面テンプレート`を指定してください';
        }
        if (!$this->config('tmpl_conf')) {
            $errors[] = '`確認画面テンプレート`を指定してください';
        }
        if (!$this->config('tmpl_comp') && !$this->config('complete_redirect')) {
            $errors[] = '`完了画面テンプレート`または`送信後遷移する完了画面リソースID`を指定してください';
        }

        if (!$this->config('tmpl_mail_admin')) {
            $errors[] = '`管理者宛メールテンプレート`を指定してください';
        }
        if ($this->config('auto_reply') && !$this->config('tmpl_mail_reply')) {
            $errors[] = '`自動返信メールテンプレート`を指定してください';
        }

        return $errors;
    }

    private function setDefaultConfig() {
        // 値の指定が無い場合はデフォルト値を設定
        return array(
            'charset'        => 'utf-8',
            'admin_mail'     => evo()->config['emailsender'],
            'auto_reply'     => 0,
            'reply_to'       => 'email',
            'reply_fromname' => evo()->config['site_name'],
            'vericode'       => 0,
            'admin_ishtml'   => 0,
            'reply_ishtml'   => 0,
            'allow_html'     => 0,
            'autosave'       => 0,
            'send_mail'      => 1,
        );
    }

    private function setSystemError($error_string) {
        $this->sysError = $error_string;
    }

    public function hasSystemError() {
        if($this->sysError) {
            return true;
        }

        return false;
    }

    public function getSystemError() {
        return $this->sysError;
    }

    /**
     * 送信内容をDBに保存
     * 動作にはcfFormDBモジュールが必要
     *
     * @access public
     */
    public function storeDB() {
        if (!$this->config('use_store_db')) {
            return;
        }

        if (!$this->ifTableExists()) {
            return;
        }

        $sql = 'INSERT INTO ' . evo()->getFullTableName('cfformdb') . '(created) VALUES(NOW())';
        db()->query($sql);
        $newID = db()->getInsertId();
        $rank = 0;
        foreach ($this->form as $key => $val) {
            if ($key === 'veri') {
                continue;
            }
            if (is_array($val)) {
                $val = join(',', $val);
            }
            $sql = sprintf("INSERT INTO %s(postid,field,value,rank) VALUES(%d, '%s', '%s', %d)",
                evo()->getFullTableName('cfformdb_detail'),
                $newID,
                db()->escape($key),
                db()->escape($val),
                $rank
            );
            db()->query($sql);
            $rank++;
        }
    }

    /**
     * テーブルの存在確認
     * @access private
     */
    private function ifTableExists() {
        $sql = sprintf(
            "SHOW TABLES FROM %s LIKE '%%cfformdb%%'"
            , db()->config['dbase']
        );
        return db()->getRecordCount(db()->query($sql)) == 2;
    }

    /* ------------------------------------------------------------------ */
    /* 標準装備の検証メソッド
    /* ------------------------------------------------------------------ */

    /**
     * num : 数値？
     */
    private function _def_num($value, $param, $field) {
        // 強制的に半角に変換します。
        $this->form[$field] = mb_convert_kana(
            $this->form[$field]
            , 'n'
            , $this->config('charset')
        );

        if (is_numeric($this->form[$field])) {
            return true;
        }

        return '半角数字で入力してください';
    }

    /**
     * email : 正しいメールアドレス形式か？
     */
    private function _def_email($value, $param, $field) {
        // 強制的に半角に変換します。
        $this->form[$field] = mb_convert_kana(
            $this->form[$field]
            , 'a'
            , $this->config('charset')
        );

        if ($this->_isValidEmail($this->form[$field])) {
            return true;
        }

        return 'メールアドレスの形式が正しくありません';
    }

    /**
     * len(min, max) : 文字数チェック
     */
    private function _def_len($value, $param, $field) {

        if (!preg_match("/([0-9]+)?(-)?([0-9]+)?/", $param, $match)) {
            return true;
        }

        if ($match[1] && empty($match[2]) && empty($match[3])) {
            if (mb_strlen($value) != $match[1]) {
                return $match[1] . '文字で入力してください';
            }
            return true;
        }

        if (!$match[1] && $match[2] && $match[3]) {
            if (mb_strlen($value) > $match[3]) {
                return $match[3] . '文字以内で入力してください';
            }
            return true;
        }

        if ($match[1] && $match[2] && !$match[3]) {
            if (mb_strlen($value) < $match[1]) {
                return $match[1] . '文字以上で入力してください';
            }
            return true;
        }

        if ($match[1] && $match[2] && $match[3]) {
            if (mb_strlen($value) < $match[1] || mb_strlen($value) > $match[3]) {
                return sprintf('%s～%s文字で入力してください', $match[1], $match[3]);
            }
            return true;
        }
        return true;
    }

    /**
     * vericode : CAPTCHA による認証チェック
     *   Added in v0.0.5
     */
    private function _def_vericode($value, $param, $field) {
        if (!$this->config('vericode')) {
            return true;
        }
        if ($_SESSION['veriword'] == $value) {
            return true;
        }

        $this->form[$field] = '';
        return '入力値が正しくありません';
    }

    /**
     * range(min, max) : 値範囲チェック
     *   Added in v0.0.5
     */
    private function _def_range($value, $param, $field) {

        if (!preg_match('/([0-9-]+)?(~)?([0-9-]+)?/', $param, $match)) {
            return true;
        }

        if ($match[1] && !$match[2] && !$match[3]) {
            if ($match[1] < $value) {
                return '入力値が範囲外です';
            }

            return true;
        }

        if (!$match[1] && $match[2] && $match[3]) {
            if ($match[3] < $value) {
                return '入力値が範囲外です';
            }
            return true;
        }

        if (isset($match[1], $match[2])) {
            if ($value < $match[1]) {
                return '入力値が範囲外です';
            }

            if ($match[3] < $value) {
                return '入力値が範囲外です';
            }
            return true;
        }

        return true;
    }

    /**
     * sameas(field) : 同一確認
     *   Added in v0.0.6
     */
    private function _def_sameas($value, $param, $field) {
        if ($value == $this->form[$param]) {
            return true;
        }

        unset($this->form[$field]);
        return sprintf('&laquo; %s &raquo; と一致しません', $this->adaptEncoding($this->parsedForm[$param]['label']));
    }

    /**
     * tel : 電話番号？
     *   Added in v0.0.7
     */
    private function _def_tel($value, $param, $field) {
        // 強制的に半角に変換します。
        $this->form[$field] = mb_convert_kana($this->form[$field], 'a', $this->config('charset'));
        $this->form[$field] = preg_replace('@([0-9])ー@u', '$1-', $this->form[$field]);
        if ((strpos($this->form[$field], '0') === 0)) {
            $checkLen = 10;
        } else {
            $checkLen = 5;
        }
        $checkStr = preg_replace('/[^0-9]/','',$this->form[$field]);
        if (!preg_match('/[0-9]{4}$/', $this->form[$field])) {
            return '正しい電話番号を入力してください。';
        }

        if ((preg_match("/[^0-9\-+]/", $checkStr) || strlen($checkStr) < $checkLen)) {
            return '半角数字とハイフンで正しく入力してください';
        }

        return true;
    }

    /**
     * tel : 郵便番号
     *   Added in v1.3.x
     */
    private function _def_zip($value, $param, $field) {
        // 強制的に半角に変換します。
        $this->form[$field] = mb_convert_kana($this->form[$field], 'as', $this->config('charset'));
        $this->form[$field] = preg_replace('/[^0-9]/','',$this->form[$field]);
        $str = $this->form[$field];

        if(strlen($str) !== 7) {
            return '半角数字とハイフンで正しく入力してください';
        }

        $this->form[$field] = substr($str,0,3) . '-' . substr($str,-4);

        return true;
    }

    /**
     * allowtype(type) : アップロードを許可するファイル形式
     *   Added in v1.0
     */
    private function _def_allowtype($value, $param, $field) {

        if (!isset($_FILES[$field]['tmp_name']) || empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
            return true;
        }
        $allow_list = explode('|', $param);
        if (!count($allow_list)) {
            return true;
        }

        $mime = $this->_getMimeType($_FILES[$field]['tmp_name'], $field);
        if ($mime === false) {
            return '許可されたファイル形式ではありません';
        }

        $type = $this->_getType($mime);
        if (!$type || !in_array($type, $allow_list, true)) {
            return '許可されたファイル形式ではありません';
        }

        return true;
    }

    private function array_get($array, $key, $default=null) {
        if (!isset($array[$key])) {
            return $default;
        }
        return $array[$key];
    }
    private function isSpam() {
        if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return false;
        }
        return true;
    }
    /**
     * allowsize(size) : アップロードを許可する最大ファイルサイズ
     *   Added in v1.0
     */
    private function _def_allowsize($value, $param, $field) {
        if (!isset($_FILES[$field]['tmp_name']) || empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
            return true;
        }

        if (!$param || !is_numeric($param)) {
            return false;
        }

        $size = @stat($_FILES[$field]['tmp_name']);
        if ($size === false) {
            return 'ファイルのアップロードに失敗しました';
        }

        if (($size['size'] <= $param * 1024)) {
            return true;
        }

        return $param . 'キロバイト以内のファイルを指定してください';
    }

    /**
     * convert(param)：半角英数字に変換
     *   See: http://jp2.php.net/manual/ja/function.mb-convert-kana.php
     *   Added in v1.2
     * @param $value
     * @param string $param
     * @param $field
     * @return bool
     */
    private function _def_convert($value, $param = 'K', $field) {
        if (!$param) {
            $param = 'K';
        }
        $this->form[$field] = mb_convert_kana(
            $this->form[$field]
            , $param
            , $this->config('charset')
        );
        return true;  // 常にtrueを返す
    }

    /**
     * zenhan：半角英数字記号に変換
     *   See: http://jp2.php.net/manual/ja/function.mb-convert-kana.php
     *   Added in v1.2
     * @param $value
     * @param string $param
     * @param $field
     * @return bool
     */
    private function _def_zenhan($value, $param='VKas', $field) {
        $this->form[$field] = mb_convert_kana(
            $this->form[$field]
            , $param
            , $this->config('charset')
        );
        $this->form[$field] = preg_replace('@([0-9])ー@u', '$1-', $this->form[$field]);
        return true;  // 常にtrueを返す
    }

    /**
     * hanzen：全角英数字記号に変換
     *   See: http://jp2.php.net/manual/ja/function.mb-convert-kana.php
     *   Added in v1.2
     * @param $value
     * @param string $param
     * @param $field
     * @return bool
     */
    private function _def_hanzen($value, $param='VKAS', $field) {
        $this->form[$field] = mb_convert_kana(
            $this->form[$field]
            , $param
            , $this->config('charset')
        );
        return true;  // 常にtrueを返す
    }

    /**
     * url(string)：URL値検証
     *   Added in v1.2
     */
    private function _def_url($value, $param, $field) {
        return preg_match("@^https?://.+\..+@", $value);
    }

    /* ------------------------------------------------------------------ */
    /* 標準装備のフィルターメソッド
    /* ------------------------------------------------------------------ */

    /**
     * implode : 文字列結合
     */
    private function _f_implode($text, $param) {
        if (is_array($text)) {
            return implode(str_replace("\\n", "\n", $param), $text);
        }

        return $text;
    }

    /**
     * implodetag(tag) : HTMLタグで文字列結合
     */
    private function _f_implodetag($text, $param) {

        if (!is_array($text)) {
            $text = array($text);
        }

        $ret = '';
        foreach ($text as $v) {
            $ret .= sprintf('<%s>%s</%s>', $param, $v, $param);
        }
        return $ret;
    }

    /**
     * num : 数値のフォーマット （※PHP関数 number_format() と同様）
     */
    private function _f_num($text, $param) {

        if (is_array($text)) {
            return array_map($this->_f_num, $text, $param);
        }

        return number_format($text);
    }

    /**
     * dateformat(format) : 日付のフォーマット （※PHP関数 strftime() と同様）
     */
    private function _f_dateformat($text, $param) {

        if (is_array($text)) {
            return array_map($this->_f_dateformat, $text, $param);
        }

        return strftime($param, strtotime($text));
    }

    /**
     * sprintf(format) : テキストのフォーマット （※PHP関数 sprintf() と同様）
     */
    private function _f_sprintf($text, $param) {

        if (is_array($text)) {
            return array_map($this->_f_sprintf, $text, $param);
        }

        return sprintf($param, $text);
    }
}

if (!function_exists('evo')) {
    function evo() {
        global $modx;
        return $modx;
    }
}

if (!function_exists('db')) {
    function db() {
        global $modx;
        return $modx->db;
    }
}
