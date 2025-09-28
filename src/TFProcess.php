<?php

/**
 * Prevent issues with parser cache, notably job queues hanging indefinitely
 * See TFHooks::onRevisionFromEditComplete(), where currently, $wikiPage->doPurge() is enough.
 */

namespace TF;

use Title;
use WikiPage;
use CommentStoreComment;

class TFProcess {

	/**
	 * @param WikiPage $wikiPage
	 * @return void
	 */
	public static function doPurge( WikiPage $wikiPage ): void {
		// Why again?
		$title = $wikiPage->getTitle()->getFullText();
		$titleObject = Title::newFromText( $title );
		$wikiPage = WikiPage::factory( $titleObject );		
		$wikiPage->doPurge();
		// self::doNullEdit( $wikiPageObject, $user  );
		// confif debug
	}

	/**
	 * Perform a null edit on the page
	 * @param WikiPage $wikiPage
	 * @param mixed $user
	 * @return void
	 */
	public static function doNullEdit( WikiPage $wikiPage, $user ): void {
		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$result = $pageUpdater->saveRevision(
			CommentStoreComment::newUnsavedComment( "" ),
			EDIT_SUPPRESS_RC | EDIT_AUTOSUMMARY
		);
	}
	
}
