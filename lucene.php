<?php
// functions for lucene-based index creation and its search
iconv_set_encoding( "input_encoding", "UTF-8");
iconv_set_encoding( "internal_encoding", "UTF-8");
iconv_set_encoding( "output_encoding", "UTF-8");
mb_internal_encoding( "UTF-8");
set_include_path( '.');
require_once( 'Zend/Search/Lucene.php');
Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('UTF-8');
// analyzers
//Zend_Search_Lucene_Analysis_Analyzer::setDefault( new Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum_CaseInsensitive());
//Zend_Search_Lucene_Analysis_Analyzer::setDefault( new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8());
//Zend_Search_Lucene_Analysis_Analyzer::setDefault( new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num());
require_once( "$ABDIR/lib/Utf8MbcsUnigram.php");
Zend_Search_Lucene_Analysis_Analyzer::setDefault( new Twk_Search_Lucene_Analysis_Analyzer_Common_Utf8MbcsUnigram());


// 20130428: top-level interface
function lfind( $query, $dirs = null, $exitOnFirstFind = false, $e = null) { // returns [ hash list | hash | null, error]
	$D = array();
	if ( is_string( $dirs)) $dirs = ttl( $dirs);
	if ( ! $dirs) $dirs = lidirs();
	foreach ( $dirs as $dir) {
		if ( $e) echoe( $e, " lucene.query($dir)");
		list( $hits, $err) = lq( $dir, $query); if ( ! $hits) continue;
		if ( $err) return array( null, $err); 
		foreach ( $hits as $hit) { list( $h, $err) = lqhit2h( $hit); if ( ! $err && $h) { $h[ 'lucenedir'] = $dir; lpush( $D, $h); }}
		if ( count( $D) && $exitOnFirstFind) break;
	}
	if ( $e) echoe( $e, '');
	return array( ( $exitOnFirstFind && count( $D)) ? $D[ 0] : ( count( $D) ? $D : null), null); // list | hash | null
}
function lupdate( $dir, $h, $donotdofiles = true, $e = null) { // purges, then creates no entries -- returns new id
	if ( $e) echoe( $e, " lucene.purge($dir." . $h[ 'id'] . ")");
	lipurge( $dir, $h[ 'id'], true, false);
	list( $info, $fields) = @leh2i( $h, $h[ 'iid'], $donotdofiles); 
	unset( $h[ 'lucenedir']); leup( $h);
	if ( $e) echoe( $e, " lucene.new($dir)");
	$id = linew( $dir, $info, $fields, true, false);
	if ( $e) echoe( $e, '');
	return $id;
}


// definitions for LUCENE field unfolds(type: { addkeys: types}) and field maps (internal: GUI)
// also manipulations across types of fields and field extensions
$LFNTYPES = ttl( 'file,string,text,keyword,binary'); // n: native types
$LFITYPES = ttl( 'text,text,text,keyword,unindexed'); // i: index type (internal to lucene)
$LFGTYPES = ttl( 'file,input,text,number,binary'); // g: GUI type, handled in web applications
$LFCLEANS = ttl( 'tags,title,authors,howpublished,keywords');
$LIDLIST = ttl( 'one,two,three,four,five,six,seven,eight,nine,ten');	// which directories to use for index partitions
function lfmapa2b( $one, $two) { $h = array(); for ( $i = 0; $i < count( $one); $i++) $h[ $one[ $i]] = $two[ $i]; return $h; }
function lfmap( $def) { // def should be 'a,b' where a,b from (n,i,g), a cannot be 'i' 
	global $LFNTYPES, $LFITYPES, $LFGTYPES; 
	extract( lth( ttl( $def), ttl( 'a,b'))); 
	$a = strtoupper( $a); $b = strtoupper( $b); 
	$ak = 'LF' . $a . 'TYPES'; $bk = 'LF' . $b . 'TYPES'; 
	return lfmapa2b( $$ak, $$bk); 
}
// when you change unfolds, change lfd**** functions as well -- they add additional keys
$LFUNFOLD = ttl( 'names=text,count=keyword,body=text,bodylength=keyword,type=text,size=keyword:length=keyword:length=keyword::count=keyword', ':', '', false);
function lfunfolds() { // returns map { LFNTYPE: { key: LFITYPES}, ...}
	global $LFNTYPES, $LFITYPES, $LFUNFOLD;
	$h = array(); 
	for ( $i = 0; $i < count( $LFNTYPES); $i++) {
		$k = $LFNTYPES[ $i]; $h[ $k] = array();
		if ( $LFUNFOLD[ $i]) $h[ $k] = tth( $LFUNFOLD[ $i]);
	}
	return $h;
}
function lfunfoldone( $name, $ntype, $def = 'n,i') { // return { key: itype}  for all keys, that is nfield + unfolded (extended) keys
	$h = array(); $map = lfmap( $def);
	$h[ $name] = $map[ $ntype];
	$map = lfunfolds();
	foreach ( $map[ $ntype] as $k => $v) $h[ $name . $k] = $v;	// unfolded/extended fields
	return $h;
}
function lfunfold( $nfields) { // converts { name: ntype} into { name: itype} extended list 
	$h = array();
	foreach ( $nfields as $k => $ntype) { $h2 = lfunfoldone( $k, $ntype); $h = hm( $h, $h2); }
	return $h;
}

