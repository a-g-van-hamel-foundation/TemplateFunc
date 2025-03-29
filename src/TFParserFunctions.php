<?php

/**
 * Parser functions.
 * @author DG
 */

namespace TF;

use MediaWiki\MediaWikiServices;
// use SMW\Parser\RecursiveTextProcessor;

/**
 * #tf-convert
 * 
 * @todo maybe assign 'page' to template param (like index and userparam)
 * @todo maybe allow user to set offset for index (default = 1/0?) and length
 * @todo maybe add parser function to get unparsed content - does mode=raw work?
 * @todo maybe add direct support for #subobject?
 * @todo JSON (source): parent node (parentParam)
 * @todo JSON (target): improve representation
 */

class TFParserFunctions {

	private $isTFConvertUsed = false;

	/**
	 * Parser function {{#tf-convert:}}
	 * @todo unify parser functions: runMustacheTemplate
	 */
	public function runConvert( \Parser &$parser, \PPFrame $frame, $params ) {
		$parserOutput = $parser->getOutput();

		// Update cache expiry should be done only once on the page
		$extData = $parserOutput->getExtensionData( "templatefunc-tfconvert-used" );
		if ( $extData == null ) {
			$parserOutput->appendExtensionData(
				"templatefunc-tfconvert-used",
				sha1( rand(10000,99999) )
			);
			$parserOutput->updateCacheExpiry(0);
		}

		// Parameters used, with their defaults, excl. user params.
		$paramsAllowed = [
			"page" => false,
			"slot" => "main",
			"sourceformat" => "template",
			"sourcetemplate" => null, // e.g. ParentTpl[param].ChildTpl
			"parenttemplate" => null, // @deprecate?
			"parenttemplateparam" => "", // @deprecate?
			"template" => "", // childtemplate // @deprecate?
			"sourcenode" => null, // @todo - previously also parentparam
			"targettemplate" => null,
			"targetwidget" => null,
			"targetmodule" => null,
			"targetmustache" => null,
			"targetmustachedir" => null,
			"target" => null,
			"data" => "all",
			"indexname" => "index",
			"mode" => "normal",
			"action" => "normal"
		];
		// Keep this aligned with order above :
		$params = $this->extractParams( $frame, $params, $paramsAllowed );
		list( $page, $slot, $sourceFormat, $sourceTemplate, $parentTemplate, $parentTemplateParam, $childTemplate, $sourceNode, $targetTemplate, $targetWidget, $targetModule, $targetMustacheTemplate, $targetMustacheDir, $target, $data, $indexName, $mode, $action ) = array_values( $params['params'] );
		$userParams = $params['userparams'];

		// wiki template first
		$sourceFormat = strtolower( $sourceFormat );
		switch ( $sourceFormat ) {
			case "template":
				if ( $sourceTemplate !== null ) {
					// decode shorthand like Recipe[Ingredients].Ingredient data
					list( $parentTemplate, $parentTemplateParam, $childTemplate ) = $this->decodeParamShorthand( $sourceTemplate );
				}
				$sourceNodeName = $childTemplate ?? $parentTemplate;
				break;
			case "json":
				$sourceNode = $sourceNode ?? $sourceTemplate;
				$nodePath = ( $sourceNode !== null )
					? $this->decodeJsonShorthand( $sourceNode )
					: [];
				// list( $parentNode, $parentNodeKey, $childNode ) = $this->decodeParamShorthand( $sourceNode );
				// @todo used when checking validity of params
				$sourceNodeName = null;
				break;
			case "raw":
				$sourceNodeName = null;
				break;
		}

		list( $targetName, $targetType, $targetMustacheDir ) = $this->getTargetData( $targetTemplate, $targetWidget, $targetModule, $targetMustacheTemplate, $targetMustacheDir, $target );

		// Check validity
		$paramsInvalid = $this->areParamsForPFInvalid( $page, $sourceFormat, $sourceNodeName, $targetType, $targetName );
		if ( $paramsInvalid ) {
			return $paramsInvalid;
		};
		// Track usage of this parser function
		// @todo rename
		$parserOutput->appendExtensionData( "templateFuncData-pf-counter", "tf-convert" );

		// Get content of source page
		$titleObj = \Title::newFromText( $page );
		$revisionRecord = $parser->fetchCurrentRevisionRecordOfTitle( $titleObj );
		$rawPageContent = TFUtils::getRawContentFromTitleObj(
			$titleObj,
			$revisionRecord,
			$slot
		);
		if ( $rawPageContent == false ) {
			//print_r( "Sorry, this parser function did not work. Going to fail silently." );
			return "";
		}

		switch( trim( $sourceFormat ) ) {
			case "template":
				$templateArray = $this->convertTemplatesToArray( $rawPageContent, $parentTemplate, $parentTemplateParam, $childTemplate );
				$str = TFConvert::convertArrayToWikiOutput( $templateArray, $sourceFormat, $data, $targetType, $targetName, $targetMustacheDir, $page, $indexName, $userParams );
				break;
			case "json":
				$targetName = ( $targetTemplate !== null ) ? $targetTemplate : "";
				$instancesArr = $this->convertJsonStrToArray( $rawPageContent, $nodePath );
				$str = ( $instancesArr !== false )
					? TFConvert::convertArrayToWikiOutput( $instancesArr, $sourceFormat, $data, $targetType, $targetName, $targetMustacheDir, $page, $indexName, $userParams )
					: "";
				break;
			case "raw":
				$str = $rawPageContent;
				break;
		}

		// @todo clean up a bit
		trim( $str );
		$enclosedStrRaw = \Html::rawElement( "div", [ "class" => "tf-convert-result" ], trim( $str ) );

		// Some of these options are not officially supported but may be useful when debugging.
		switch( $mode ) {
			case "string":
				$res = $str;
				break;
			case "unparsed-html":
			case "raw":
				//$str = \Html::rawElement( "div", [ "class" => "tf-convert-result--raw" ], $str );
				$res = [ $enclosedStrRaw, 'noparse' => true, 'isHTML' => true ];
				break;
			case "unparsed-nohtml":
				$res = [ $enclosedStrRaw, 'noparse' => true, 'isHTML' => false ];
				break;
			case "parsed-nohtml":
				// $res = [ $enclosedStrRaw, 'noparse' => false, 'isHTML' => false ];
				$res = [ $enclosedStrRaw, 'noparse' => false, 'isHTML' => false ];
				break;
			case "parsed-html":
				$res = [ $enclosedStrRaw, 'noparse' => false, 'isHTML' => true ];
				break;
			case "pre":
				$str = \Html::rawElement( "pre", [], $str );
				$res = [ $str, 'noparse' => true, 'isHTML' => true ];
				break;
			case "lazy":
				trim( $str );
				// $str = str_replace( "\n", " ", trim($str) );
				$res = [ $this->lazyParse( $str, $page ), 'noparse' => false, 'isHTML' => true ];
				break;
			default:
				if ( $sourceFormat == "raw" ) {
					$res = [ $str, 'noparse' => true, 'isHTML' => true ];
				} elseif ( $sourceFormat == "json" ) {
					// noparse/isHTML same as Page Forms
					//$str = \Html::rawElement( "div", [ "class" => "tf-convert-result" ], $str );
					$res = [ $enclosedStrRaw, 'noparse' => false, 'isHTML' => true ];
				} else {
					//$str = \Html::rawElement( "div", [ "class" => "tf-convert-result" ], $str );
					$res = [ $enclosedStrRaw, 'noparse' => false, 'isHTML' => false ];
				}
		}
		return $res;
	}

