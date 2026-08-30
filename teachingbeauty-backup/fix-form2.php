<?php
/**
 * FC2 予約フォームの全項目を Contact Form 7 に完全再現し、
 * reserve.html の FC2 フォームを CF7 に置換、muryou は原本（フォーム無し）へ戻す。
 */
$backup = 'C:\\Users\\Administrator\\Documents\\New folder\\teachingbeauty-backup\\www\\';
$recipient = 'sy3208100@gmail.com';

// --- 治療コース（FC2の値そのまま） ---
$courses = array(
	'出張施術','鍼灸施術 ','突発性難聴','急性低音型感音性難聴','メニエール病',
	'耳管開放症、耳管狭窄症','耳鳴り、耳閉塞感','前置胎盤・低置胎盤','逆子','ダイエット鍼',
	'クレオパトラ美容鍼','ブライダル美容鍼','整体（骨盤矯正・猫背矯正・姿勢矯正）','美脚矯正・０脚矯正',
	'ソリッド小顔矯正','ハリウッド式小顔リフトアップ','交通事故・自賠責保険・むちうち施術','産前・産後骨盤矯正',
	'不妊症','男性不妊症','つわり','多嚢胞性卵巣症候群','脱毛症、薄毛施術、育毛','過敏性腸症候群（IBS）',
	'花粉症','眼科疾患','推拿療法（マッサージ・按摩・指圧）　30分・60分・120分','無料相談','その他',
);
$course_tags = '';
foreach ( $courses as $c ) { $course_tags .= ' "' . $c . '"'; }

$sources = array( 'HP','テレビ','雑誌','youtube','えきてん','鍼灸コンパス','看板','知人の紹介','SNS','その他' );
$source_tags = '';
foreach ( $sources as $s ) { $source_tags .= ' "' . $s . '"'; }

$age_tags = '';
for ( $i = 1; $i <= 120; $i++ ) { $age_tags .= ' "' . $i . '"'; }