// main index openers/closers, (dir) is absolute path
function liopen( $dir) {	// return [ $L | null, error] 
	$L = null;
	try { 
		if ( is_dir( $dir) && count( flget( $dir))) 
			$L = new Zend_Search_Lucene( $dir, false);
		else $L = new Zend_Search_Lucene( $dir, true);
	}		
	catch ( Zend_Search_Lucene_Exception $e) {
		return array( null, $e->getMessage());
	}
	return array( $L, '');
}
function linew( $cdir, $info, $types, $commit = true, $optimize = false) {	// open and close index -- returns newly created document id
	global $LUCENEDIR, $LFCLEANS; $cleans = hvak( $LFCLEANS, true, true);
	list( $L, $msg) = liopen( "$LUCENEDIR/$cdir");
	if ( ! $L) die( "  ERROR! linew() Could not open index at [$LUCENEDIR/$cdir]!\n");
	ldfixbinary( $info, $types);	// fix screwed up binary fields
	$D = new Zend_Search_Lucene_Document();
	//echo "\n\n\n\n";
	foreach ( $info as $k => $v) {
		//if ( $v === '') continue;
		if ( ! isset( $types[ $k])) die( " ERROR! type[$k] is not found in types\n");
		$type = $types[ $k];
		//echo "$k [$type] $v\n";
		if ( isset( $cleans[ $k])) $v = ldclean( $v);
		//echo "$k [$type] " . mb_substr( $v, 0, 100) . "\n"; 
		if ( $type == 'keyword') ldkeyword( $D, $k, $v);	
		if ( $type == 'text') ldtext( $D, $k, $v);
		if ( $type == 'unindexed') ldunindexed( $D, $k, $v);
	}
	@$L->addDocument( $D); 
	return liclose( $L, $commit, $optimize) - 1;
}
function lipurge( $cdir, $id, $commit = true, $optimize = false) {	// [ true|false, msg], will open and close index
	global $LUCENEDIR; $id = ( int)$id;
	list( $L, $err) = liopen( "$LUCENEDIR/$cdir");
	$L->delete( $id);
	liclose( $L, $commit, $optimize);
	return array( true, 'ok');
}
function liclose( $L, $commit = true, $optimize = false) { 
	if ( $commit) $L->commit(); 
	if ( $optimize) $L->optimize(); 
	$docs = $L->numDocs();
	$count = $L->count();
	unset( $L);
	return $count;
}
function lqopen( $dir) { // return $L, no arrays
	try { $L = Zend_Search_Lucene::open( $dir); }
	catch ( Zend_Search_Lucene_Exception $e) { return null; }
	return $L;
}
function lqclose( $L, $commit = true, $optimize = true) { 
	if ( $commit) $L->commit(); 
	if ( $optimize) $L->optimize(); 
	$docs = $L->numDocs();
	unset( $L);
	return $docs; 
}