	/**
	 * The current assumption is to always get child templates.
	 * @todo Make sure we can use the main template.
	 */
	private function convertTemplatesToArray(
		string $rawPageContent,
		string|null $parentTemplate,
		string|null $parentTemplateParam,
		string|null $childTemplate
	) {
		// Retrieve content from parent template
		$newRecursiveParser = new WSRecursiveParser;
		$templateStructure = $newRecursiveParser->parse( $rawPageContent );

		// parent template only
		if ( $childTemplate == null ) {
			if ( isset( $templateStructure[$parentTemplate] ) ) {
				$templateArr = $templateStructure[$parentTemplate];
			} else {
				// @todo throw error message.
				$templateArr = [];
			}
			return $templateArr;
		}

		// child templates
		$childTemplateArray = [];
		if ( isset( $templateStructure[$parentTemplate][0][$parentTemplateParam][$childTemplate] ) ) {
			$childTemplateArray = $templateStructure[$parentTemplate][0][$parentTemplateParam][$childTemplate];
		} else {
			$msg = "<div class='alert alert-warning'>" . wfMessage( 'templatefunc-cannot-find-template' )->parse() . "</div>";
			// @todo throw error message.
		}

		return $childTemplateArray;
	}

	

	/**
	 * Converts string of JSON instances to array.
	 * @todo Consider moving to TFConvert??
	 * @return array|false
	 */
	private function convertJsonStrToArray(
		string $rawPageContent,
		array $nodePath = []
	): array|false {
		$minifiedPageContent = preg_replace( "/\s+/", " ", $rawPageContent);
		$jsonArr = json_decode( $minifiedPageContent, true);
		if ( $jsonArr == null ) {
			return false;
		}
		// Without a parent node, we're assuming the root array is what we want.
		$instancesArr = $jsonArr;
		if( !empty( $nodePath ) ) {
			// Looking for nested items in array
			foreach( $nodePath as $node ) {
				if ( array_key_exists( $node, $instancesArr ) ) {
					$instancesArr[] = $instancesArr[$node];
				}
			}
		}
		//$instancesArr = ( $parentParam !== "" ) ? $jsonArr[$parentParam] : $jsonArr;
		if ( $instancesArr == null ) {
			return false;
		}
		return $instancesArr;
	}

