<?php

class StringexSetup { // wdir, keyHashBits, keyHashMask, docHashBits, docHashMask, localSizeLimit, verbose 
	public $docid = 0;
	public $wdir = '.';
	public $keyHashBits = 16;
	public $keyHashMask = 4;
	public $docHashBits = 32;
	public $docHashMask = 24;
	public $localSizeLimit = 2000;	// in kb
	public $verbose = false;
	public $keys = array();
	public function __construct( $h) { foreach ( $h as $k => $v) $this->$k = is_numeric( $v) ? round( $v) : $v; }
	public function ashash() { return get_object_vars( $this); }
}
class StringexMeta { //  { blockmask: { hashkey: object, ...}, ...}
	protected $setup;
	protected $name;
	protected $h; //  { blockey(bk): { itemkey(ik): [ doc id, ...], ...}, ...}
	protected $log = array();	// { time: { blockmask}, ..}
	protected $blockstats = array();
	public $stats = array( 'size' => 0, 'reads' => 0, 'writes' => 0, 'readbytes' => 0, 'writebytes' => 0, 'blocks' => 0); 	// stats
	public function __construct( $name, $setup) { 
		$this->setup = $setup;
		$this->name = $name;
		$this->h = array();
	}
	protected function log( $bk, $bks) {
		$time = tsystem();
		unset( $this->log[ $bk]); $this->log[ $bk] = compact( ttl( 'bks,time'));
	}
	protected function itemkey( $k) { 
		$k2 = cryptCRC24( bstring2bytes( $k));
		$k3 = btail( $k2 >> ( 24 - $this->setup->keyHashBits), $this->setup->keyHashBits);
		return $k3;
	}
	protected function blockey( $ik) { return btail( $ik >> ( $this->setup->keyHashBits - $this->setup->keyHashMask), $this->setup->keyHashMask);}
	protected function blockey2string( $bk) { return sprintf( '%0' . ( round( log10( round( b01( 32 - $this->setup->keyHashMask, $this->setup->keyHashMask)))) + 2) . 'd', $bk); }
	protected function makeys( $k, $ik = null, $bk = null, $bks = null) { 
		if ( ! $ik) $ik = $this->itemkey( $k); 
		if ( ! $bk) $bk = $this->blockey( $ik);
		if ( ! $bks) $bks = $this->blockey2string( $bk);
		return array( $ik, $bk, $bks, $this->setup->wdir, $this->name);
	}
	protected function updateblocksize( $bk) { 
		$size = 0; 
		if ( $this->setup->verbose) foreach ( $this->h[ $bk] as $ik => $docs) {
			$docs = hk( $docs); $__ik = $ik;
			$size += strlen( h2json( compact( ttl( '__Ik,docs')), true, '', false, true));
		}
		$this->blockstats[ $bk] = $size;	// update block size
		$this->stats[ 'size'] = round( 0.001 * $size);
	}
	// interface
	public function find( $k, $docid = null, $ik = null, $bk = null, $bks = null) { // null | [ docids] | { bk, ik} 
		list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( $k, $ik, $bk, $bks);
		//die( jsonraw( compact( 'ik,bk,bks,wdir,name')));
		if ( ! isset( $this->h[ $bk]) && is_file( "$wdir/$name.$bks")) { // load the block
			//echo "$name > LOAD BLOCK $wdir/$name.$bks\n";
			if ( ! is_file( "$wdir/$name.$bks")) return null;
			$in = finopen( "$wdir/$name.$bks"); htouch( $this->h, $bk);
			while ( ! findone( $in)) {
				list( $h, $p) = finread( $in); if ( ! $h) continue;
				$this->stats[ 'reads']++;
				extract( $h); // __ik, docs
				htouch( $this->h[ $bk], $ik);
				foreach ( $docs as $doc) $this->h[ $bk][ $ik][ "$doc"] = true;
			}
			$this->stats[ 'readbytes'] += $in[ 'current']; 	// how many bytes read
			$this->blockstats[ $bk] = $in[ 'current'];
			$this->stats[ 'size'] = round( 0.001 * msum( hv( $this->blockstats)));
			finclose( $in);
		}
		if ( isset( $this->h[ $bk]) && isset( $this->h[ $bk][ $ik]) && $docid !== null && isset( $this->h[ $bk][ $ik][ "$docid"])) return compact( ttl( 'bk,ik'));
		if ( isset( $this->h[ $bk]) && isset( $this->h[ $bk][ $ik]) && $docid === null) return hk( $this->h[ $bk][ $ik]);	// list of docs
		return null;
	}
	public function add( $k, $docid, $syncnow = false, $ik = null, $bk = null, $bks = null) {
		list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( $k, $ik, $bk, $bks);
		$h = $this->find( $k, $docid, $bk, $bks);
		if ( ! $h) $this->log( $bk, $bks);	// new doc, mark the log
		htouch( $this->h, $bk); htouch( $this->h[ $bk], $ik);
		$this->h[ $bk][ $ik][ "$docid"] = true;
		$this->updateblocksize( $bk);
		//die( jsonraw( $this->blockstats));
		$this->stats[ 'size'] = round( 0.001 * msum( hv( $this->blockstats)));
		if ( $syncnow) $this->sync( tsystem());
	}
	public function purge( $k, $docid, $syncnow = false, $ik = null, $bk = null, $bks = null) {
		list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( $k, $ik, $bk, $bks);
		$h = $this->find( $k, $docid, $bk, $bks);
		if ( $h) $this->log( $bk, $bks);
		htouch( $this->h, $bk); htouch( $this->h[ $bk], $ik);
		unset( $this->h[ $bk][ $ik][ "$docid"]);
		if ( ! count( $this->h[ $bk][ $ik])) unset( $this->h[ $bk][ $ik]);
		if ( ! count( $this->h[ $bk])) unset( $this->h[ $bk]);
		$this->updateblocksize( $bk);
	}
	public function sync( $time2 = 'one', $emulate = false) { // write all changes to disk -- returns earliest time
		if ( $time2 == 'one' && count( $this->log)) $time2 = mmin( hltl( hv( $this->log), 'time'));	// pop only one
		if ( $time2 == 'one' || ! $time2) $time2 = tsystem();
		//echo " META SYNC ($time2): " . jsonraw( $this->log) . "\n";
		$wdir = $this->setup->wdir; $name = $this->name;
		//echo "\n\n"; echo $this->name . '  log: ' . jsonraw( $this->log) . "\n";
		foreach ( hk( $this->log) as $bk) { 
			$bk = round( $bk);
			extract( $this->log[ $bk]); // bks, time
			if ( $time > $time2) continue;	// skip this one, too early
			unset( $this->log[ $bk]);
			$this->stats[ 'writes']++;
			if ( ! isset( $this->h[ $bk])) { `rm -Rf $wdir/$name.$bks`; continue; }
			$out = foutopen( "$wdir/$name.$bks", 'w');
			foreach ( $this->h[ $bk] as $ik => $docs) {
				$docs = hk( $docs); $__ik = $ik;
				foutwrite( $out, compact( ttl( '__Ik,docs'))); 
			}
			$this->stats[ 'writebytes'] += $out[ 'bytes'];
			foutclose( $out); unset( $this->h[ $bk]);
			unset( $this->h[ $bk]); $this->blockstats[ $bk] = 0;
		}
		$this->stats[ 'size'] = round( 0.001 * msum( hv( $this->blockstats))); 
		if ( ! count( $this->h)) $this->stats[ 'size'] = 0;
		return count( $this->log) ? mmin( hltl( hv( $this->log), 'time')) : tsystem();
	}
	public function stats() { $this->stats[ 'blocks'] = count( $this->h); return $this->stats; }
}
class StringexDocs extends StringexMeta { //  { blockmask: { hashkey: object, ...}, ...}
	protected $setup;
	protected $name;
	protected $h; //  { blockey(bk): { docid: { doc hash + __docid}, ...}, ...}
	protected $log = array();	// { time: { blockmask}, ..}
	protected $blockstats = array();
	public $stats = array( 'size' => 0, 'reads' => 0, 'writes' => 0, 'readbytes' => 0, 'writebytes' => 0); 	// stats
	public function __construct( $setup) { 
		$this->setup = $setup;
		$this->name = 'docs';
		$this->h = array();
	}
	protected function updateblocksize( $bk) { 
		$size = 0;
		if ( $this->setup->verbose) foreach ( $this->h[ $bk] as $docid => $doc) $size += strlen( h2json( $doc, true, '', false, true));
		$this->blockstats[ $bk] = $size;	// update block size
		$this->stats[ 'size'] = round( 0.001 * msum( hv( $this->blockstats)));
	}
	protected function blockey( $ik) { return btail( $ik >> ( $this->setup->docHashBits - $this->setup->docHashMask), $this->setup->docHashMask);}
	// interface
	public function get( $docid, $bk = null, $bks = null) { // null | doc hash
		list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( ( int)$docid, ( int)$docid, $bk, $bks);
		if ( ! isset( $this->h[ $bk]) && is_file( "$wdir/$name.$bks")) { // load the block
			if ( ! is_file( "$wdir/$name.$bks")) return null;
			$in = finopen( "$wdir/$name.$bks"); htouch( $this->h, $bk);
			while ( ! findone( $in)) {
				list( $h, $p) = finread( $in); if ( ! $h) continue;
				$this->stats[ 'reads']++;
				extract( $h); // __docid, data hash
				$this->h[ $bk][ "$__docid"] = $h;
			}
			$this->updateblocksize( $bk);
			$this->stats[ 'readbytes'] += $in[ 'current']; 	// how many bytes read
			finclose( $in);
		}
		if ( isset( $this->h[ $bk]) && isset( $this->h[ $bk][ "$docid"])) return $this->h[ $bk][ "$docid"];	// doc
		return null;
	}
	public function set( $docid, $doc, $syncnow = false, $ik = null, $bk = null, $bks = null) {
		list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( ( int)$docid, ( int)$docid, $bk, $bks);
		//echo "  SET(docid=$docid,ik=$ik,bk=$bk,bks=$bks)\n";
		$h = $this->get( $docid, $bk, $bks);
		$this->log( $bk, $bks);	// new doc, mark the log
		if ( ! $h) $h = array();
		$h = hm( $h, $doc); $h = hm( $h, array( '__docid' => ( int)$docid)); 
		htouch( $this->h, $bk);
		$this->h[ $bk][ "$docid"] = $h;
		$this->updateblocksize( $bk); 
		if ( $syncnow) $this->sync( tsystem());
	}
	public function purge( $docid, $syncnow = false, $ik = null, $bk = null, $bks = null) {
		list( $ik, $bk, $bks, $wdir, $name) = $this->makeys( ( int)$docid, ( int)$docid, $bk, $bks);
		$h = $this->get( $docid, $bk, $bks);
		if ( ! $h) return;		// no doc, nothing to do
		$this->log( $bk, $bks);
		unset( $this->h[ $bk][ "$docid"]);
		if ( ! count( $this->h[ $bk])) unset( $this->h[ $bk]);
		$this->updateblocksize( $bk);
	}
	public function sync( $time2 = 'one') { // write all changes to disk
		if ( $time2 == 'one' && count( $this->log)) $time2 = mmin( hltl( hv( $this->log), 'time'));	// pop only one
		if ( $time2 == 'one' || ! $time2) $time2 = tsystem();
		//echo " SYNC($time2) : " . jsonraw( $this->log) . "\n\n";
		$wdir = $this->setup->wdir; $name = $this->name;
		//echo "\n\n"; echo jsonraw( $this->log)  . "\n";
		foreach ( hk( $this->log) as $bk) { 
			extract( $this->log[ $bk]); // bks, time
			if ( $time > $time2) continue;	// skip this one, too early
			unset( $this->log[ $bk]);
			$this->stats[ 'writes']++;
			if ( ! isset( $this->h[ $bk])) { `rm -Rf $wdir/$name.$bks`; continue; }
			$out = foutopen( "$wdir/$name.$bks", 'w');
			foreach ( $this->h[ $bk] as $docid => $doc) foutwrite( $out, $doc); 
			$this->stats[ 'writebytes'] += $out[ 'bytes'];
			unset( $this->h[ $bk]);
			$this->blockstats[ $bk] = 0;
			foutclose( $out); 
		}
		if ( ! count( $this->h)) $this->stats[ 'size'] = 0;
		$this->stats[ 'size'] = round( 0.001 * msum( hv( $this->blockstats))); 
		return count( $this->log) ? mmin( hltl( hv( $this->log), 'time')) : tsystem();
	}
	
}
class Stringex { 
	private $setup;
	public $keys = array();
	public $docs;
	public function __construct( $setup) { // if setup is string, then it is a wdir
		if ( is_string( $setup)) $wdir = $setup; else $wdir = $setup->wdir;
		if ( ! is_dir( $wdir)) mkdir( $wdir);
		if ( ! is_dir( $wdir)) die( "ERROR! Stringex:__construct() cannot find [$wdir]\n");
		`chmod -R 777 $wdir`;
		if ( is_string( $setup) && is_file( "$wdir/setup.json")) $setup = new StringexSetup( jsonload( "$wdir/setup.json"));
		$this->setup = $setup;
		$this->docs = new StringexDocs( $setup);
		foreach ( $setup->keys as $k) $this->keys[ $k] = new StringexMeta( $k, $setup);
		jsondump( $setup->ashash(), "$wdir/setup.json");
	}
	// stats
	public function stats() {
		$stats = array();
		foreach ( $this->keys as $k => $K) {
			if ( ! $stats) $stats = $K->stats();
			else foreach ( $K->stats() as $k2 => $v2) $stats[ $k2] += $v2;
		}
		foreach ( $this->docs->stats() as $k => $v) $stats[ $k] += $v;
		return $stats;
	}
	public function count() { return $this->setup->docid; }
	// actions
	public function get( $docids, $h = null) {	// return docs for ids -- if ( h) verifies the input 
		$L = array();
		if ( is_string( $docids)) $docids = ttl( $docids);
		foreach ( $docids as $docid) {
			$h2 = $this->docs->get( $docid); if ( ! $h2) die( "ERROR! Stringex:find() Doc($docid) not found in docs! Should not happen.\n");
			if ( ! $h) { lpush( $L, $h2); continue; }
			$ok = true;
			foreach ( $h as $k => $v) {
				if ( ! isset( $h2[ $k])) { $ok = false; break; }
				$v2 = is_array( $h2[ $k]) ? ltt( $h2[ $k], ' ') : $h2[ $k];
				if ( strpos( $v2, $v) === false) { $ok = false; break; }
			}
			if ( $ok) lpush( $L, $h2);
		}
		return $L;
	}
	public function find( $h, $idonly = false) { // null | list of docs(+__docid)  -- search as intersection of keys
		$H = array();
		foreach ( $h as $k => $v) {
			if ( ! isset( $this->keys[ $k])) continue;
			$docs = $this->keys[ $k]->find( $v); 
			//echo " DOCS($k=$v): " . jsonraw( $docs) . "\n";
			if ( ! $docs) return null;	// no such docs
			if ( ! count( $H)) { $H = hvak( $docs, true, true); continue; } 	// first list
			foreach ( $docs as $docid) if ( ! isset( $H[ "$docid"])) unset( $H[ "$docid"]);
			$docs = hvak( $docs, true, true); foreach ( hk( $H) as $docid) if ( ! isset( $docs[ "$docid"])) unset( $H[ "$docid"]);
			if ( ! count( $H)) return null; 	// no matches
		}
		if ( ! count( $H)) return null;
		if ( $idonly) return hk( $H);
		return $this->get( hk( $H));
	}
	public function add( $h, $syncnow = false) { 
		$this->setup->docid++;
		$h[ '__docid'] = $this->setup->docid;
		$this->docs->set( $this->setup->docid, $h, $syncnow);
		foreach ( $h as $k => $v) {
			if ( $k == '__docid') continue;
			if ( ! isset( $this->keys[ $k])) continue;
			if  ( ! $v) continue;	// no information in this key
			if ( is_string( $v)) $v = array( $v);
			foreach ( $v as $v2) $this->keys[ $k]->add( $v2, $this->setup->docid, $syncnow);
		}
		
	}
	public function purge( $h, $syncnow = false) { foreach ( $h as $k => $v) { 
		if ( $k == '__docid') continue;
		if ( ! $v) continue;
		if ( is_string( $v)) $v = array( $v);
		foreach ( $v as $v2) $this->keys[ $k]->purge( $v2, $h[ '__docid'], $syncnow);
	}}
	public function commit( $time2 = null) { 	// commit all changes to disk
		$wdir = $this->setup->wdir;
		if ( ! $time2) $time2 = tsystem();
		foreach ( $this->keys as $k => $K) $K->sync( $time2);
		$this->docs->sync( $time2);
		jsondump( $this->setup->ashash(), "$wdir/setup.json");
	}
	
}

?>