// information about index and fields
function lidirs() { // return list of dirs | empty list if error
	global $LUCENEDIR, $LIDLIST;
	$L = array(); foreach ( $LIDLIST as $dir) if ( is_dir( "$LUCENEDIR/$dir")) lpush( $L, $dir);
	return $L;
}
function licount( $cdir, $L = null) { // returns count of documents for that cdir
	global $LUCENEDIR;
	if ( ! $L) list( $L2, $msg) = liopen( "$LUCENEDIR/$cdir");
	else $L2 = $L;
	if ( ! $L2) return null;
	$count = $L2->count();
	if ( ! $L) liclose( $L2, false, false);
	return $count;
}
function liget() { // returns full info hash from info.json in LUCENEDIR, puts iid key in front
	global $LUCENEDIR;
	$h = @jsonload( "$LUCENEDIR/info.json", true, true); if ( ! $h) return null; // try to open with locking
	$h[ 'fields'] = hm( array( 'iid' => 'keyword'), tth( $h[ 'fields']));
	return $h;
}
function liset( &$oh, $donotdosizes = true) { // oh: { iid, fields: { name: type, ...}}
 	global $LUCENEDIR;
	$oh[ 'fields'] = htt( $oh[ 'fields']);
 	if ( ! $donotdosizes) lisizes( $oh);
	jsondump( $oh, "$LUCENEDIR/info.json", true, true); // write with locking
}
function lisizes( &$oh) { 	// sets sizes of all current content directories
	global $LUCENEDIR;
	$dirs = lidirs();
	htouch( $oh, 'sizes');
	foreach ( $dirs as $dir) $oh[ 'sizes'][ $dir] = round( 0.001 * procdu( "$LUCENEDIR/$dir"));	// Mb
}
function lfget( $unfold = false, $guinames = false) { 	// when unfold is true: will add additional keys depending on field types
	extract( liget()); 	// iid, fields
	$fields2 = array(); $map = lfmap( $guinames ? 'n,g' : 'n,i');
	$fields2[ 'iid'] = $map[ 'keyword'];		// add iid key no matter what
	foreach ( $fields as $k => $ntype) {
		$fields3 = $unfold ? lfunfoldone( $k, $ntype, $guinames ? 'n,g' : 'n,i') : array( $k => $map[ $ntype]);
		$fields2 = hm( $fields2, $fields3);
	}
	return $fields2;
}

// doc fields
function ldkeyword( $D, $k, $v) { $D->addField( Zend_Search_Lucene_Field::Keyword( $k, $v, 'UTF-8'));}
function ldtext( $D, $k, $v) { $D->addField( Zend_Search_Lucene_Field::Text( $k, $v, 'UTF-8'));}
function ldunindexed( $D, $k, $v) { $D->addField( Zend_Search_Lucene_Field::UnIndexed( $k, $v, 'UTF-8'));}

// index manipulations, (dir,cdir) always a directory in LUCENEDIR
// search, $hits is array of objects where files are object variables
function lq( $dir, $query, $limit = 300, $L = null, $donotclose = false) { // array( $hits | null, [error])
	global $LUCENEDIR;
	$query = mb_strtolower( $query);
	Zend_Search_Lucene::setResultSetLimit( $limit);
	$closewhendone = false; if ( ! $L) $closewhendone = true;
	if ( ! $L) $L = lqopen( "$LUCENEDIR/$dir");
	if ( ! $L) return array( null, "did not find Lucene index in [$dir]");
	$hits = null;
	try { $hits = $L->find( $query);}
	catch ( Zend_Search_Lucene_Exception $e) { return array( null, $e->getMessage()); }
	if ( $closewhendone && ! $donotclose) { lqclose( $L, false, false); $L = null; }	// no commits
	return array( $hits, $L ? $L : null);
}
function lqhit2h( $hit) {	// returns array( hash | null, msg | error) 
	$fields = lfget( true);	// unfolded itypes
	$h = array(); $h[ 'id'] = $hit->id;
	foreach ( $fields as $k => $t) {
		try { $h[ $k] = @$hit->__get( $k); }
		catch ( Zend_Search_Lucene_Exception $e) { $h[ $k] = ''; }
	}
	return array( $h, null);
}
function lqdoc2h( $doc) { // NOTE: no ID for delete, return array( hash | null, msg | error) 
	$fields = lfget( true);	// unfolded itypes
	$h = array();
	foreach ( $fields as $k => $t) {
		unset( $field);
		try { $field = $doc->getField( $k); }
		catch ( Zend_Search_Lucene_Exception $e) { $h[ $k] = ''; }
		$h[ $k] = ( isset( $field) && is_object( $field) && isset( $field->value)) ? $field->value : ''; 
	}
	return array( $h, null);
}


