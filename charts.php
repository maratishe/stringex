<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
//clhelp( '');
//htg( clget( ''));


$FS = 16; $BS = 4.5; 
class MyChartFactory extends ChartFactory { public function make( $C, $margins) { return new ChartLP( $C->setup, $C->plot, $margins);}}
$S = new ChartSetupStyle(); $S->style = 'D'; $S->lw = 0.1; $S->draw = '#000'; $S->fill = null;
$SR = clone $S; $SR->lw = 1.0; $SR->draw = '#f30';
$SB = clone $SR; $SB->draw = '#06f';





$B = tth( 'lucene=cross,stringex=circle');
list( $C, $CS, $CST) = chartlayout( new MyChartFactory(), 'P', '1x2', 30, '0.1:0.1:0.15:0.15');
$C2 = lshift( $CS); $x = array(); $y = array();
foreach ( flget( '.', 'raw', '', 'json') as $file) {
	$L = ttl( $file, '.'); lshift( $L); $m = lshift( $L);
	$H = jsonload( $file);  
	for ( $i = 0; $i < count( $H); $i++) $H[ $i] = tth( $H[ $i]);
	$size = lpop( hltl( $H, 'size')); lpop( $H);
	htouch( $x, $m); htouch( $y, $m); 
	lpush( $x[ $m], 0.001 * $size); lpush( $y[ $m], log10( msum( hltl( $H, 'filesin')) - msum( hltl( $H, 'filesout'))));
}
foreach ( $x as $m => $v) $C2->train( $x[ $m], $y[ $m]);
$C2->autoticks( null, null, 10, 8, 'xmax=2600');
$C2->frame( 'Final size of index (kb)', 'log( peak no. of files)');
foreach ( $x as $m => $v) chartscatter( $C2, $x[ $m], $y[ $m], $B[ $m], 6, $S);
$CL = new ChartLegendOR( $C2);
$CL->add( 'cross', 6, 0.1, 'Lucene', $S);
$CL->add( 'circle', 6, 0.1, 'Stringex', $S);
$CL->draw();
$C->dump( 'charts.2.pdf');



list( $C, $CS, $CST) = chartlayout( new MyChartFactory(), 'P', '1x2', 30, '0.1:0.1:0.15:0.15');

// raw.lucene.json    vs    raw.stringex.08.26.json (keyHashMask=8,docHashMask=26)
$C2 = lshift( $CS); $e = echoeinit();
$H1 = jsonload( 'raw.lucene.json'); $H2 = jsonload( 'raw.stringex.04.24.json');
$x1 = array(); $x2 = array(); $y1 = array(); $y2 = array();
foreach ( ttl( '1,2') as $k) {
	$k2 = "H$k"; $R =& $$k2; $tag = $k2; 
	$k2 = "x$k"; $X =& $$k2; lpush( $X, 0);
	$k2 = "y$k"; $Y =& $$k2; lpush( $Y, 0);
	foreach ( $R as $pos => $h) {
		echoe( $e, "$tag  $pos/" . count( $R));
		extract( tth( $h)); // bytesin, filesin, filesout, size
		lpush( $X, llast( $X) + $bytesin);
		lpush( $Y, llast( $Y) - $filesout + $filesin);
	}
	$X2 = array(); for ( $i = 0; $i < count( $X) - 1; $i += 100) { $L = array(); for ( $ii = $i; $ii < $i + 100 && $ii < count( $X) - 1; $ii++) lpush( $L, $X[ $ii]); lpush( $X2, 0.001 * mavg( $L)); }
	$Y2 = array(); for ( $i = 0; $i < count( $Y) - 1; $i += 100) { $L = array(); for ( $ii = $i; $ii < $i + 100 && $ii < count( $Y) - 1; $ii++) lpush( $L, $Y[ $ii]); lpush( $Y2, mavg( $L)); }
	lpush( $X2, 0.001 * llast( $X)); lpush( $Y2, llast( $Y));
	$X = $X2; $Y = $Y2;
	unset( $R); unset( $X); unset( $Y);
}
echo " OK\n";
$C2->train( $x1, $y1); $C2->train( $x2, $y2);
$C2->autoticks( null, null, 10, 8, 'xmin=0,ymin=0');
$C2->frame( 'Transmitted traffic volume (kb)', 'File count');
chartline( $C2, $x1, $y1, $SB);
chartline( $C2, $x2, $y2, $SR);
$CL = new ChartLegendOR( $C2);
$CL->add( null, 4, 0.5, 'Stringex (keyHashMask=4; docHashMask=24)', $SR);
$CL->add( null, 4, 0.5, 'Lucene', $SB);
$CL->draw( true);


$C2 = lshift( $CS); $e = echoeinit();
$x = array(); $y = array();
foreach ( flget( '.', 'raw.stringex', '', 'json') as $file) {
	$H = jsonload( $file); $x2 = array( 0); $y2 = array( 0);
	foreach ( $H as $pos => $h) {
		echoe( $e, "$file $pos/" . count( $H));
		extract( tth( $h)); 	// bytesin, bytesout, filesin, filesout, size
		lpush( $x2, llast( $x2) + $bytesin); lpush( $y2, llast( $y2) + $filesin);
	}
	$x3 = array(); $y3 = array();
	for ( $i = 0; $i < count( $x2) - 1; $i += 100) {
		$x4 = array(); $y4 = array();
		for ( $ii = $i; $ii < $i + 100 && $ii < count( $x2); $ii++) { lpush( $x4, $x2[ $ii]); lpush( $y4, $y2[ $ii]); }
		lpush( $x3, 0.001 * mavg( $x4));
		lpush( $y3, log10( round( mavg( $y4))));
	}
	lpush( $x3, 0.001 * llast( $x2)); lpush( $y3, log10( round( llast( $y2)))); 
	lpush( $x, $x3); lpush( $y, $y3);
}
echo " OK\n";
foreach ( $x as $pos => $h) $C2->train( $x[ $pos], $y[ $pos]);
$C2->autoticks( null, null, 10, 8);
$C2->frame( 'Transmitted traffic volume (kb)', 'log( no. of files)');
foreach ( $x as $i => $h) chartline( $C2, $x[ $i], $y[ $i], $S);

$C->dump( 'charts.1.pdf');



?>