$req = '<font size="1" color="#FF0000"><b> ※ </b></font>';
$form = <<<FORM
<p>下記フォームへ入力し [ 送信 ] ボタンを押してください。</p>
<table class="tb-form">
<tr><td class="td-item-title">お名前{$req}</td><td>姓：[text* sei]　名：[text* mei]</td></tr>
<tr><td class="td-item-title">ふりがな{$req}</td><td>姓：[text* kana-sei]　名：[text* kana-mei]</td></tr>
<tr><td class="td-item-title">年　齢{$req}</td><td>[select* age include_blank{$age_tags}] 歳</td></tr>
<tr><td class="td-item-title">メールアドレス{$req}</td><td>[email* mail]</td></tr>
<tr><td class="td-item-title">ＴＥＬ{$req}</td><td>[tel* tel]　例）123-456-7890</td></tr>
<tr><td class="td-item-title">住　所{$req}</td><td>〒[text* post1] - [text post2]<br>[text* address]</td></tr>
<tr><td class="td-item-title">希望する治療コース{$req}</td><td>[checkbox* course use_label_element{$course_tags}]</td></tr>
<tr><td class="td-item-title">希望日／時間(第2希望までお書き下さい。）{$req}</td><td>[text* hopedate]</td></tr>
<tr><td class="td-item-title">料金表の確認はされていますか？{$req}</td><td>[radio pricelist use_label_element "はい" "いいえ"]</td></tr>
<tr><td class="td-item-title">当サロンをどちらでお知りになりましたか？{$req}</td><td>[radio source use_label_element{$source_tags}]</td></tr>
<tr><td class="td-item-title">ご紹介者様がいらっしゃる場合は、その方のお名前をお知らせください。「その他」をお選びの場合は、当院をお知りになったきっかけをお聞かせください。</td><td>[text referrer]</td></tr>
<tr><td class="td-item-title">その他、ご質問やご不明な点がございましたら、こちらにご記入ください。</td><td>[textarea question]</td></tr>
<tr><td class="td-item-title">症状がいつ頃、どのような状況で現れたか、その後の経過と現在の状態について、できるだけ詳しくご記入ください。症状の頻度や程度、症状が強くなる状況、これまでに受けた診察や治療なども併せてお知らせください。</td><td>[textarea symptoms]</td></tr>
<tr><td class="td-item-title">予約ホームの※印はすべて入力はされましたか？</td><td>[radio confirmall use_label_element "はい" "いいえ"]</td></tr>
</table>
[submit "送信する"]
FORM;

$body = <<<BODY
Teaching Beauty ウェブサイトの予約フォームから送信がありました。

お名前　　　　　: [sei] [mei]
ふりがな　　　　: [kana-sei] [kana-mei]
年齢　　　　　　: [age] 歳
メールアドレス　: [mail]
TEL　　　　　　 : [tel]
住所　　　　　　: 〒[post1]-[post2] [address]
希望する治療コース: [course]
希望日／時間　　: [hopedate]
料金表の確認　　: [pricelist]
どちらでお知りに: [source]
ご紹介者様　　　: [referrer]
ご質問・ご不明点: [question]
症状の詳細　　　: [symptoms]
※印すべて入力　: [confirmall]

--
このメールは https://www.teachingbeauty.jp/ の予約フォームから自動送信されています。
BODY;

$f = wpcf7_contact_form( 1086 );
if ( ! $f ) { echo "CF7 form 1086 not found\n"; return; }
$props = $f->get_properties();
$props['form']                      = $form;
$props['mail']['recipient']         = $recipient;
$props['mail']['subject']           = '[Teaching Beauty] ウェブサイトからのご予約・お問い合わせ';
$props['mail']['sender']            = 'Teaching Beauty <wordpress@teachingbeauty.jp>';
$props['mail']['additional_headers'] = 'Reply-To: [mail]';
$props['mail']['body']              = $body;
$props['mail_2']['active']    = true;
$props['mail_2']['recipient'] = '[mail]';
$props['mail_2']['subject']   = '【Teaching Beauty】ご予約・お問い合わせありがとうございます';
$props['mail_2']['sender']    = 'Teaching Beauty <wordpress@teachingbeauty.jp>';
$props['mail_2']['body']      = "[sei] [mei] 様\n\nお問い合わせいただきありがとうございます。以下の内容で受け付けいたしました。追って担当者よりご連絡いたします。\n\n----------\n希望する治療コース: [course]\n希望日／時間: [hopedate]\n----------\n\nTeaching Beauty 鍼灸マッサージ整骨院・整体院\n〒154-0014 東京都世田谷区新町3-21-1 さくらウェルガーデン301号室\nTEL: 03-6413-7803";
$f->set_properties( $props );
$id = $f->save();
echo "CF7 form updated: {$id}, shortcode " . $f->shortcode() . "\n";

// --- 原本 body 取得 ---
function tb_origbody( $backup, $file ) {
	$raw = file_get_contents( $backup . $file );
	if ( preg_match( '/charset\s*=\s*["\']?\s*shift_jis/i', $raw ) ) {
		$raw = mb_convert_encoding( $raw, 'UTF-8', 'SJIS-win' );
	}
	return preg_match( '/<body[^>]*>(.*)<\/body>/is', $raw, $m ) ? trim( $m[1] ) : $raw;
}

$sc = '[contact-form-7 id="' . $f->hash() . '" title="ご予約・お問い合わせ"]';

// reserve: FC2 ブロックを CF7 に置換
$rb = tb_origbody( $backup, 'reserve.html' );
$rb = preg_replace( '/<!--\s*FC2.*?-->\s*<script[^>]*form1ssl\.fc2[^>]*>\s*<\/script>\s*<noscript>.*?<\/noscript>\s*<!--\s*FC2.*?-->/is', $sc, $rb, 1, $cnt );
global $wpdb;
$rp = get_page_by_path( 'reserve' );
$wpdb->update( $wpdb->posts, array( 'post_content' => $rb ), array( 'ID' => $rp->ID ) );
clean_post_cache( $rp->ID );
echo "reserve updated (FC2->CF7 replaced: {$cnt})\n";

// muryou: 原本（フォーム無し）に戻す
$mb = tb_origbody( $backup, 'muryou.html' );
$mp = get_page_by_path( 'muryou' );
$wpdb->update( $wpdb->posts, array( 'post_content' => $mb ), array( 'ID' => $mp->ID ) );
clean_post_cache( $mp->ID );
echo "muryou reverted to original (no form)\n";