// formats and conversion mainly used to connect web, backend lucene and backup/restore/fix utilities
// root: luceneinfo, h: hash, o: outer, i: inner, b: backup
function leh2o( $info) { // returns new hash in outer format: all non-empty info is base64-ed
	$fields = lfget( true);	// unfolded itypes
	$info2 = $info;
	foreach ( $fields as $k => $t) {
		if ( ! isset( $info2[ $k])) continue;	// ignore missing keys ( edit mode)
		if ( ! strlen( $info2[ $k])) continue;	// nothing to do
		$info2[ $k] = base64_encode( $info2[ $k]);
	}
	return $info2;
}
function leo2h( $info) { // will work on any hash, not just outer
	foreach ( $info as $k => $v) if ( strlen( $v)) $info[ $k] = base64_decode( $info[ $k]);
	return $info;
}
function leh2i( $info, $oiid = null, $donotdofiles = false, $e = null) { // [ info, fields], allows for fields outside of inner
	global $LUCENEDIR;
	$iinfo = liget(); extract( $iinfo); // iid, fields
	if ( $oiid) $info[ 'iid'] = $oiid;	// iid should be replaced
	if ( ! isset( $info[ 'iid']) ||  ! $info[ 'iid']) { $info[ 'iid'] = $iid; $iinfo[ 'iid']++; liset( $iinfo); } // increment iid for future numbers
	$info2 = array(); $fields2 = array(); 
	$info2[ 'iid'] = $info[ 'iid']; $fields2[ 'iid'] = 'keyword';
	foreach ( $fields as $k => $ntype) {
		$f = 'lfd' . $ntype; if ( $e) echoe( $e, " leh2i($k=$ntype)"); 
		list( $info3, $fields3) = $f( $k, $info, $donotdofiles, $e);
		//foreach ( $info3 as $k2 => $v2) echo "$k2 $v2\n";
		$info2 = hm( $info2, $info3); $fields2 = hm( $fields2, $fields3);
	}
	return array( $info2, $fields2, '');
}
function leo2i( $info, $oiid = null, $donotdofiles = false) { return leh2i( leo2h( $info), $oiid, $donotdofiles); }
function lei2h( $info) { return ldo2h( $info); } // will simply base64() non-empty fields
function lei2b( $info) { // will work with any hash, does not limit keys
	foreach ( $info as $k => $v) if ( strlen( $v)) $info[ $k] = base64_encode( $info[ $k]);
	return $info;
}
function leb2i( $info) { return leo2h( $info); }
function leup( &$info, $h = null) { // updates internal entry's log binary info, updated by reference
	$L = ttl( $info[ 'log'], ' '); 
	if ( ! $h) $h = tth( "lastupdate=" . tsystemstamp());	// default log -- lastupdate 
	lpush( $L, h2json( $h, true));
	$info[ 'log'] = ltt( $L, ' ');
	$info[ 'logcount'] = count( $L);
	return $info;
}


// each task is spawn using procat and then waited on
function ltask( $cdir, $type, $info = null, $fields = null, $e = null, $donotwait = false, $donotcheckforerrors = true) {	// returns [ status | prefix, err | ''] -- general task
	global $LUCENEDIR, $LUCENECODEDIR; 
	if ( ! is_dir( "$LUCENEDIR/temp")) mkdir( "$LUCENEDIR/temp");
	$h = array(); $prefix = "$LUCENEDIR/temp/" . sprintf( "%s.%s.%d.%d", $cdir, $type, ( int)tsystem(), mr( 10));
	if ( $info) $h[ 'info'] = $info; if ( $fields) $h[ 'fields'] = $fields;
	if ( $info) jsondump( $h, "$prefix.json", true, false);	// force not locking
	$c = "/usr/local/php/bin/php $LUCENECODEDIR/lucene.$type.php $LUCENEDIR $cdir $prefix.json"; 
	jsondbg( $c);
	procat( "$c > $prefix.log 2>&1 3>&1");
	if ( $donotwait) return array( $prefix, '');	// return immediately, will be monitored separately
	$limit = 100; while ( $limit-- && ! is_file( "$prefix.log")) usleep( 50000);	// wait for the process to start
	if ( ! is_file( "$prefix.log")) { `rm -Rf $prefix.*`; if ( $e) echoe( $e, ''); return array( false, 'failed to start process, maybe ATD service not running?'); }
	$before = tsystem(); while ( tsystem() - $before < 15000 && procpid( $prefix)) {
		if ( $e) echoe( $e, '   ltask(' . tshinterval( tsystem(), $before) . ')');
		usleep( 1000 * mt_rand( 100, 500));
	}
	if ( procpid( $prefix)) { prockill( procpid( $prefix)); `rm -Rf $prefix*`; if ( $e) echoe( $e, ''); return array( false, 'Process still running after 15000 timeout, quit on it...'); }
	$bad = false; 
	if ( ! $donotcheckforerrors) foreach ( file( "$prefix.log") as $line) {
		$line = trim( $line); if ( ! $line) continue;
		$line = strtolower( $line);
		$bads = ttl( 'warning,notice,error'); foreach ( $bads as $k) if ( strpos( $k, $line) !== false) { 
			if ( ! $bad) $bad = array();
			lpush( $bad, trim( $line));
			break;	// only one hit per line
		}
		
	}
	if ( $bad) { `rm -Rf $prefix*`; return array( false, $bad); } // bad contains warning/error/notice lines
	//if ( $info) `rm -Rf $prefix.json`;	// remove file if one was created previously
	//`rm -Rf $prefix.log`; `rm -Rf $prefix.bz64jsonl`; // remove all possible temp files
	//`rm -Rf $prefix.*`; // all kinds of other files
	if ( $e) echoe( $e, '');
	return array( $prefix, 'ok');
}
function ltasksearchgetids( $prefix, $e = null) { // returns [ 'type.id', ...] having parsed the results
	$L = ttl( $prefix, '/', '', false); $prefix = lpop( $L); $dir = ltt( $L, '/');
	$FL = flget( $dir, $prefix, '', 'bz64jsonl'); $h = array();
	foreach ( $FL as $file) { 
		$L = ttl( $file, '.'); lpop( $L); $type = llast( $L); 
		$in = finopen( "$dir/$file");
		while ( ! findone( $in)) {
			list( $h2, $progress) = finread( $in); if ( ! $h2) continue;
			$id = base64_decode( $h2[ 'id']); lpush( $h, "$type.$id");
			if ( $e) echoe( $e, " $type($progress) $id");
		}
		
	}
	return $h;
}

