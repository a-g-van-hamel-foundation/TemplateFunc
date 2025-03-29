<?php

/**
 * Methods used for the `#tf-convert` parser function
 */

namespace TF;

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
		array $userParams = []
	): mixed {
		set_time_limit( 10 );
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
				$str = self::convertArrayToWikiInstances( $newInstances, $targetType, $targetName, false, " " );
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
		array|null $templateArr,
		array|null $dataArr,
		string|null $fullpagename = "",
		string|null $indexName = "index",
		array|null $userParams = []
	): array {
		$newInstances = [];
		foreach( $templateArr as $i => $instance ) {
			set_time_limit( 10 );
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
		array|null $templateArr,
		array|null $dataArr,
		string|null $fullpagename = "",
		string|null $indexName = "",
		array|null $userParams = []
	): array {
		$newInstances = [];
		foreach ( $templateArr as $i => $instance ) {
			set_time_limit( 10 );
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
	 * @param array $templateArr
	 * @param string $templateName
	 * @param bool $returnArray
	 * @return string|array
	 */
	public static function convertArrayToWikiInstances(
		array $templateArr,
		string $targetType,
		string $targetName,
		bool $returnArray = false,
		string $paramSep = " "
	): string|array {
		$str = $prefix = "";
		$instances = [];
		if ( $targetType == "template" ) {
			$prefix = "{{";
		} elseif ( $targetType == "widget" ) {
			$prefix = "{{#widget:";
		} elseif ( $targetType == "module" ) {
			$prefix = "{{#invoke:";
		} elseif ( $targetType == "subobject" ) {
			// Afterthought: not implemented, but note they're nameless
			$prefix = "{{#subobject:";
		}
		$suffix = "}}";
		foreach ($templateArr as $instance) {
			set_time_limit( 10 );
			//$instanceStr = $prefix . $targetName . "\n";
			$instanceStr = $prefix . $targetName . $paramSep;
			foreach ($instance as $k => $v) {
				//$instanceStr .= "|{$k}={$v}" . "\n";
				$instanceStr .= "|{$k}={$v}" . $paramSep;
			}
			$instanceStr .= $suffix;
			$str .= $instanceStr;
			$instances[] = $instanceStr;
		}
		return $returnArray ? $instances : $str;
	}

}
