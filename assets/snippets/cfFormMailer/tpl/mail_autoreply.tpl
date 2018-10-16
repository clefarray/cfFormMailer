<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<style type="text/css">
th { vertical-align: top; text-align: left; color: #1E6C8A; padding-right: 1em;}
fieldset { margin-bottom: 20px;}
legend { color: #1E6C8A;}
.note { color:#666;}
</style>
</head>
<body>
<p class="note">---------------------------------------------------</br />
　これは自動返信メールです<br />
---------------------------------------------------</p>
<u><strong>[+name_f+] [+name_l+]</strong> 様</u>
<p>お問い合わせありがとうございました。<br />
以下の内容で受け付けましたのでご確認ください。</p>
<p>送信日時： [+senddate|dateformat(%Y年%m月%d日 %H時%M分)+]</p>


  <fieldset><legend>個人情報</legend>
  <table>
    <tbody>
      <tr>
        <th>お名前</th>
        <td>[+name_f+] [+name_l+]</td>
      </tr>
      <tr>
        <th>会社名</th>
        <td>[+company+]</td>
      </tr>
      <tr>
        <th>メールアドレス</th>
        <td>[+reply_to+]</td>
      </tr>
      <tr>
        <th>性別</th>
        <td>[+gender+]</td>
      </tr>
      <tr>
        <th>郵便番号</th>
        <td>[+zip1+] - [+zip2+]</td>
      </tr>
      <tr>
        <th>住所</th>
        <td>[+pref+] [+addr1+] [+addr2+] [+addr3+]</td>
      </tr>
      <tr>
        <th>電話番号</th>
        <td>[+tel+]</td>
      </tr>
      <tr>
        <th>FAX番号</th>
        <td>[+fax+]</td>
      </tr>
    </tbody>
  </table>
  </fieldset>
  <fieldset><legend>お問い合わせ内容</legend>
  <table>
    <tbody>
      <tr>
        <th>お問い合わせ内容</th>
        <td>[+summary+]</td>
      </tr>
      <tr>
        <th>メッセージ</th>
        <td>[+message+]</td>
      </tr>
      <tr>
        <th>優先する連絡方法</th>
        <td>[+way+]</td>
      </tr>
    </tbody>
  </table>
  </fieldset>
  <fieldset><legend>アンケート</legend>
  <table>
    <tbody>
      <tr>
        <th>既にお持ちの商品</th>
        <td><ul>[+own|implodetag(li)+]</ul></td>
      </tr>
      <tr>
        <th>印象に残った商品</th>
        <td><ul>[+impression|implodetag(li)+]</ul></td>
      </tr>
    </tbody>
  </table>
  </fieldset>

<hr />

<p>○×株式会社<br />
□△課 ○○　○○</p>
<address>Email: <a href="mailto:[+adminmail+]">[+adminmail+]</a><br />
<a href="http://example.com/">http://example.com</a></address>
</body>
</html>