// data field and data (file) creators
function lfdfile( $name, $info2 = array(), $optional = null, $e = null) { // returns [ info, fields]
	$info = array(); $fields = lfunfoldone( $name, 'file', 'n,i');
	foreach ( $fields as $k => $itype) $info[ $k] = ( isset( $info2[ $k]) && $info2[ $k]) ? $info2[ $k] : '';
	$k = $name; $v = $info[ $name]; 
	$vs = array( '', '', 0, '', 0, '', 0); $ks = hk( $info);
	if ( ! $v) { for ( $i = 0; $i < count( $vs); $i++) $info[ $ks[ $i]] = $vs[ $i]; return array( $info, $fields); }
	$L = ttl( $v, ' '); 
	$L2 = array(); foreach ( $L as $v) lpush( $L2, lpop( ttl( $v, '/'))); $info[ $k . 'names'] = ltt( $L2, ' ');
	$info[ $k . 'count'] = count( $L);
	if ( ! $optional) $info[ $k . 'body'] = ldreadfiles( $L, $e); // donotreadfiles = FALSE
	$info[ $k . 'bodylength'] = mb_strlen( $info[ $k . 'body']);
	$L2 = array(); foreach ( $L as $v) lpush( $L2, lpop( ttl( $v, '.'))); $info[ $k . 'type'] = ltt( $L2, ' ');
	$size = 0; foreach ( $L as $file) $size += @filesize( $file); $info[ $k . 'size'] = $size;
	return array( $info, $fields, '');
}
function lfdtext( $name, $info2 = array(), $optional = null, $e = null) { // returns [ info, fields]
	$info = array(); $fields = lfunfoldone( $name, 'text', 'n,i');
	foreach ( $fields as $k => $itype) $info[ $k] = ( isset( $info2[ $k]) && $info2[ $k]) ? $info2[ $k] : '';
	$k = $name; $v = $info[ $name];
	$info[ $k] = utf32clean( $v);
	$info[ $k . 'length'] = mb_strlen( $info[ $k]);
	return array( $info, $fields, '');
}
function lfdstring( $name, $info2 = array(), $optional = null, $e = null) { return lfdtext( $name, $info2, $optional); }
function lfdkeyword( $name, $info2 = array(), $optional = null, $e = null) { // returns [ info, fields]
	$info = array(); $fields = lfunfoldone( $name, 'keyword', 'n,i');
	foreach ( $fields as $k => $itype) $info[ $k] = ( isset( $info2[ $k]) && $info2[ $k]) ? $info2[ $k] : '';
	return array( $info, $fields, '');
}
function lfdbinary( $name, $info2 = array(), $optional = null, $e = null) { // returns [ info, fields]
	$info = array(); $fields = lfunfoldone( $name, 'binary', 'n,i');
	foreach ( $fields as $k => $itype) $info[ $k] = ( isset( $info2[ $k]) && $info2[ $k]) ? $info2[ $k] : '';
	$k = $name; $info[ $k] = trim( $info[ $k]); $v = $info[ $name];
	$info[ $k . 'count'] = $v ? count( ttl( $v, ' ')) : 0;
	return array( $info, $fields, '');
}

