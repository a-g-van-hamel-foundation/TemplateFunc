<?php

/**
 * Methods used for the `#tf-convert` parser function
 */

namespace TF;

use TF\TFUtils;
use TF\TFMustache;

class TFConvert {

	/**
	 * Convert array to designated output format.
	 * Version using WSRecursiveParser.
	 */
	public static function convertArrayToWikiOutput(
		array $templateArr,
		string $sourceFormat,
		string $data,
		string $targetType,
		string $targetName,
		string $mustacheTemplateDir = null,
		string $fullpagename = "",
		string $indexName = "index",
		array $userParams = [],
		mixed $targetInstanceTemplates = []
	): mixed {
		if ($templateArr == null) {
			return "";
		}

		// Map source and target parameters
		if ( $data == "all" ) {
			// Reproduce all parameters verbatim
			$dataArr = null;
		} else {
			$dataArr = [];
			$dataList = explode(',', $data);
			foreach ($dataList as $d) {
				$pair = explode('=', trim($d), 2);
				$from = trim( $pair[0] );
				$to = ( array_key_exists( 1, $pair )) ? trim( $pair[1] ) : "";
				$dataArr[$from] = $to;
			}
		}

		//@todo - is this necessarily right?
		if( TFUtils::isAssociativeArray( $templateArr ) ) {
			// If array is associative, add it as instance in array
			$templateArr = [ $templateArr ];
		}

		// The array created from templates is a little bit different.
		if ( $sourceFormat == "template" ) {
			$newInstances = self::createNewInstancesFromTemplateArr( $templateArr, $dataArr, $fullpagename, $indexName, $userParams );
		} elseif ( $sourceFormat == "json" ) {
			$newInstances = self::createNewInstancesFromJsonArr( $templateArr, $dataArr, $fullpagename, $indexName, $userParams );
		} else {
			$newInstances = [];
		}

		switch( $targetType ) {
			case "json":
				$str = TFUtils::showArrayAsJsonInWikiText( $newInstances );
				// $str = json_encode( $newInstances );
				break;
			case "mustache":
				$templatedir = ( $mustacheTemplateDir == null ) ? __DIR__ . "/templates" : $mustacheTemplateDir;
				$str = TFMustache::processMustacheTemplate( $newInstances, $targetName, $templatedir );
				break;
			case "raw":
				// @todo raw output makes sense when the source is raw, too.
				$str = TFUtils::showArrayAsJsonInWikiText( $newInstances );
				break;
			default:
				$str = self::convertArrayToWikiInstances( $newInstances, $targetType, $targetName, false, " ", $targetInstanceTemplates );
		}

		return ( $str !== false ) ? $str : "";
	}

	/**
	 * Create new instances from template array WSRecursiveParser.
	 * 
	 * @param array|null $templateArr
	 * @param array|null $dataArr
	 * @param string|null $fullpagename
	 * @param string|null $indexName
	 * @param array|null $userParams
	 * @return array
	 */
	private static function createNewInstancesFromTemplateArr(
		mixed $templateArr,
		mixed $dataArr,
		mixed $fullpagename = "",
		mixed $indexName = "index",
		mixed $userParams = []
	): array {
		$newInstances = [];
		foreach( $templateArr as $i => $instance ) {
			$newInstances[$i] = [];
			// Additional content
			$newInstances[$i]['fullpagename'] = $fullpagename;
			$newInstances[$i][$indexName] = strval( $i + 1 );
			foreach ( $userParams as $k => $v ) {
				// userparam1, userparam2, etc.
				$newInstances[$i][$k] = ( $v !== null ) ? $v : "";
			}

			// Content from template
			foreach ( $instance as $k => $v ) {
				// $k is parameter name, $v is an array.
				$val = $v['_text'];
				if ( $dataArr == null ) {
					// Same parameters when data=all
					$newInstances[$i][$k] = $val;
				} else {
					$newKey = ( array_key_exists( $k, $dataArr ) ) ? $dataArr[$k] : false;
					if ($newKey !== false) {
						$newInstances[$i][$newKey] = $val;
					}
				}
			}
		}
		return $newInstances;
	}

