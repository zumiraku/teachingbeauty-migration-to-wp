$www='C:\Users\Administrator\Documents\New folder\teachingbeauty-backup\www'
$out='C:\Users\Administrator\Documents\New folder\teachingbeauty-backup\teachingbeauty-fullbody.wxr'
$sjis=[System.Text.Encoding]::GetEncoding(932); $utf=[System.Text.Encoding]::UTF8
$pages=@('index','concept','menu','news','muryou','reserve','kanja','nhk','incho','access','sinkyu','beauty','esthetichtml','kokushi','privacy','kyuujin','funin','kafun','sakago','taiban','hari','kyuu','seitai','kairopura','miminari','Meniere','tsuwari','newpage4','newpage5','newpage7','newpage10','newpage13','newpage14','newpage17','newpage22','newpage23','newpage24','newpage25','newpage27')

function ReadPage($slug){ $f=Join-Path $www ($slug+'.html'); if(-not(Test-Path $f)){return $null}; $b=[System.IO.File]::ReadAllBytes($f); $ascii=[System.Text.Encoding]::ASCII.GetString($b); $e= if($ascii -match '(?i)charset\s*=\s*["'']?\s*shift_jis'){$sjis}else{$utf}; $e.GetString($b) }
function Xesc($s){ ($s -replace '&','&amp;' -replace '<','&lt;' -replace '>','&gt;') }
function CDwrap($s){ '<![CDATA[' + ($s -replace ']]>',']]]]><![CDATA[>') + ']]>' }

$items=New-Object System.Collections.Generic.List[string]
$order=0
foreach($slug in $pages){
  $html=ReadPage $slug; if($null -eq $html){ Write-Output "MISS $slug"; continue }
  # body inner + body attributes
  $bm=[regex]::Match($html,'(?is)<body([^>]*)>(.*)</body>')
  $body = if($bm.Success){ $bm.Groups[2].Value } else { $html }
  $bodyattr = if($bm.Success){ $bm.Groups[1].Value.Trim() } else { 'id="hpb-template-05-08-01" class="hpb-layoutset-02"' }
  # 破損した http 追跡スクリプトのみ除去（表示は不変）。それ以外は原文のまま。
  $body=[regex]::Replace($body,'(?is)<script[^>]*counter1\.fc2\.com[^>]*>\s*</script>','')
  $body=$body.Trim()
  # head meta
  $title=''; if($html -match '(?is)<title>(.*?)</title>'){ $title=($Matches[1] -replace '\s+',' ').Trim() }
  if($title -eq ''){ $title=$slug }
  $desc=''; if($html -match '(?is)<meta[^>]*name\s*=\s*["'']?description["'']?[^>]*content\s*=\s*["'']([^"'']*)["'']'){ $desc=$Matches[1].Trim() }
  $kw=''; if($html -match '(?is)<meta[^>]*name\s*=\s*["'']?keywords["'']?[^>]*content\s*=\s*["'']([^"'']*)["'']'){ $kw=$Matches[1].Trim() }
  $order++
  $id=1000+$order
  $postname= if($slug -eq 'index'){'home'}else{$slug}
  $item=@"
	<item>
		<title>$(Xesc $title)</title>
		<link>https://www.teachingbeauty.jp/$slug.html</link>
		<pubDate>Wed, 01 Jan 2020 00:00:00 +0000</pubDate>
		<dc:creator><![CDATA[admin]]></dc:creator>
		<guid isPermaLink="false">https://www.teachingbeauty.jp/?page_id=$id</guid>
		<description></description>
		<content:encoded>$(CDwrap $body)</content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<wp:post_id>$id</wp:post_id>
		<wp:post_date><![CDATA[2020-01-01 09:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2020-01-01 00:00:00]]></wp:post_date_gmt>
		<wp:comment_status><![CDATA[closed]]></wp:comment_status>
		<wp:ping_status><![CDATA[closed]]></wp:ping_status>
		<wp:post_name><![CDATA[$postname]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>$order</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:post_password><![CDATA[]]></wp:post_password>
		<wp:is_sticky>0</wp:is_sticky>
		<wp:postmeta><wp:meta_key><![CDATA[_tb_description]]></wp:meta_key><wp:meta_value>$(CDwrap $desc)</wp:meta_value></wp:postmeta>
		<wp:postmeta><wp:meta_key><![CDATA[_tb_keywords]]></wp:meta_key><wp:meta_value>$(CDwrap $kw)</wp:meta_value></wp:postmeta>
		<wp:postmeta><wp:meta_key><![CDATA[_tb_bodyattr]]></wp:meta_key><wp:meta_value>$(CDwrap $bodyattr)</wp:meta_value></wp:postmeta>
		<wp:postmeta><wp:meta_key><![CDATA[_wp_page_template]]></wp:meta_key><wp:meta_value><![CDATA[default]]></wp:meta_value></wp:postmeta>
	</item>
"@
  $items.Add($item)
  Write-Output ("OK  {0,-14} body={1,6} chars  title='{2}'" -f $slug,$body.Length,$title.Substring(0,[math]::Min(26,$title.Length)))
}
$header=@"
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<title>Teaching Beauty</title>
	<link>https://www.teachingbeauty.jp</link>
	<description>桜新町の鍼灸整骨院</description>
	<pubDate>Wed, 01 Jan 2020 00:00:00 +0000</pubDate>
	<language>ja</language>
	<wp:wxr_version>1.2</wp:wxr_version>
	<wp:base_site_url>https://www.teachingbeauty.jp</wp:base_site_url>
	<wp:base_blog_url>https://www.teachingbeauty.jp</wp:base_blog_url>
	<wp:author><wp:author_id>1</wp:author_id><wp:author_login><![CDATA[admin]]></wp:author_login><wp:author_email><![CDATA[]]></wp:author_email><wp:author_display_name><![CDATA[admin]]></wp:author_display_name></wp:author>
	<generator>manual-fullbody</generator>
"@
$footer="</channel>`n</rss>"
[System.IO.File]::WriteAllText($out,$header + ($items -join "`n") + "`n" + $footer,(New-Object System.Text.UTF8Encoding($false)))
# validate
$xml=New-Object System.Xml.XmlDocument; $xml.Load($out)
Write-Output ("---- WXR OK: $($xml.SelectNodes('//item').Count) pages, {0:N0} bytes ----" -f (Get-Item $out).Length)