// file specific functions
function ldreadfiles( $paths, $e = null) { // returns filebody
	if ( ! $paths) return ''; if ( is_array( $paths)) $paths = ltt( $paths, ' ');
	$L = ttl( $paths, ' '); if ( ! count( $L)) return '';
	$body = ''; 
	foreach ( $L as $path) {  list( $body2, $err) = ldreadfile( $path); if ( $body2) $body .= '   ' . $body2; }
	if ( ! $body) return '';
	return utf32clean( $body, $e);
}
function ldreadfile( $path, $noiconv = false) { // returns [ $body | nothing, error | nothing]
	global $LUCENEDIR;
	if ( ! is_dir( "$LUCENEDIR/temp")) mkdir( "$LUCENEDIR/temp");
	$temp = lpop( ttl( $path, '/')) . '.' . tsystem() . '.txt'; $tempath = "$LUCENEDIR/temp/$temp";
	$ext = strtolower( lpop( ttl( $path, '.')));
	$body = '';
	// call various ext2txt processors and get the body of text
	if ( $ext == 'pdf') {
		$XPDF = '/usr/local/xpdf/bin';
		$enc = 'UTF-8';
		$c = "$XPDF/pdftotext -layout -nopgbrk -eol unix -enc " . strdblquote( $enc) . ' ' . strdblquote( $path) . ' ' . strdblquote( $tempath) . ' > /dev/null 2>/dev/null 3>/dev/null';
		@unlink( $tempath); @system( $c);
		$body = ''; $in = @fopen( $tempath, 'r'); while ( $in && ! feof( $in) && strlen( $body) < 1000000) $body .= fgets( $in); @fclose( $in);
		@unlink( $tempath);
	}
	if ( $ext == 'tex') { 
		$detex = '/usr/local/texlive/2010/bin/i386-linux/detex';
		$c = "$detex " . strdblquote( $path) . " > " . strdblquote( $tempath) . " 2> /dev/null 3>/dev/null";
		@unlink( $tempath); @system( $c);
		$body = ''; $in = @fopen( $tempath, 'r');  while ( $in && ! feof( $in)) $body .= fgets( $in); @fclose( $in);
		@unlink( $tempath);
	}
	if ( $ext == 'txt') { 
		$body = ''; $in = @fopen( $path, 'r'); while ( $in && ! feof( $in)) $body .= fgets( $in); @fclose( $in);
	}
	if ( $ext == 'html') { 
		$body = ''; $in = @fopen( $path, 'r'); while ( $in && ! feof( $in)) $body .= fgets( $in); @fclose( $in);
		$body = strip_tags( $body);	// php function
	}
	return array( $body, '');
}
function ldclean( $v) {
	$bads = ':;/'. "'" . '"' . '{}[]'; for ( $i = 0; $i < strlen( $bads); $i++) $v = str_replace( substr( $bads, $i, 1), ' ', $v);
	for ( $i = 0; $i < 10; $i++) $v = str_replace( '  ', ' ', $v);
	$v = trim( $v);	// trim front and trailing spaces
	return $v;
}
function ldfixbinary( &$info, $fields) { foreach ( $info as $k => $v) {
	if ( $fields[ $k] != 'unindexed') continue;
	if ( ! trim( $v)) continue;
	$L = ttl( $v, ' '); $L2 = array();
	foreach ( $L as $v) {
		//echo "FIXBINARY h (" . h2json( @json2h( $v, true)) . ")\n";
		$h = @json2h( $v, true); if ( $h && is_array( $h)) { lpush( $L2, $v); continue; }
		$h = @tth( s642s( $v)); if ( $h && is_array( $h)) { lpush( $L2, $v); continue; }
	}
	$info[ $k] = count( $L2) ? ltt( $L2, ' ') : '';
	$info[ $k . 'count'] = $info[ $k] ? count( ttl( $info[ $k], ' ')) : 0;
}}

?>