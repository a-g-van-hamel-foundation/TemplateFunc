<?php

namespace TF;

use MediaWiki\Title\Title;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Registration\ExtensionRegistry;

class TFUtils {

	public static function getRawContentFromPageID( $id ) {
		$titleObj = Title::newFromID( $id, 0 );
		$res = self::getRawContentFromTitleObj( $titleObj, false );
		return $res;
	}

	/**
	 * Get the raw, unparsed content of a page 
	 * (current revision; main slot by default)
	 */
	public static function getRawContentFromPage( string $fullpagename, string $slot = "main" ) {
		$titleObj = Title::newFromText ( $fullpagename );
		$res = self::getRawContentFromTitleObj( $titleObj, false, $slot );
		return $res;
	}

	/**
	 * Get source code of content associated with Title
	 * @param Title $titleObj
	 * @param mixed $revisionRecord
	 * @param mixed $slot
	 * @return string|bool
	 */
	public static function getRawContentFromTitleObj(
		Title $titleObj,
		$revisionRecord = false,
		$slot = "main"
	): string|bool {
		if ( $revisionRecord == false ) {
			$revisionRecord = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $titleObj, 0 );
		}
		if ( $slot == "main" ) {
			$slotRec = SlotRecord::MAIN;
		} elseif( $slot !== null ) {
			$slotRec = $slot; // ?
		}
		if ( $revisionRecord && $revisionRecord->hasSlot( $slot ) ) {
			// Revision record found
			$text = $revisionRecord->getContent( $slotRec, RevisionRecord::RAW )->getText();
		} else {
			// No revision record found, or no slot associated
			return false;
		}
		return $text;
	}

	/**
	 * Get ParserOutput instance from the cache of the RevisionOutput
	 */
	public static function getParserOutputFromRevisionOutputCache( $name, $revisionRecord, $parserOptions ) {
		// Instantiate cache by name
		$revisionOutputCache = MediaWikiServices::getInstance()->getParserCacheFactory()->getRevisionOutputCache( $name );
		// Get ParserOutput
		$parserOutput = $revisionOutputCache->get( $revisionRecord, $parserOptions );
		return $parserOutput;
	}

	/**
	 * Converts array to JSON string to be displayed on the wiki.
	 * Copied from the CODECSResources extension.
	 */
	public static function showArrayAsJsonInWikiText( array $arr ): string {
		$registry = ExtensionRegistry::getInstance();
		$encoded = json_encode( $arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( $encoded == false ) {
			return "";
		}
		if ( $registry->isLoaded( 'SyntaxHighlight' ) == true || $registry->isLoaded( 'highlight.js integration' ) == true )  {
			$str = "<syntaxhighlight lang='json'>" . $encoded . "</syntaxhighlight>";
		} else {
			$str = "<pre lang='json'>" . $encoded . "</pre>";
		}
		return $str;
	}

	/**
	 * Simple check if array is associative or sequential
	 * @param array $arr
	 * @return bool
	 */
	public static function isAssociativeArray( array $arr ) {
		if ( array_keys( $arr ) !== range( 0, count( $arr ) - 1) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param mixed $arr
	 * @return bool
	 */
	public static function areAllArrayElementsStrings( $arr )  {
		return array_sum(array_map('is_string', $arr)) == count($arr);
	}

}
