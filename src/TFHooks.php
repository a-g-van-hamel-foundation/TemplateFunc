<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\OutputPage;
use MediaWiki\WikiPage;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\InternalParseBeforeLinksHook;
use MediaWiki\Page\Hook\RevisionFromEditCompleteHook;
# use TF\TFProcess;
# use MediaWiki\Registration\ExtensionRegistry;

class TFHooks implements
	ParserFirstCallInitHook,
	InternalParseBeforeLinksHook,
	RevisionFromEditCompleteHook {

	private $isPFUsed = false;

	/**
	 * Register render callbacks with the Parser.
	 * Pass template arguments as PPNode objects. 
	 * See docs in Preprocessor.php for more info about methods in PPFrame and PPNode.
	 * 
	 * Removed: #tf-convert-templates, tf-convert-json-items and tf-transfer-template-data
	 * 
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @link https://www.mediawiki.org/wiki/Manual:Preprocessor.php
	 */
	public function onParserFirstCallInit( $parser ): void {
		$flags = Parser::SFH_OBJECT_ARGS;
		$parser->setFunctionHook(
			'tf-convert',
			function( \Parser $parser, \PPFrame $frame, array $args ) {
					$pf = new TF\TFParserFunctions;
					return $pf->runConvert( $parser, $frame, $args );
			},
			$flags
		);
		$parser->setFunctionHook(
			'tf-mustache',
			function( \Parser $parser, \PPFrame $frame, array $args ) {
					$pf = new TF\TFParserFunctions;
					return $pf->runMustacheTemplate( $parser, $frame, $args );
			},
			$flags
		);
	}

	/**
	 * Do not try to re-parse because a lock is in place.
	 */
	public function onInternalParseBeforeLinks( $parser, &$text, $stripState ) {
		// @todo Maybe do this a little later?
		if ( self::isParserFunctionUsed( $parser ) ) {
			// Disable parser cache.
			// $parser->getOutput()->updateCacheExpiry(0);
			// record that parser function is used. 
			$this->isPFUsed = true;
		}
	}

	/**
	 * Called from PageUpdater::doCreate(), PageUpdater::doModify()
	 * when revision was inserted due to an edit, file upload, import or page move.
	 * There is also onPageSaveComplete, but runs a little later. 
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/RevisionFromEditComplete
	 */
	public function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ): void {
		if ( $this->isPFUsed == true ) {
			$doPurge = \RequestContext::getMain()->getConfig()->get( 'TFDoPurge' );
			if ( $doPurge ) {
				$wikiPage->doPurge();
				// Either a purge or a null edit should do.
				// TFProcess::doPurge( $wikiPage );
				// TFProcess::doNullEdit( $wikiPage, $user );
			}
		}
	}

	/**
	 * Returns true only when the parser function is first invoked.
	 */
	private static function isParserFunctionUsed( Parser $parser ): bool {
		$extData = $parser->getOutput()->getExtensionData( "templateFuncData-pf-counter" );
		if ( $extData !== null && array_key_exists( "tf-convert", $extData ) ) {
			$counter = $extData["tf-convert"];
			$pfIsUsed = ( $counter == 1 ) ? true : false;
			return $pfIsUsed;
		}
		return false;
	}

	/**
	 * Add links to special page of AdminLinks extension
	 * 
	 * @param ALTree &$adminLinksTree
	 * @return bool
	 */
	public static function addToAdminLinks( ALTree &$adminLinksTree ): bool {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Admin Links' ) == false ) {
			return true;
		}
		
		$linkSection = $adminLinksTree->getSection( 'CODECS' );
		if ( is_null( $linkSection ) ) {
			$section = new ALSection( 'CODECS' );
			$adminLinksTree->addSection(
				$section,
				wfMessage( 'adminlinks_general' )->text()
			);
			$linkSection = $adminLinksTree->getSection( 'CODECS' );
			$extensionsRow = new ALRow( 'extensions' );
			$linkSection->addRow( $extensionsRow );
		}

		$extensionsRow = $linkSection->getRow( 'extensions' );

		if ( is_null( $extensionsRow ) ) {
			$extensionsRow = new ALRow( 'extensions' );
			$linkSection->addRow( $extensionsRow );
		}
		
		global $wgScript;
		$realUrl = str_replace( '/index.php', '', $wgScript ) . '/index.php';
		$extensionsRow->addItem(
			ALItem::newFromExternalLink(
				$realUrl . '/Special:TemplateFunc',
				'TemplateFunc'
			)
		);

		return true;
	}

}