	/**
	 * Entry point for `{{#tf-mustache: template= |name= |associated= }}`
	 * Uses Mustache template in the designated folder
	 * @note Every input must be parsed.
	 * User params: template (required), templatedir (optional) and Mustache template params
	 */
	public function runMustacheTemplate( $parser, $frame, $params ) {
		$newParams = [];
		$template = $templatedir = null;
		foreach ($params as $i => $param) {
			$paramExpanded = $frame->expand($param);
			$keyValPair = explode('=', $paramExpanded, 2);
			$paramName = trim( $keyValPair[0] );
			$value = (array_key_exists(1, $keyValPair)) ? trim( $keyValPair[1] ) : "";
			// get Mustache template name
			switch ($paramName) {
				case 'template':
					$template = $value;
					break;
				case 'templatedir':
					$templatedir = $value;
					break;
			}
			// Maybe introduce some intermediate logic here?
			// Create new array of trimmed, parsed values
			// In our case, we don't want to accept empty values
			$newParams[$paramName] = ( $value !== "" ) ? $value : null;
		}
		if ( $template == null ) {
			return "";
		}

		// Get templates directory
		global $IP;
		$templatedir = ( $templatedir == null ) ? TFMustache::getMustacheTemplatesDir() : $IP . $templatedir;
		if ( !is_dir( $templatedir ) ) {
			// @todo log, localisaton
			return "<div class='mw-error-msg'>Error: Mustache template directory not found</div>";
		}
		$res = TFMustache::processMustacheTemplate( $newParams, $template, $templatedir );
		return $res;
	}

	/**
	 * Helper function
	 * Extracts parameters based on the mapping provided and assigns defaults.
	 * Separately extracts user parameters beg. with 'userparam...'
	 * Returns regular and user params as separate arrays.
	 */
	private function extractParams( $frame, array $params, $paramsAllowed = [] ) {
		$incomingParams = [];
		foreach ( $params as $param) {
			$paramExpanded = $frame->expand( $param );
			$keyValPair = explode('=', $paramExpanded, 2);
			$paramName = trim( $keyValPair[0] );
			$value = ( array_key_exists( 1, $keyValPair) ) ? trim( $keyValPair[1] ) : "";
			$incomingParams[$paramName] = $value;
		}

		$params = [];
		foreach ( $paramsAllowed as $paramName => $default ) {
			$params[$paramName] = ( array_key_exists( $paramName, $incomingParams ) ) ? $incomingParams[$paramName] : $default;
		}

		$userParams = [];
		foreach ( $incomingParams as $k => $v ) {
			if ( substr( $k, 0, 9 ) == 'userparam' ) {
				$userParams[ $k ] = $v;
			}
		}

		return [
			"params" => $params,
			"userparams" => $userParams
		];
	}

	/**
	 * Helper function to decode shorthand param 'sourcetemplate'
	 * e.g. Recipe (parent template only) 
	 * or Recipe[Ingredients].Ingredient data
	 */
	private function decodeParamShorthand( $sourceTemplate ): array {
		$arr = explode( ".", $sourceTemplate );
		$parentElements = preg_split( '/[\[\]]/', $arr[0] );
		$parentTemplate = trim( $parentElements[0] );
		$parentTemplateParam = array_key_exists( 1, $parentElements ) ? trim( $parentElements[1] ) : null;
		$childTemplate = array_key_exists( 1, $arr ) ? trim( $arr[1] ) : null;
		return [ $parentTemplate, $parentTemplateParam, $childTemplate ];
	}

