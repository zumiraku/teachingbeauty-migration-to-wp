<?php
/**
 * サイトルート用の index.php。
 * WordPress 本体は /wp/ に置いたまま、表示アドレスだけルートにする（専用ディレクトリ構成）。
 * これをルートに置いて「サイトアドレス」をルートにすれば全ページがルートで出る。管理画面は /wp/wp-admin/ のまま。
 */

define( 'WP_USE_THEMES', true );
require __DIR__ . '/wp/wp-blog-header.php';
