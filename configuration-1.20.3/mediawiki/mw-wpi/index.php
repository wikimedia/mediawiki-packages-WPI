<?php
/**
 * New version of MediaWiki web-based config/installation
 *
 * @file
 */

define( 'MW_CONFIG_CALLBACK', 'Installer::overrideConfig' );
define( 'MEDIAWIKI_INSTALL', true );

chdir( dirname( __DIR__ ) );
if ( isset( $_SERVER['MW_COMPILED'] ) ) {
	require ( 'core/includes/WebStart.php' );
} else {
	require( dirname( __DIR__ ) . '/includes/WebStart.php' );
}

require( dirname( __FILE__ ) . '/WpiSettings.php' );

wfInstallerMain();

function wfInstallerMain() {
//	global $wgRequest, $wgLang, $wgMetaNamespace, $wgCanonicalNamespaceNames;
	global $IP, $wgWPIOptionStore, $wgLanguageCode, $wgEnableUploads;

	$siteName = $wgWPIOptionStore['sitename'];
	$adminName = $wgWPIOptionStore['adminname'];
	$wgLanguageCode = $wgWPIOptionStore['lang'];
	$wgEnableUploads = (strtolower($wgWPIOptionStore['enablefileupload']) == 'true');
	
	$wgWPIOptionStore['scriptpath'] = getScriptPath();
	
	ob_start();
	echo "<pre>";
	
	$installer = InstallerOverrides::getCliInstaller( $siteName, $adminName, $wgWPIOptionStore );
	
	$extensions = array();
	if ( strtolower($wgWPIOptionStore['usewindowsazure']) == "true" ) {
		$extensions[] = 'WindowsAzureStorage';
		$extensions[] = 'WindowsAzureSDK';
	}
	
	$installer->setVar( '_Extensions', $extensions );

	$status = $installer->doEnvironmentChecks();
	if( $status->isGood() ) {
		$installer->showMessage( 'config-env-good' );
		$installer->execute();
		$installer->writeConfigurationFile( $IP );
		// Modify LocalSettings for WindowsAzure
		if ( strtolower($wgWPIOptionStore['usewindowsazure']) == "true" ) {
			$azureSettings = "
\$wgFileBackends[] = array(
		'name'        => 'azure-backend',
		'class'       => 'WindowsAzureFileBackend',
		'lockManager' => 'nullLockManager',
		'azureHost'      => '{$wgWPIOptionStore['azurehost']}',
		'azureAccount'   => '{$wgWPIOptionStore['azureaccount']}',
		'azureKey'       => '{$wgWPIOptionStore['azurekey']}',

		//IMPORTANT: Mind the container naming conventions! http://msdn.microsoft.com/en-us/library/dd135715.aspx
		'wikiId'       => '{$wgWPIOptionStore['wikiId']}',
		'containerPaths' => array(
				'media-public'  => 'media-public',
				'media-thumb'   => 'media-thumb',
				'media-deleted' => 'media-deleted',
				'media-temp'    => 'media-temp',

		)
);

\$wgLocalFileRepo = array (
	'class'             => 'LocalRepo',
	'name'              => 'local',
	'scriptDirUrl'      => '/php/mediawiki-filebackend-azure',
	'scriptExtension'   => '.php',
	'url'               => \$wgScriptPath.'/img_auth.php', // It is important to set this to img_auth. Basically, there is no alternative.
	'hashLevels'        => 2,
	'thumbScriptUrl'    => false,
	'transformVia404'   => false,
	'deletedHashLevels' => 3,
	'backend'           => 'azure-backend',
	'zones' => 
			array (
					'public' => 
							array (
								'container' => 'local-public',
								'directory' => '',
							),
					'thumb' => 
							array(
								'container' => 'local-public',
								'directory' => 'thumb',
							),
					'deleted' => 
							array (
								'container' => 'local-public',
								'directory' => 'deleted',
							),
					'temp' => 
							array(
								'container' => 'local-public',
								'directory' => 'temp',
							)
		)
);

\$wgImgAuthPublicTest = false;
";
			file_put_contents( $IP.'/LocalSettings.php', $azureSettings, FILE_APPEND );
		}
	}
	echo "</pre>";
	
	ob_end_clean();
	
	header('Location: ../');
}

/**
 * Get the script path
 * 
 * @return String $wpiScriptPath The web platform installer script path
 */

function getScriptPath() {
    //setting the default value as empty
    $wpiScriptPath = '';
    $path = false;
	if ( !empty( $_SERVER['PHP_SELF'] ) ) {
		$path = $_SERVER['PHP_SELF'];
	} elseif ( !empty( $_SERVER['SCRIPT_NAME'] ) ) {
		$path = $_SERVER['SCRIPT_NAME'];
	}
	if ($path !== false) {
		$wpiScriptPath = preg_replace( '{^(.*)/(mw-)?config.*$}', '$1', $path );
	}
	return $wpiScriptPath;
}