	/**
	 * Helper function
	 * empty or * - root
	 * event.data.name (alternative: $.event.data.name)
	 * @param string $sourceNode
	 * @return array
	 */
	private function decodeJsonShorthand( string|null $sourceNode ): array {
		if ( $sourceNode == null | $sourceNode === "" || $sourceNode === "$" ) {
			return [];
		}
		$nodeArr = explode( ".", $sourceNode );
		$res = [];
		foreach( $nodeArr as $node ) {
			trim( $node );
			if ( $node !== "$" ) {
				$res[] = $node;
			}
		}
		return $res;
	}

	/**
	 * Helper function for parser function
	 * Returns asset name and output type (template, widget, etc.) as an array
	 */
	private function getTargetData( 
		$targetTemplate = null,
		$targetWidget = null,
		$targetModule = null,
		$targetMustacheTemplate = null,
		$targetMustacheDir = null,
		$target = null
	): array {
		$targetMustacheDir = null;
		if ( $targetTemplate !== null ) {
			$targetType = "template";
			$targetName = $targetTemplate;
		} elseif ( $targetWidget !== null ) {
			// Extension:Widgets
			$targetType = "widget";
			$targetName = $targetWidget;
		} elseif ( $targetModule !== null ) {
			// Lua module
			$targetType = "module";
			$targetName = $targetModule;
		} elseif( $targetMustacheTemplate !== null ) {
			$targetType = "mustache";
			global $IP;
			$targetMustacheDir = isset( $targetMustacheDir ) ? $IP . $targetMustacheDir : TFMustache::getMustacheTemplatesDir();
			$targetName = $targetMustacheTemplate;
		} elseif( $target !== null ) {
			// for now, only "raw" is accepted
			$targetName = "";
			$targetType = "raw";
		} else {
			// Raw JSON representation
			$targetName = "";
			$targetType = "json";
		}
		return [ $targetName, $targetType, $targetMustacheDir ];
	}

	/**
	 * Helper to return error message if parameters are invalid, else false.
	 * nodeName represents name of the wiki template or JSON node.
	 * @todo Should probably cover more.
	 * templateName is either parent or child template of the source page.
	 * @return bool|string
	 */
	private function areParamsForPFInvalid(
		string|false $page,
		string $sourceFormat,
		string|null $templateName,
		string|null $targetType,
		string|null $targetName
	): bool|string {
		if ( empty( $page ) ) {
			return "<div class='alert alert-warning'>" . wfMessage( 'templatefunc-too-few-parameters' )->parse() . " No page provided.</div>";
		}
		if( $sourceFormat == "raw" ) {			
			// @todo No further checks necessary?
			return false;
		} elseif ( $sourceFormat == "json" ) {
			if ( empty( $targetType ) ) {
				return "<div class='alert alert-warning'>" . wfMessage( 'templatefunc-too-few-parameters' )->parse() . " No target type.</div>";
			}
		} else {
			// Assuming default $sourceFormat = "template"
			if ( empty( $templateName ) || empty( $targetType ) ) {
				return "<div class='alert alert-warning'>" . wfMessage( 'templatefunc-too-few-parameters' )->parse() . " </div>";
			}
		}
		// @todo - check that page and template are valid.
		// @todo - check that target asset exists (widgets, templates; mustache?)
		return false;
	}

	/**
	 * Defer generation of parsed output until after page load. 
	 * Currently depends on the CODECSResources extension
	 * @param mixed $str
	 * @param mixed $fullpagename
	 * @return string
	 */
	private function lazyParse( $str, $fullpagename ) {
		// Don't use htmlentities or htmlspecialchars() ?
		$parsable = $parsableModel = "<nowiki>$str</nowiki>";
		$triggerId = $loadmoreId = $paginationId = false;
		$random = rand(10000,99999);
		$attributes = [
			"class" => "cr-parse-request ",
			"id" => "cr-parse-request-conversion-" . $random,
			"data-fullpagename" => $fullpagename,
			"data-trigger" => "afterpageload",
			"data-parse" => $parsable,
			"data-parse-model" => $parsableModel,
			"data-json" => "{}",
			"data-trigger-id" => $triggerId,
			"data-pagination-id" => $paginationId,
			"data-loadmore-id" => $loadmoreId,
			"data-target-action" => "replace",
			"data-valsep" => ";",
			"data-update-url" => false
		];

		// @note - does not work with Html::rawElement or Html::element
		$attrStr = "";
		foreach ( $attributes as $k => $v ) {
			if ( ( $v !== false && $v !== "" ) ) {
				$attrStr .= "{$k}=\"$v\" ";
			}
		}
		$res = "<div {$attrStr}></div>";	
		return $res;
	}

}
