<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( 'PURPOSE: to index some papers from dataset.bz64jsonl');
htg( clgetq( 'limit,wdir,report,setup'));
$setup = tth( $setup); foreach ( $setup as $k => $v) $setup[ $k] = round( $v);


require_once( 'Stringex.php');
echo "\n\n"; $e = echoeinit(); 
//`rm -Rf $wdir/*`; // */ 
`rm -Rf $wdir`; `mkdir $wdir`; 
$W = new FilesystemWatch( $wdir);
$Ss = new StringexSetup( $setup);
$Ss->wdir = $wdir;
$Ss->keys = ttl( 'tags,authors,keywords');
$Ss->verbose = false;
$S = new Stringex( $Ss);
$in = finopen( 'dataset.bz64jsonl');
while ( ! findone( $in) && $limit-- > 0) {
	list( $h, $p) = finread( $in); if ( ! $h) continue; 
	$S->add( $h, true);
	echoe( $e, "$p($limit)  > " . htt( $W->report()) . '   count(' . $W->count() . ')  size(' . $W->size() . ')');
}
finclose( $in); echo "\n";
echo "committing... "; $S->commit(); echo "  OK\n";
$W->report(); 	// get the final state
jsondump( $W->history(), $report);
`rm -Rf $wdir`;

?>