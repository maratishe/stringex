<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
//clhelp( '');
//htg( clget( ''));


require( 'Stringex.php');
$S = new Stringex( 'index.stringex');

echo "\n\n";
echo "test: author=m.zhanikeev\n"; $q = tth( 'authors=m.zhanikeev');
$docids = $S->find( $q, true);	// only ids
echo 'docids: ' . jsonraw( $docids) . "\n";
$docs = $S->get( $docids, $q);
echo "docs:\n";
foreach ( $docs as $doc) echo '   ' . jsonraw( $doc) . "\n";


?>