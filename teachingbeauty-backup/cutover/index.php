<?php
/**
 * サイトルート用 index.php
 *
 * WordPress 本体は /wp/ に置いたまま、サイトの表示アドレスをルート（https://www.teachingbeauty.jp/）にする
 * （WordPress 公式の「WordPress を専用ディレクトリに配置する」構成）。
 * このファイルをサイトルートに置き、WordPress の「サイトアドレス (URL)」をルートにすると、
 * /concept.html などの全ページがルートで表示される。管理画面は /wp/wp-admin/ のまま。
 */

define( 'WP_USE_THEMES', true );
require __DIR__ . '/wp/wp-blog-header.php';
