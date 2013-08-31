<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
//clhelp( 'PURPOSE: to index some papers from dataset.bz64jsonl');
htg( clgetq( 'limit,wdir,report'));

$LUCENEDIR = '.';
`rm -Rf $wdir`; `mkdir $wdir`;
require_once( 'lucene.php'); 
list( $L, $err) = liopen( "$LUCENEDIR/$wdir"); if ( ! $L) die( " Failed to create Lucene index: err[$err]\n");


echo "\n\n"; $e = echoeinit(); 
$in = finopen( 'dataset.bz64jsonl'); $watch = new FilesystemWatch( $wdir); 
while ( ! findone( $in) && $limit-- > 0) {
	list( $h, $p) = finread( $in); if ( ! $h) continue; 
	$D = new Zend_Search_Lucene_Document();
	foreach ( $h as $k => $v) {
		if ( is_array( $v)) $v = ltt( $v, ' ');
		$D->addField( Zend_Search_Lucene_FIeld::Text( $k, $v, 'UTF-8'));
		@$L->addDocument( $D);
		$L->commit();
	}
	echoe( $e, "$p($limit) > " . htt( $watch->report()) . '   cont(' . $watch->count() . ') size(' . $watch->size() . ')');
}
finclose( $in); echo "\n";
echo "committing... "; $L->optimize(); echo "  OK\n";
$watch->report();
jsondump( $watch->history(), $report);	// dump the report
`rm -Rf $wdir`;

?>