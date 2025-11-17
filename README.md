cfFormMailerマニュアル
=================

**v1.7.0**

* 設置＆使用方法
* 機能解説
* eFormとの差異
* バグレポート、お問い合わせ、サポート
* ご注意、免責事項
* 更新履歴

## 設置＆使用方法

### 【インストール】

1.  /assets/snippets/ フォルダ内に **cfFormMailer** というフォルダを作成し、そのフォルダ内に以下のファイルを配置（アップロード）します。

    * class.cfFormMailer.inc.php
    * bootstrap.php
    * additionalMethods.inc.php (※独自検証メソッドまたは独自フィルターメソッドを追加しない場合は、アップロードしなくても構いません。）
2.  （ファイルアップロードを使用する場合） [class.upload.php](http://www.verot.net/php_class_upload.htm)を /assets/snippets/cfFormMailer/ フォルダにアップロードします。（任意）
    ※「ユーザーがアップロードしたファイルの添付送信」参照  
    class.upload.phpはColin Verot氏作の画像アップロードライブラリです。
    ダウンロード先： http://www.verot.net/php_class_upload_download.htm
3.  （ファイルアップロードを使用する場合） プラグインcfFileViewをインストールします。
    「ユーザーがアップロードしたファイルの添付送信」を参照してください。  
4.  （送信内容を記録する場合） cfFormDBモジュールをインストールします。  
    詳細はcfFormDBのマニュアルを参照してください。

5.  modxの管理画面にログインし、以下の情報で新規スニペットを作成します。  

    * スニペット名： cfFormMailer  
    * スニペットコード欄： ファイル snippet.cfFormMailer.php の内容をコピー＆ペースト

6.  「環境設定」として利用するチャンクまたは設定ファイルを作成します。
    * 設定ファイルを使用する場合は、/assets/snippets/cfFormMailer/forms/sample/ フォルダ内の config.with_comment.ini をコピーして作成することができます。
    * スニペットタグで、チャンクまたは設定ファイルのパスを指定します。

7.  各設定項目の値をご利用の環境に合わせて書き換え、保存してください。  
    `#` で始まる行はコメントです。

### 【環境設定項目】

上記インストール手順6で作成するチャンクまたは設定ファイルには、以下の設定項目が用意されています。

#### 管理者宛メール関連

##### 管理者メール送信先 [admin_mail]

フォームの送信先メールアドレスを指定します。半角コンマ区切りで複数のメールアドレスを指定することができますが、複数のメールアドレスに送信したい場合はadmin_mail_ccやadmin_mail_bccの使用をおすすめします。

- `[デフォルト]` MODXグローバル設定の「送信者メールアドレス」

- `[設定例]` admin_mail = info@example.com

##### 送信者名 [admin_name]

上記”管理者メール送信先”へ送信されるメールの送信者名を指定します。

- `[設定例]` admin_name = フォーム送信者

##### 管理者宛メール件名 [admin_subject]
管理者宛に送信されるメールの件名を指定します。

- `[デフォルト]` サイトから送信されたメール|
- `[設定例]` admin_subject = メールフォームから送信されたメール|

##### 管理者宛メール HTMLメールフラグ [admin_ishtml]
管理者宛に送信されるメールがHTMLメールかどうかを指定します。  

- `[設定値]` 0・・・テキストメール / 1・・・HTMLメール  
- `[デフォルト]` 0

##### 管理者宛CCメール送信先 [admin_mail_cc]

管理者宛メールと同時にCCで送るアドレスを指定します。半角コンマ区切りで複数のアドレスを指定できます。

- `[設定例]` admin_mail_cc = test@example.com,info@example.jp

##### 管理者宛BCCメール送信先 [admin_mail_bcc]
管理者宛メールと同時にBCCで送るアドレスを指定します。半角コンマ区切りで複数のアドレスを指定できます。

- `[設定例]` admin_mail_bcc = test@example.com,info@example.jp

##### 管理者アドレスの動的変更 [dynamic_send_to_field]
フォーム中の特定項目の選択肢によって管理者メールアドレスを動的に変更する場合に、その項目名を指定します。  

「管理者アドレスの動的変更」参照。

#### 自動返信メール関連

##### 自動返信メールフラグ [auto_reply]
フォーム送信者に対して自動返信メールを送信するかどうかを指定します。  

- `[設定値]` 0・・・送信しない / 1・・・送信する  
- `[デフォルト]` 0

##### 自動返信メールの送信先として使用するフィールド名 [reply_to]
自動返信メールの送信先として使用するフィールド名を指定します。  

単一のフィールド、または複数のフィールドを組み合わせたものを指定できます。  

複数のフィールドを指定する場合は、半角プラス記号でフィールド名をつないで指定します。また、半角アットマークはそのまま記述します。  

- `[デフォルト]` email  
- `[設定例]` reply_to = email1+@+email2  

（この例の場合、email1フィールドの値が info で、email2フィールドの値が`example.com` だった場合、送信先アドレスは `info@example.com` になります。）

##### 自動返信メールの送信者メールアドレス [reply_from]
自動返信メールの送信者メールアドレスを指定します。

- `[デフォルト]` 管理者メールアドレス（admin_mail）
。admin_mailに複数のアドレスが指定されている場合は先頭のアドレス。  
- `[設定例]` reply_from = `info2@example.com`

##### 自動返信メールの送信者名 [reply_fromname]
自動返信メールの送信者名を指定します。  

- `[デフォルト]` modxサイト名（[(site_name)])  
- `[設定例]` reply_fromname = 株式会社○×

##### 自動返信メールの件名 [reply_subject]
自動返信メールの件名を指定します。  

- `[デフォルト]` 自動返信メール  
- `[設定例]` reply_subject = お問い合わせありがとうございます

##### 自動返信メールのHTMLメールフラグ [reply_ishtml]

自動返信されるメールがHTMLメールかどうかを指定します。  

- `[設定値]` 0・・・テキストメール / 1・・・HTMLメール  
- `[デフォルト]` 0

##### 添付するファイル [attach_file]

自動返信メールに添付するファイルを絶対パスで指定します。  

半角コンマ区切りで、複数のファイルを指定することができます。  

なお、ファイル名は必ず半角英数字で指定し、漢字・ひらがな・カタカナ・全角記号等は使わないでください。    

- `[設定例]` attach_file = /var/sample/pdf/guidebook.pdf,/var/sample/word/entrysheet.doc

##### 添付するファイルの名称 [attach_file_name]

前項で指定するファイル名に漢字・ひらがな等が使用できないため、添付送信するファイルにこれらの文字を使用したい場合に指定します。  

複数のファイルを指定する場合は、attach_fileでの指定順に合わせて、半角コンマで区切って指定します。  

- `[設定例]` attach_file_name = 募集要項.pdf,エントリーシート.doc

#### テンプレート関連


入力画面テンプレート [tmpl_input] 
入力画面のテンプレートとして使用するチャンク名またはリソースIDを指定します。  

[設定例] tmpl_input = tmpl_input (※チャンクを指定する場合）  

 　　　  tmpl_comp = 11 (※リソースを指定する場合）
確認画面テンプレート [tmpl_conf]
送信内容確認画面テンプレートとして使用するチャンク名またはリソースIDを指定します。  

[設定例] tmpl_conf = tmpl_conf (※チャンクを指定する場合）  

 　　　  tmpl_comp = 12 (※リソースを指定する場合）
完了画面テンプレート [tmpl_comp]
送信完了画面テンプレートとして使用するチャンク名またはリソースIDを指定します。  

[設定例] tmpl_comp = tmpl_comp (※チャンクを指定する場合）  

 　　　  tmpl_comp = 13 (※リソースを指定する場合）
管理者宛メールテンプレート [tmpl_mail_admin]
管理者宛メールのテンプレートとして使用するチャンク名またはリソースIDを指定します。  

[設定例] tmpl_mail_admin = tmpl_mail_admin (※チャンクを指定する場合）  

 　　　  tmpl_comp = 14 (※リソースを指定する場合）
自動返信メールテンプレート [tmpl_mail_reply]
自動返信メールのテンプレートとして使用するチャンク名を指定します。  

[設定例] tmpl_mail_reply = tmpl_mail_reply (※チャンクを指定する場合）  

 　　　  tmpl_comp = 15 (※リソースを指定する場合）
自動返信メールテンプレート（モバイル用） [tmpl_mail_reply_mobile]
モバイル宛自動返信メールのテンプレートとして使用するチャンク名を指定します。  

モバイル宛テンプレートを使用しない場合は空欄にしてください。  

[設定例] tmpl_mail_reply_mobile = tmpl_mail_reply_mobile (※チャンクを指定する場合）  

 　　　  tmpl_comp = 16 (※リソースを指定する場合）
送信後遷移する完了画面リソースIDまたはURL [complete_redirect]
送信完了と同時に完了画面（入力・確認画面とは違うURL）へ遷移するようにします。「完了画面テンプレート(tmpl_comp)」の指定より優先されます。  

リソースIDまたはURLを指定してください。  

[設定例] complete_redirect = 17 (※リソースIDで指定する場合）  

 　　　  complete_redirect = http://www.example.com/thanks.html （※URLで指定する場合）


#### その他の設定


CAPTCHAによる認証 [vericode]
CAPTCHAによる画像認証の仕様有無を指定します。  

[設定値] 0・・・使用しない / 1・・・使用する  

[デフォルト] 0
エラーフィールドに挿入されるクラス名 [invalid_class]
検証エラーのあるフィールド（input,textarea,selectタグ）に付加されるクラスセレクタ名を指定します。  

使用しない場合は空欄にしてください。  

[設定例] invalid_class = invalid
メール内HTMLタグ使用許可 [allow_html]
管理者宛や自動返信をHTMLメールとして送信する場合、フォーム送信者が記入したHTMLタグを有効にするかどうかを指定します。  

[設定値] 0・・・HTMLタグ使用不可（タグは無効化されます） / 1・・・使用する  

[デフォルト] 0

ファイルアップロード時に使用するディレクトリの設定[upload_tmp_path]
※このオプションは[「[バグ]cfFormMailerでファイルアップロードができない場合の対処](http://www.clefarray-web.net/blog/archive/2010/05/cfformmailer-2.html)」への対応です。  

フォーム送信者によるファイルアップロード送信機能を使用する場合に、アップロードされたファイルの一時保管方法を指定します。  

通常は0（デフォルト）で大丈夫ですが、XREAなど、SAFE Modeがonの環境でファイルアップロード送信がうまくいかない場合は1にしてください。      

（1の場合、asset/snippets/cfFormMailer/tmpディレクトリに書き込みができるようパーミッション設定を行ってください。）  

[設定値] 0･･･サーバデフォルトのテンポラリエリア / 1･･･スニペットディレクトリ内のtmpディレクトリ  

[デフォルト] 0

改行スタイル[lf_style]
送信するメールのヘッダに使われる改行コード。通常は変更しなくても大丈夫だと思います。  

[設定値] 0･･･\n (linuxスタイル) / 1･･･\r\n (windowsスタイル)  

[デフォルト] 0

送信内容をデータベースに記録する[use_store_db]
フォームの送信内容をデータベースに記録します。  

この機能を使用するには、あらかじめcfFormDBモジュールをインストールし、記録用テーブルを作成しておく必要があります。  

[設定値] 0･･･記録しない / 1･･･記録する  

[デフォルト] 0

メール送信フラグ[send_mail]
メールを送信するかどうかを指定します。0を指定すると、管理者宛、自動送信宛ともに送信しなくなります。  

[設定値] 0･･･送信しない / 1･･･送信する  

[デフォルト] 1
送信するメールの文字コード [mail_charset]
送信されるメールの文字コードを指定します。  

v1.2までは日本語メール送信における一般的な設定値である「ISO-2022-JP」に固定していましたが、unicodeの文字をそのまま送信したい場合（例：機種依存文字など）のために指定できるようにしました。  

設定値は、一般的には iso-2022-jp または utf-8 を指定します。  

[設定例] mail_charset = utf-8  

[デフォルト] iso-2022-jp


### 【テンプレートの作成 】

入力・確認・完了の各画面や、送信されるメールのテンプレートは、チャンクまたはリソース、ファイルによって管理されます。
各画面のテンプレートとなるチャンク名やファイルパスは環境設定内で指定できます。
/assets/snippets/cfFormMailer/forms/sample/ フォルダ内に各テンプレートのサンプルがありますので、参考にしてみて下さい。

1.  「入力画面」テンプレートの作成

      任意の名称（デフォルト：tmpl_input）で新規チャンクを作成し、入力画面のHTMLを記述します。

      サンプル： web_form.tpl.html

2.  「確認画面」テンプレートの作成

      任意の名称（デフォルト：tmpl_conf）で新規チャンクを作成し、確認画面のHTMLを記述します。

      サンプル： web_confirm.tpl.html

3.  「完了画面」テンプレートの作成

    任意の名称（デフォルト：tmpl_comp）で新規チャンクを作成し、完了画面のHTMLを記述します。

    サンプル： web_thanks.tpl.html

4.  「管理者宛メール」テンプレートの作成

    任意の名称（デフォルト：tmpl_mail_admin）で新規チャンクを作成し、管理者宛メールの本文テンプレートを記述します。

    サンプル： mail_receive.tpl.txt

5.  「自動返信メール」テンプレートの作成

    任意の名称（デフォルト：tmpl_mail_reply）で新規チャンクを作成し、自動返信メールの本文テンプレートを記述します。

    サンプル： mail_autoreply.tpl.txt（テキストメール）、mail_autoreply.tpl.html（HTMLメール）

6.  「自動返信メール（モバイル用）」テンプレートの作成　※PCメール宛と携帯メール宛でテンプレートを変えたい場合

    任意の名称で新規チャンクを作成し、モバイル端末用の自動返信メール本文テンプレートを記述します。

    ここで作成したチャンクの名称を、設定ファイル内の tmpl_mail_reply_mobile 項目に記述します。  

### 【旧バージョンからのバージョンアップ】

【v1.6からのバージョンアップ】
assets/snippets/cfFormMailer/フォルダ内のすべてのファイルを置き換え、スニペットの内容をsnippet.cfFormMailer.phpと置き換えます。
設定ファイルやテンプレートは従来のものがそのまま使用できます。

【v1.3以前からのバージョンアップ】
assets/snippets/cfFormMailer/フォルダ内のすべてのファイルを置き換え、スニペットの内容をsnippet.cfFormMailer.phpと置き換えます。
設定ファイルの形式が変更されているため、新しい.ini形式の設定ファイルに移行することをお勧めします。
サンプルファイルの配置が変更されています（forms/sampleフォルダに移動）。
独自検証メソッド、独自フィルターを定義している場合は、additionalMethods.inc.phpファイル内に定義してください。

### 【スニペットタグの書式】

```
[!cfFormMailer?&config=`チャンク名または設定ファイルのパス`!]
```

config パラメータは必須です。チャンク名または設定ファイルのパスを指定します。
ファイルパスで指定する場合は、`@FILE:相対パス` の形式で指定します（例：`@FILE:forms/myform/config.ini`）。

## 機能解説

### 【入力値の検証】

フォーム内の各フィールドに対して、入力値の検証を行うことができます。

####  [書式]  

```
<input type="text" name="field_name" value="" valid="required:method:label" />
```

required

入力必須かどうか（1-yes / NULL-no)

method

検証方法。コンマ区切りで複数指定可。以下の標準装備の方式以外にも、独自の方式も指定可能。（下記「検証メソッドの追加」参照。）  

ルール名|検証内容
---|---
email|メールアドレスチェック（検証前に半角に強制変換）
num|数値チェック（検証前に半角に強制変換）
len(n-m)|文字数チェック（m文字以下 / n～m文字 / n文字以上）
range(n~m)|数値の値範囲チェック（m以下 / n～m / n以上）※n,mで指定した数値は範囲に含まれます
sameas|他のフィールドと組み合わせて使います。「フィールド名」で指定したフィールドと同値かをチェック。
tel|正しい電話番号形式かどうかをチェック（半角に強制変換）
vericode|画像認証
allowtype|アップロードを許可するファイルの種別。`|` 記号で区切って複数指定可。gif/jpg/png/pdf/txt/html/word
allowsize|アップロードを許可する最大ファイルサイズ。キロバイト単位で指定。
convert|「変換オプション」に従って入力値を変換します。変換オプションはPHP関数 [mb_convert_kana()](http://jp.php.net/manual/ja/function.mb-convert-kana.php) と同じものとなり、未指定の場合は「**K**」（半角カタカナを全角カタカナに変換）となります。※このメソッドのみ”検証”ではなく”変換”となります。検証エラーが発生することはありません。
url|入力値がURIかどうかを簡易的にチェックします。
label|エラー表示用（[+errors+]プレースホルダ利用時）に使用する項目名。`<label>`を使用したラベルよりも優先されます。未指定の場合はname属性の値が使用されます。


####  [例]  

```
<input type="text" name="age" size="3" valid="1:num" />
```

この項目は入力必須であり、数字（0-9）のみ入力を許可する

```
<input type="text" name="pass" size="10" valid=":len(-8)" />
```

8文字以内で入力させる

```
<input type="text" name="tel" size="10" valid=":num,len(10-12)" />
```

10文字以上12文字以内の数値のみ許可する

```
<input type="text" name="age" size="4" valid="1:num,range(20~)" />
```

20歳以上のみ許可する

```
<input type="password" name="password" valid="1" />
<input type="password" name="password_confirm" valid="1:sameas(password)" />`
```

同じパスワードを２回入力させる

```
<input type="text" name="kana" valid="1::お名前（フリガナ）" />
```

入力エラーのあった項目名を指定。このテキストボックスが未入力だった場合の`[+errors+]`プレースホルダ。

```
[お名前（フリガナ）] 入力必須項目です
```
確認画面で上記のように出力されます。

```
<input type="file" name="picture" valid=":allowtype(gif|jpg)" />
```

GIFとJPEG画像のみアップロードを許可する

```
<input type="file" name="photo" valid="1:allowsize(100)" />
```

アップロード可能最大サイズを100KBにする  

```
<input type="text" name="kana" valid="1:convert(C)" />
```

全角カタカナを全角ひらがなに変換する  

以上で挙げた検証の他に、「自動返信」を使用する場合は”自動返信先”（reply_to）がメールアドレスとして正しいかどうかを必ずチェックします。
また、これにより自動的にフィールド `reply_to` が生成されますので、エラーメッセージ表示の際のフィールド名として利用できます。

### 【追加タグ】

主にエラーメッセージ表示用に、以下の独自タグをサポートしています。

```
<iferror></iferror>
```

エラーが存在する場合にタグ内を表示

```
<iferror.フィールド名></iferror>
```

指定したフィールドにエラーが存在する場合にタグ内を表示

```
<iferror.name>
<p class="error">氏名を入力してください</p>
</iferror>
```

```
<iferror.(f1,f2[,f3...])></iferror>
```
f1またはf2（またはf3...。すべてフィールド名）項目にエラーが存在する場合にタグ内を表示  

```
<iferror.(name,kana)>
<p class="error">氏名とふりがなを入力してください</p>
</iferror>
```

###  【プレースホルダ】

各テンプレート内で使用可能。


[+フィールド名+]

フィールド名に対応する値

[+error.フィールド名+]

指定したフィールドに割り当てられているエラーメッセージ  

（エラーメッセージが複数割り当てられている場合、<br />タグで区切って表示。下記「表示フィルタ」を使用して変更することも可能。）

#### -- システムで予約済みのもの --


[+errors+]

全てのエラーを表示（初期値は`<br />`区切り。下記「表示フィルタ」で変更可能。）  

`<input>`タグや`<select>`タグに対して適切に`<label>`タグを使用することで、エラーのあるフィールド名が`<label>`タグで囲まれた表記で表示されます。


##### [例]

`<label for="name">お名前</label><input type="text" name="name" valid="1" id="name" />`  
→ [お名前] 入力必須項目です

※v1.0以降、valid属性で指定するlabelのほうが優先されます。  
`<label for="name">お名前</label><input type="text" name="name" valid="1::氏名" id="name" />`  
→[氏名] 入力必須項目です

[+verimageurl+]
画像認証コードとして使用する画像の URI


#### メールテンプレート用（「管理者宛メール」「自動返信メール」テンプレートチャンク内でのみ使用できます）

[+adminmail+]

管理者メールアドレス（環境設定の admin_mail と同値）

[+reply_to+]

自動返信の宛先メールアドレス

[+senddate+]

送信日時（デフォルト書式： Y-m-d H:i:s。下記「表示フィルタ」の dateformat を利用して変更可能。）

[+sender_ip+]

送信者のIPアドレス

[+sender_host+]

送信者のホスト名（逆引きできない場合は IPアドレス）

[+sender_ua+]

送信者のユーザーエージェント


### 【表示フィルタ】

上記プレースホルダの出力に対して任意のフィルターをかけることが可能。

#### [書式] 

**[+フィールド名|フィルタ名（パラメータ）+]** 


フィールド名
フォームのフィールド名（name="xxx")
フィルタ名、パラメータ
適応させるフィルタ名とパラメータ（任意）。標準では以下のフィルタを備えています。



implode(string)
string で区切って表示 ※該当フィールドが配列の場合のみ有効。

implodetag(string)
値を`<string></string>`タグで囲んで表示 ※該当フィールドが配列の場合のみ有効。

dateformat(format)
format に従い日付書式を変換  ※PHP関数 strftime() と同様。

num
数字をフォーマット ※PHP関数 number_format() と同様。ただし第2引数以降は未対応。

sprintf(format)
整形して表示　※PHP関数 sprintf() と同様。


####  [例]

`[+errors+] (フィルタを指定しない場合）`

→出力：`[お名前：]入力必須項目です<br />[メールアドレス：]メールアドレスの形式が正しくありません<br />[性別：]入力必須項目です`

`[+errors|implode( / )+]`

→出力：`[お名前：]入力必須項目です / [メールアドレス：]メールアドレスの形式が正しくありません / [性別：]入力必須項目です`

`<ul>[+errors|implodetag(li)+]</ul>`

→出力： `<ul><li>[お名前：]入力必須項目です</li><li>[メールアドレス：]メールアドレスの形式が正しくありません</li><li>[性別：]入力必須項目です</li></ul>`

### 【検証メソッドの追加】

以下の仕様に則った関数を使用して、任意の検証メソッドを追加することができるます。

#### [関数仕様]  

1.  名前が 「_validate_検証名」 となる関数を作成する
2.  引数として 2つの値を受け取るようにする。1つ目はユーザが入力した値、2つ目は検証メソッドのパラメータ（カッコ内の数値）
3.  これらの引数を基に検証する
4.  正しい値の場合は TRUE を、それ以外の場合はエラーメッセージを返値として指定する

####  [登録のやり方]  

「関数仕様」に則った関数を/assets/snippets/cfFormMailer/**additionalMethods.inc.php** 内に作成するだけで定義できます。  

#### [例]

```
/* 正しい郵便番号かどうかを検証 */
function _validate_postcode($data, $param) {
return preg_match("/\d{3}\-\d{4}/", $data) ? TRUE : '郵便番号が正しくありません';
}
```


### 【フィルターの追加】

任意の出力整形フィルタを加えることができます。大まかな流れは、上記「検証メソッドの追加」と同様。

#### [関数仕様]      

1.  名前が「_filter_フィルタ名」 となる関数を作成する
2.  引数として 2つの値を受け取るようにする。1つめは整形対象となるテキスト、2つめはフィルタメソッドのパラメータ（カッコ内の数値）
3.  これらの引数を基に整形する
4.  返値として、整形後の値を返す

####  [登録方法] 

「関数仕様」に則った関数を/assets/snippets/cfFormMailer/**additionalMethods.inc.php** 内に作成するだけで定義できます。

### 【自動返信】

環境設定内の **auto_reply** を 1 、**reply_to** にメールアドレスとして使用するフィールド名を指定することで、"管理者"宛のメールのほかにフォーム送信者に対して自動返信を行うことができます。
※1 reply_to で指定したフィールドは正しいメールアドレス形式かどうかをチェックされます。
※2 reply_to は、1つのフィールド名、または複数のフィールド名を + 記号で繋げて指定します。

#### [例] 

`reply_to = email` //name="email" のフィールド値を宛先として使用  
`reply_to = email1+@+email2` //name="email1"のフィールド値＋アットマーク＋name="email2"のフィールド値を宛先として使用

### 【画像認証コード（CAPTCHA)の使用】

v0.0.5 から画像認証コードが利用できるようになっています。
環境設定内で CAPTCHA 使用を宣言し（**vericode = 1**）、以下の例のような、src 属性に **[+verimageurl+]** プレースホルダを指定した`<img>`タグと、検証項目に **vericode** を指定した`<input>` タグを、入力画面テンプレート内に作成します。

#### [例]

```
<img src="[+verimageurl+]" />
<input type="text" name="veri" valid="1:vericode" />
```

※MODX 本体と同じクラスを利用するため、画像として表示される文字列は [ツール]>[グローバル設定]>[詳細設定]タブの「CAPTCHA用ワード」と同様になります。

### 【自動返信メールテンプレートの切り替え】

自動返信メールの宛先がモバイル端末(※1)の場合に、使用するテンプレートを変更することができます。
使用するテンプレートをチャンクとして作成し、そのチャンク名を環境設定の「tmpl_mail_reply_mobile」項目に指定します。

※1 ”モバイル端末”と判別されるメールアドレスは、末尾が以下の場合です。

* `docomo.ne.jp`
* `ezweb.ne.jp`
* `softbank.ne.jp`
* `vodafone.ne.jp`
* `disney.ne.jp`
* `pdx.ne.jp`
* `willcom.ne.jp`
* `emnet.ne.jp`


### 【指定したファイルの添付送信】

フォーム送信者への自動返信メール送信の際に、任意のファイルを添付して送信することができます。
環境設定内の以下の項目を設定してください。


attach_file
送信するファイルの絶対パス（例： /var/www/path/to/file/sample.pdf )。半角コンマ区切りで複数のファイルを指定することができます。

必ず半角英数字で指定してください。
attach_file_name
送信するファイルに付けるファイル名（拡張子まで含む）。省略の場合は「attach_file」のファイル名と同様。

attach_fileは半角英数字に限定されるため、日本語名で送信したい場合に使用してください。（例： 資料.pdf など。）



### 【ユーザーがアップロードしたファイルの添付送信】

`<input type="file" />`でアップロードされたファイルを、管理者宛メールと自動返信メールに添付して送信することができます。
検証メソッドallowtypeとallowsizeで、アップロードできるファイルを限定することをお勧めします。
[注意]
"SAFE MODE Restriction in effect"というエラーが発生する場合は、環境設定のupload_tmp_pathを1にしてみてください。

#### [プラグインの追加]

！送信内容確認画面でアップロードされたファイルを表示するには、プラグインの追加が必要です！

アップロードされたファイルは、送信完了するまでブラウザからはアクセスできない一時ディレクトリに保管されるため、送信内容確認画面で表示するために追加処理が必要となります。  
以下の手順に従って、プラグインcfFileViewをインストールしてください。  
※確認画面でアップロードされたファイルを表示させない場合は、インストールする必要はありません。

1.  以下の情報で新規プラグインを作成します。_  
プラグイン名： **cfFileView**  
プラグインコード： ダウンロードしたcfFormMailerパッケージ内 **plugin.cfFileView.php** ファイルの内容をコピー＆ペースト
2.  システムイベントタブをクリックし、**OnPageNotFound**にチェックを入れます。

以上でインストール完了です。

#### [ファイルの追加]

！画像アップロードライブラリclass.upload.phpの追加を推奨します！

アップロードされたファイルのMIMEタイプ判別（と、将来の拡張）のために、Colin Verot氏作の画像アップロードライブラリであるclass.upload.phpを使用することを推奨します。  
一応無くても動作するようにはなっていますが、画像以外のファイルのアップロードを許可する場合は、最適なPHPの動作環境（[Fileinfo関数](http://www.php.net/manual/ja/book.fileinfo.php)が利用できること）とともに、このclass.upload.phpを使用するようにしてください。

書庫ファイル内には格納しておりませんので、[class.upload.phpのページ](http://www.verot.net/php_class_upload.htm)からダウンロードし、/assets/snippets/cfFormMailer/フォルダ内にアップロードしてください。

#### [確認画面でのファイル指定]

アップロードされたファイルは、プラグインcfFileViewを利用して表示させますので、`<img>`タグや`<a>`タグのURLとして、以下のように記述します。

**cfFileView?field=フィールド名**

画像なら`<img>`タグのsrc属性、それ以外なら`<a>`タグのhref属性に指定して別画面で表示させるという方法が良いと思います。  
「テンプレートでの記述例」を参考にしてください。

#### [追加されるプレースホルダ]

以下のプレースホルダが追加され、「確認画面テンプレート」で使用できます。

##### アップロードされたファイルが画像の場合


[+フィールド名.imagewidth+]
アップロードされた画像の幅
[+フィールド名.imageheight+]
アップロードされた画像の高さ

[+フィールド名.imagename+]
ファイル名
[+フィールド名.imagetype+]
ファイルの形式。以下の何れかとなります。  

GIF / JPG / PNG / PDF / TXT / HTML / WORD 



##### アップロードされたファイルが画像以外の場合


[+フィールド名.filename+]
ファイル名
[+フィールド名.filetype+]
ファイルの形式。以下の何れかとなります。  

GIF / JPG / PNG / PDF / TXT / HTML / WORD




#### [テンプレートでの記述例]


画像の場合(1)

入力画面
```
<p><input type="file" name="pic" valid=":allowsize(1024)" /></p>
```
確認画面
```
<p><img src="cfFileView?field=pic" width="[+pic.imagewidth+]" height="[+pic.imageheight+]" alt="[+pic.imagename+]" /></p>
```

実際の出力

```
<p><img src="cfFileView?field=pic" width="240" height="320" alt="画像です.jpg" /></p>
```
画像の場合(2)

入力画面

```
<p><input type="file" name="photo" valid=":allowtype(gif|jpg)" /></p>
```

確認画面

```
<p><img src="cfFileView?field=photo" alt="" width="100" /></p>
```

実際の出力

```
<p><img src="cfFileView?field=photo" alt="" width="100" /></p>
```

画像以外のファイルの場合

入力画面

```
<p>
<input type="file" name="profile" valid="1:allowtype(pdf|txt)" />
PDFまたはテキスト形式のプロフィールをアップロードしてください
</p>
```

確認画面

```
<p>
<a href="cfFileView?field=profile" target="_blank">[+profile.filename+]</a>
【[+profile.filetype+]形式】
</p>
```

実際の出力

```
<p>
<a href="cfFileView?field=profile" target="_blank">○○のプロフィール.pdf</a>
【PDF形式】
</p>
```

#### [その他仕様]

※アップロードされたファイルは、アップロード時のファイル名（クライアントマシンの元のファイル名）で添付されます。  
※アップロードされたファイルは、メール送信時と、確認画面から入力画面に戻った場合に削除されます。

### 【管理者アドレスの動的変更】

フォーム中の特定項目の選択内容によって、管理者メールアドレスとなるメールアドレスを変化させることができます。  
例えば「問い合わせ内容」として「製品について」「サイトについて」「求人について」「4. その他」という項目を設置した場合に、「製品について」を選択した場合はproduct@example.com宛、「3. 求人について」を選択した場合はrecruit@example.com宛に送信する、といった設定を行うことができます。

例）問い合わせ内容：

```
<select name="">
<option>製品について</option>
<optionサイトについて</option>
<option>求人について</option>
<option>その他</option>
</select>
```
#### [設定ファイルでの指定方法]

dynamic_send_to_field = 選択によってメール送信先を変更するフィールド名  
例） dynamic_send_to_field = type

対象となるフィールドは`<select>`または`<input type="radio"〜 />`である必要があります。

#### [テンプレートでの記述例]

入力画面テンプレート内の、「dynamic_send_to_field」で指定したフィールドの各選択肢に「**sendto**」属性を追加します。

例）　dynamic_send_to_field = type と設定した場合のセレクトリストの場合：  

```
<p>お問い合わせ内容
<select name="type">  
  <option value="製品について" sendto="product@example.com">製品について</option>  
  <option value="サイトについて" sendto="info@example.com">サイトについて</option>  
  <option value="求人について" sendto="recruit@example.com">求人について</option>  
  <option value="その他" sendto="info@example.com">その他</option>  
</select>  
</p>
```

例）　dynamic_send_to_field = type と設定した場合のラジオボタンの例：

```
<p>お問い合わせ内容  
  <input type="radio" name="type" value="製品について" sendto="product@example.com" />製品について  
  <input type="radio" name="type" value="サイトについて" sendto="info@example.com" />サイトについて  
  <input type="radio" name="type" value="求人について" sendto="recruit@example.com" />求人について  
  <input type="radio" name="type" value="その他" sendto="info@example.com" />その他  
</p>
```

■ eFormとの差異
-----------

標準で添付されるスニペット eForm と比べて、以下の点に対応していません。

* イベントをトリガーにして何かする


■ バグレポート、お問い合わせ、サポート
--------------------

ブログ「網的脚本実験室」まで  
http://www.clefarray-web.net/blog/

または、MODXの公式日本語フォーラムでも受け付けています。

■ ご注意、免責事項
-----------

※本スクリプトは MODX と同様、GPL ライセンスの元で配布されています。  
※本スクリプトに関してのメール等での個別のお問い合わせはご遠慮ください。  
※本スクリプトの使用によって生じた損害等について、作者は一切の責務を負わないこととさせていただきます。ご了承ください。  
※バージョン1.0以降、動作検証は MODX Evolution 1.0.x 日本語版にて行っています。また文字コードは UTF-8 に限定しております。

■ 更新履歴
-------

2025-XX-XX v1.7.0
[CHANGE] バージョン番号を1.6.1から1.7.0に更新

[FIX] セッションからのファイルアップロード処理の改善

[FIX] 各種バグ修正とコードクリーンアップ

2020-03-03 v1.6
[CHANGE] ディレクトリ構成の変更（サンプルファイルをforms/sampleへ移動）

[CHANGE] 設定ファイル形式を.ini形式に変更

[NEW] @FILE:パス形式でのテンプレート指定をサポート

[NEW] スパム判定機能の追加 (isSpam)

[FIX] PHP7対応

[FIX] 非公開リソースに設定を書いた場合の動作修正

[FIX] 複数行で記述されたフォームタグの解析改善

[FIX] 異体字（草彅・山﨑など）の文字化け対策

[CHANGE] get_magic_quotes_gpc()の削除（PHP7.4対応）

[CHANGE] コードのリファクタリング

2015-03-02 v1.4
[CHANGE] MODXMailerを直接使用するように変更（phpmailer_ex.php削除）

[FIX] 各種バグ修正

2013-05-23 v1.3
[NEW] 管理者メールのCC, BCC送信　（admin_mail_cc, admin_mail_bcc)

[NEW] メール送信文字コードの指定 (mail_charset)

[NEW] 送信後遷移する完了画面を指定可能に（complete_redirect)

[NEW] 選択肢による管理者メール送信先の動的変更 (dynamic_send_to_field )

[CHANGE] 設定チャンクサンプルconfig_chunk.txtでの「reply_to」「use_store_db」「vericode」の初期値変更
2011-04-04 v1.2
[NEW] cfFormDBとの連携機能追加  

[NEW] 検証メソッド”url”, ”convert”追加  

[NEW] 環境設定 use_store_db, send_mail 追加  

[CHANGE] tel検証メソッドを使用した項目は全角英字・ハイフンも半角に変換（従来は全角数字のみ）  

[FIX] 長い件名への対応強化
2010-11-10 v1.1r2
[FIX] メール送信者名やメール件名が長い場合に不要な文字が挿入されてしまう不具合に対処  

（ただしWindows版PHPからmail関数でメール送信する設定の場合は正常動作しない可能性があります。）  

[FIX] ユーザーからのファイルアップロードの際、サーバー環境によって保存がうまくいかない場合に対処 （環境設定 upload_tmp_path 追加）
2010-05-12 v1.1
[FIX] 開始タグと閉じタグの間にテキストの無い偶数個目のタグ（<textarea>タグなど）について値の復元が正しく行われない不具合を修正(thanks to trickstarさん）  

[CHANGE] 
同梱のサンプルテンプレートの名称を変更（thanks to yamaさん / 公式フォーラムより）  

[CHANGE] cfFileViewプラグインを MODx v1.0.3でのプラグインインストール半自動化に対応

2010-03-06 v1.0
[NEW] ファイルを添付しての送信に対応。（環境設定 attach_file, attach_file_name追加）  

[NEW] ユーザーからの画像ファイルアップロードと送信に対応  

[NEW] 携帯端末宛自動返信メールのテンプレートをPC宛とは独立して設定可能に。（環境設定 tmpl_mail_reply_mobile追加）  

[NEW] valid属性の3番目のパラメータを[+errors+]表示時の項目名として利用  

[CHANGE] 独自検証メソッド、独自フィルターメソッドの追加方法を変更（※従来の方法もひとまず使用可能ですが、非推奨）


[CHANGE] 環境設定admin_nameの初期値を、「サイト名」から未定義に変更  

[CHANGE] class.cfFormMailerMODx.inc.phpを廃し、メインクラス内に統合


[FIX] メールの件名と送信者名が文字化けする場合がある不具合に対処  

[FIX] selectタグ、textareaタグへINVALID_CLASSを付加する際に余分なスラッシュを付加してしまう問題を修正  

[FIX] サンプルテンプレートでのID重複を削除

2009-09-12 v0.0.7.2
[FIX] 独自検証メソッド、フィルターメソッド追加に関する不具合を修正  

[FIX] フォームの各属性の値が大文字で記述されていた場合に、エラー画面で入力値が反映しない不具合を修正  

[FIX] 空のvalue属性があった場合にタグが崩れる不具合を修正  

[FIX] 入力画面サンプルチャンクの<form>タグにaction属性を追加

2007-11-13 v0.0.7.1
[FIX] 前後にタブを含む<option>タグを正常に処理できない不具合を修正  

[FIX] 初期選択値（selected="selected")が指定されている場合は削除

2007-11-04 v0.0.7
[NEW] HTML メール送信に対応  

[NEW] 検証メソッド”tel”追加  

[NEW] 自動返信先となるメールアドレスを任意指定可能に  

[NEW] [+reply_to+]プレースホルダ追加  

[CHANGE] 自動返信先として指定したフィールドは必ず、メールアドレス形式として正しいかを検証（→検証メソッド email は付けなくて良いです）  

[FIX] EUC-JP 環境化でのメール文字化け解消

2007-10-23（未公開） v0.0.6
[NEW] [+sender_ip+][+sender_host+][+sender_ua+]プレースホルダ追加  

[NEW] 管理者宛メールアドレス（admin_mail）に複数のメールアドレスを指定可能に  

[NEW] 検証メソッド”sameas”追加  

[NEW] エラーのあるフィールドに任意のクラスセレクタを付加  

[NEW] admin_ishtml, reply_ishtml, reply_fromname, invalid_class 設定項目を追加  

[CHANGE] mb_send_mail()関数ではなく MODx 付属の PHPMailer クラスを使用するように変更。送信メールの文字コードは iso-2022-jp。  

[FIX] 一部の設定項目省略値が反映されない不具合を修正

2007-10-16（未公開）v0.0.5
[NEW] CAPTCHA 認証コードに対応  

[NEW] 検証メソッド”range”追加  

[FIX] 入力値が空(NULL）の場合は確認画面表示時に   に変換

2007-10-09 v0.0.4
[FIX] [+errors+]が効かない不具合を修正  

[FIX] システムが付加するフィールドはアンダースコア(_)から始まる名称に変更  

[NEW] <label>タグに対応  

[NEW] 入力必須項目のエラーメッセージで、ラジオボタンやリストのときは「選択必須項目です」と表示（他は「入力必須項目です」）
