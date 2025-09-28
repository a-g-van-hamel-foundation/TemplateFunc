<?php

namespace TF;

use RequestContext;
use TemplateParser;

/**
 * Functions for using Mustache templates.
 * Entry point for the parser function is in TFParserFunctions.
 * @todo Improve error handling.
 */

class TFMustache {

    public static function processMustacheTemplate(
        array $data,
        string $templateName,
        string $templatedir
    ): string {
		if( !file_exists( "{$templatedir}/{$templateName}.mustache" ) ) {
			$context = RequestContext::getMain();
			$errormsg = $context->msg( 'templatefunc-mustache-template-not-found', $templateName )->text();
			error_log( $errormsg );
			return "<div class='mw-error-msg'>{$errormsg}</div>";
		}
		$instances = [];
		if ( !array_key_exists( 0, $data ) ) {
			$instances[] = $data;
		} else {
			$instances = $data;
		}
		$templateParser = new TemplateParser( $templatedir );
		$res = "";
		foreach ( $instances as $instance ) {
			$processed = $templateParser->processTemplate( $templateName, $instance );
			$res .= ($processed !== null ) ? $processed : "";
		}
		return $res;
	}

	/**
	 * Get the directory containing the Mustache templates.
	 * Use config setting or a custom one.
	 * Does not check if directory exists
	 * @return string|bool
	 */
	public static function getMustacheTemplatesDir( $customDir = null ) {
		if ( empty( $customDir ) ) {
			$config = RequestContext::getMain()->getConfig();
			$relPath = trim( $config->get( 'MustacheTemplatesDir' ));
			$relPath = "/" . trim( $relPath, "/" );
		} else {
			$relPath = "/" . trim( $customDir, "/" );
		}
		global $IP;
		$templatesPath = $IP . $relPath;
		return $templatesPath;
	}

}
