<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( 'PURPOSE: to run simulations');
//htg( clget( ''));


`rm -Rf raw.*`;


// first, Lucene run
echo "\n\n"; $e = echoeinit();
echo "LUCENE run\n"; `rm -Rf report.json`;
$c = "php make.lucene.php 5000 index report.json"; echo "c: $c\n";
echopipee( $c); if ( ! is_file( 'report.json')) die( " ERROR! No report.json found!\n");
`mv report.json raw.lucene.json`; 
echo "OK\n";

// now, a bunch of Stringex runs
echo "\n\n"; $e = echoeinit();
echo "STRINGEX runs\n";
for ( $keyHashMask = 4; $keyHashMask <= 16; $keyHashMask += 4) {
	for ( $docHashMask = 24; $docHashMask <= 32; $docHashMask += 2) {
		$report = sprintf( 'raw.stringex.%02d.%02d.json', $keyHashMask, $docHashMask);
		$c = "php make.stringex.php 5000 index $report keyHashBits=16,keyHashMask=$keyHashMask,docHashBits=32,docHashMask=$docHashMask";
		echo "$c\n";
		echopipee( $c);
		if ( ! is_file( $report)) die( " ERROR! Could not find report file [$report]\n");
	}
	
}


?>