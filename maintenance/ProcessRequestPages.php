<?php

namespace MediaWiki\Extension\CommunityRequests\Maintenance;

use Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ProcessRequestPages extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CommunityRequests' );
	}

	public function execute() {
		$this->output( "This is the CommunityRequests extension.\n" );
	}
}

$maintClass = ProcessRequestPages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
