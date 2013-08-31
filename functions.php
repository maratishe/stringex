<?php

// shared by all scripts in this directory
class FilesystemWatch { 
	public $meta;
	public $scale = 1;
	public $reports = array();
	public function __construct( $wdir) {
		$this->meta = flmeta( $wdir);
	}
	public function report() { // returns { bytesin(kb), filesin, filesout}
		$meta = $this->meta;
		$meta2 = flmetaupdate( $meta); 
		$changes = flmetachanges( $meta, $meta2);
		$bytesin = 0; $filesin = 0; $filesout = 0;
		foreach ( $changes as $path => $type) { 
			if ( $type == 'created') $filesin++;
			if ( $type == 'created') $bytesin += $meta2[ 'files'][ $path][ 'size'];
			if ( $type == 'removed') $filesout++;
			if ( $type == 'changed') {
				if ( $meta2[ 'files'][ $path][ 'size'] >= $meta[ 'files'][ $path][ 'size']) $bytesin += $meta2[ 'files'][ $path][ 'size'] - $meta[ 'files'][ $path][ 'size'];
				else $bytesin += $meta2[ 'files'][ $path][ 'size']; 	// re-write the file
			}
			
		}
		$bytesin = round( $this->scale * $bytesin);
		$this->meta = $meta2;
		$size = $this->size();
		$report = compact( ttl( 'bytesin,filesin,filesout,size'));
		lpush( $this->reports, htt( $report));
		return $report;
	}
	public function history() { return $this->reports; } // return the entire history of reports
	public function count() { return count( $this->meta[ 'files']); }
	public function size() { return round( $this->scale * msum( hltl( hv( $this->meta[ 'files']), 'size'))); }
}

?>