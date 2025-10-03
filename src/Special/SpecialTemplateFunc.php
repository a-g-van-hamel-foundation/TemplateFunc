<?php

/**
 * List basic information about the extension
 *
 * @author: Dennis Groenwegen
 * @file
 * @ingroup
 */

namespace TF\Special;

if ( version_compare( MW_VERSION, "1.41", "<" ) ) {
	class_alias( "SpecialPage", "MediaWiki\SpecialPage\SpecialPage" );
}

use MediaWiki\MediaWikiServices;
//use SpecialPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Context\RequestContext;
use TF\TFParserFunctionsInfo;

class SpecialTemplateFunc extends SpecialPage {

	private $extensionName;
	private $extensionJsonSource;

	public function __construct( $name = 'TemplateFunc' ) {
		parent::__construct( $name );
		global $IP;
		$mainConfig = MediaWikiServices::getInstance()->getMainConfig();
		$extAssets = $mainConfig->get( 'ExtensionAssetsPath' );
		$this->extensionName = wfMessage( 'tf-extensionname' )->parse();
		$this->extensionJsonSource = $IP . $extAssets . "/" . $this->extensionName . "/extension.json";
	}

	function isExpensive() {
		return false;
	}

	function isSyndicated() {
		return false;
	}

    public function execute( $subPage ) {
		$outputPage = RequestContext::getMain()->getOutput();
		$res = '';
		$this->setHeaders();

		// @todo custom styling
		//$this->getOutput()->addModuleStyles( [ 'ext.TemplateFunc.special' ] );

		$res = $this->getSpecialPageContent();
		$outputPage->addWikiTextAsContent( $res );

	}

	private function getSpecialPageContent() {

		$version = $this->getExtensionVersion();
		$res = $this->getInfoTable();

		$tfConvertInfo = TFParserFunctionsInfo::getTFConvertInfo();
		//$res .= "<h2>Parameters</h2>" . TF\TFUtils::showArrayAsJsonInWikiText( $tfConvertInfo );
		$res .= "<h2>Parameters</h2>";
		$res .= "<h3><code>#tf-convert</code></h3>" . $this->buildList( $tfConvertInfo["parameters"] );
		return $res;
	}

	private function getInfoTable() {
		$str = "<table class='table'>";
		$str .= "<tr><th>Name</th><td>{$this->extensionName}</td></tr>";
		$version = $this->getExtensionVersion();
		$str .= "<tr><th>Description</th><td>" . $this->getExtensionDescription() . "</td></tr>";
		$str .= "<tr><th>Extension version</th><td>$version</td></tr>";
		$str .= "<tr><th>Repository</th><td>[https://github.com/a-g-van-hamel-foundation/TemplateFunc Github] (code and documentation)</td></tr>";
		$str .= "</table>";
		return $str;
	}

	private function buildList( array $params ) {
		$doc = "";
		foreach ( $params as $k => $param ) {
			$name = "<strong>$k</strong>";
			if ( array_key_exists( "default", $param ) ) {
				$name .= " (default: <code>" . $param["default"] . "</code>)";
			}
			$options = "";
			if ( array_key_exists( "options", $param ) ) {
				$options .= "<p>";
				foreach( $param["options"] as $k => $option ) {
					$options .= "<code>$k</code>: $option <br>";
				}
				$options .= "</p>";
			}
			$description = "<p>" . $param["description"] . "</p>";
			$doc .= $name . $description . $options;
		}
		return $doc;
	}

	private function getExtensionVersion() {
		if ( file_exists( $this->extensionJsonSource ) ) {
			$contents = file_get_contents( $this->extensionJsonSource );
			if ( $contents == null ||  $contents == "" ) {
				return "";
			}
		}
		$arr = json_decode( $contents, true );
		return $arr["version"] ?? "?";
	}

	private function getExtensionDescription() {
		$description = wfMessage( 'tf-desc' )->parse();
		return $description;
	}

}