	/**
	 * Create new instances from JSON-based array.
	 * @param array|null $templateArr
	 * @param array|null $dataArr
	 * @param string|null $fullpagename
	 * @param string|null $indexName
	 * @param array|null $userParams
	 * @return array
	 */
	private static function createNewInstancesFromJsonArr(
		mixed $templateArr,
		mixed $dataArr,
		mixed $fullpagename = "",
		mixed $indexName = "",
		mixed $userParams = []
	): array {
		$newInstances = [];
		foreach ( $templateArr as $i => $instance ) {
			$newInstances[$i] = [];
			// Additional
			$newInstances[$i]['fullpagename'] = $fullpagename;
			$newInstances[$i][$indexName] = strval( $i + 1 );
			foreach ( $userParams as $k => $userParam ) {
				// userparam1, userparam2, etc.
				$newInstances[$i]["userparam{$k}"] = ($userParam !== null) ? $userParam : "";
			}
			foreach ($instance as $k => $v) {
				if ( $dataArr == null ) {
					// Same parameters when data=all
					$newInstances[$i][$k] = $v;
				} else {
					$newKey = (array_key_exists($k, $dataArr)) ? $dataArr[$k] : false;
					if ($newKey !== false) {
						$newInstances[$i][$newKey] = $v;
					}
				}
			}
		}
		return $newInstances;
	}

	/**
	 * Convert associative array to series of instances (raw string, unparsed)
	 * including instances of templates and parser functions
	 * @param array $templateArr - expected to be a sequential array
	 * @param string $templateName
	 * @param bool $returnArray
	 * @todo @param null|array $instanceTemplates - [ "items" => "Show item", etc, ... ]
	 * @return string|array
	 */
	public static function convertArrayToWikiInstances(
		array $templateArr,
		string $targetType,
		string $targetName,
		bool $returnArray = false,
		string $paramSep = " ",
		mixed $instanceTemplates = []
	): string|array {
		$str = $prefix = "";
		$instances = [];
		if ( $targetType === "template" ) {
			$prefix = "{{";
		} elseif ( $targetType === "widget" ) {
			$prefix = "{{#widget:";
		} elseif ( $targetType === "module" ) {
			$prefix = "{{#invoke:";
			$n = explode( "{{!}}", $targetName );
			$targetName = $n[0];
			$functionName = array_key_exists( 1, $n ) ? $n[1] : null;
		} elseif ( $targetType === "subobject" ) {
			// Afterthought: not implemented, but note they're nameless
			$prefix = "{{#subobject:";
		}
		$suffix = "}}";

		foreach ( $templateArr as $instance ) {
			//$instanceStr = $prefix . $targetName . "\n";
			$instanceStr = $prefix . $targetName . $paramSep;
			if ( $targetType === "module" ) {
				$instanceStr .= "|" . $functionName;
			}
			foreach ($instance as $k => $v) {
				$dataType = gettype( $v );
				if( $dataType === "string" || $dataType === "integer" ) {
					$instanceStr .= "|{$k}={$v}" . $paramSep;
				} elseif( $dataType === "array" ) {
					// @todo Handle with care
					// @todo allow for customising separator
					$subStr = self::handleSubArray( $k, $v, ";", $instanceTemplates );
					if ( gettype($subStr ) == "string" ) {
						$instanceStr .= "|{$k}={$subStr}" . $paramSep;
					}
				}
			}
			$instanceStr .= $suffix;
			$str .= $instanceStr;
			$instances[] = $instanceStr;
		}
		return $returnArray ? $instances : $str;
	}

	/**
	 * Helper for convertArrayToWikiInstances
	 * @param mixed $k
	 * @param array $arr
	 * @param string $sep
	 * @param array|null $instanceTemplates
	 * @return array|string
	 */
	private static function handleSubArray( $k, array $arr, $sep = ";", mixed $instanceTemplates = [] ) {
		if ( TFUtils::isAssociativeArray( $arr ) ) {
			$arr = [ $arr ];
		}
		if ( TFUtils::areAllArrayElementsStrings( $arr ) ) {
			return implode( $sep, $arr );
		}
		// Check if a child template was set for parameter name in parent template
		// Scenario: [ [ "foo1" => "bar1", ... ], [ ... ], [ ... ] ]
		if ( $instanceTemplates !== null && in_array( $k, array_keys($instanceTemplates) ) ) {
			$templateName = $instanceTemplates[$k];
			return self::convertArrayToWikiInstances( $arr, "template", $templateName, false, " ", [] );
		}
		return "";
	}

}
