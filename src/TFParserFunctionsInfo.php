<?php

/**
 * Self-documentation for use on Special:TemplateFunc.
 * @todo
 */

namespace TF;

class TFParserFunctionsInfo {

	/**
	 * Get descriptions of #tfconvert's parameters
	 * @return array{name: string, parameters: array}
	 */
	public static function getTFConvertInfo() {
		$name = "tf-convert";
		$parameters = [
			"page" => [
				"description" => "Required. The full name of the wiki page to serve as the data source. Supports use of the magic word <code>{{</code><code>FULLPAGENAME</code><code>}}</code>.",
				"required" => true
			],
			"slot" => [
				"description" => "Name of the content slot role (https://mediawiki.org/wiki/Manual:Slot). Defaults to the main slot.",
				"default" => "main",
				"required" => false
			],
			"sourceformat" => [
				"description" => "The data format of the wiki page: `template`, `json`, or `raw`.",
				"options" => [
					"template" => "Fetch template data from the page, assuming it is structured with a wiki template.",
					"json" => "Fetch the JSON of the page",
					"raw" => "Get the unprocessed source code"
				],
				"default" => "template"
			],
			"sourcetemplate" => [
				"description" => "Used only if `sourceformat=template`. The template containing your data. To select an embedded multiple-instance template, use an expression in the format `ParentTemplate[parametername].ChildTemplate`, for instance `Recipe[Ingredients].Ingredient`, where `Recipe` is the parent template, `Ingredients` the name of the parameter that holds (nested) instances of `Ingredient`, which is the multiple-instance child template."
			],
			"sourcenode" => [
				"description" => "Used only if `sourceformat=json`. The node from which to traverse and get the data. Defaults to the root node.",
			],
			"targettemplate" => [
				"description" => "Name of the wiki template (without the `Template:` namespace prefix) to pass the data to."
			],
			"targetwidget" => [
				"description" => "Name of the Smarty widget (without the `Widget:` namespace prefix) to pass the data to. By widget is meant an instance of the parser function <code>#widget</code> from the Widgets extension (https://www.mediawiki.org/wiki/Extension:Widgets)"
			],
			"targetmodule" => [
				"description" => "Name of the Lua module (without `Module:` namespace prefix) to pass the data to, followed by an escaped pipe symbol (<code>{{</code>!<code>}}</code>) and function name. See https://www.mediawiki.org/wiki/Extension:Scribunto"
			],
			"targetmustache" => [
				"description" => "Name of the Mustache template to pass the data to."
			],
			"targetmustachedir" => [
				"description" => "Optionally (if targetmustache is used), directory on the server containing the Mustache templates. Defaults to the location set in <code>\$wgMustacheTemplatesDir</code>."
			],
			"target" => [
				"description" => "Can be set to `raw` to transfer code verbatim, especially for use in form inputs.",
				"default" => null
			],
			"data" => [
				"description" => "Formula to map the parameter names of the source code to parameters of the target, e.g. `sourceparam1=targetparam1, sourceparam2=targetparam2`. Use `data=all` (default) to map all parameters verbatim.",
				"default" => "all",
				"required" => false
			],
			"indexname" => [
				"description" => "Preferred name of the index parameter for the target.",
				"default" => "index",
				"required" => false
			],
			"userparam*" => [
				"description" => "Optionally, multiple user parameters whose names begin with `userparam` and are followed by a number or string attached to it, e.g. `userparam1`, `userparam2`, `userparamTheme`, etc. Allows you to add custom data.",
				"required" => false
			],
			"mode" => [
				"description" => "Not normally used, but the output can be rendered in alternative formats (raw, pre, lazy).",
				"options" => [
					"normal" => "Default.",
					"raw" => "Get the unparsed content.",
					"pre" => "Get preformatted content between `pre` tags. May be useful for debugging.",
					"lazy" => "Defer generating content until after page load. Experimentally supported if CODECSResources is installed."
				],
				"default" => "normal",
				"required" => false
			],
			"action" => [
				"description" => "Associated with lazy mode, but not implemented.",
				"default" => "replace"
			]
		];
		$res = [
			"name" => $name,
			"parameters" => $parameters
		];
		return $res;
	}

}
