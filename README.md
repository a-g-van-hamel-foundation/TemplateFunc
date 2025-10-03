<h1>TemplateFunc extension for MediaWiki</h1>

The TemplateFunc extension offers parser functions for fetching structured data from wiki pages and converting them to other data formats for use in the wiki, including wiki template markup, JSON, Mustache templates and Smarty widgets. It was specifically created to work with multiple-instance templates and to offer solutions to issues that are expected to arise in the future as Parsoid takes over as the de facto MediaWiki parser. It may be useful, too, if the use of Lua/Scribunto is not a viable option on your wiki.

The extension enables two new parser functions:
* <code>#tf-convert</code> - the main parser function, which allows you to read a template's data on a wiki page and re-assign those data to a different format, such as a different wiki template, Smarty widget or JSON representation.
* <code>#tf-mustache</code> - a dedicated parser function for converting template data to Mustache templates.

<h2>Parser function <code>#tf-convert</code> </h2>
To fetch and move around structured data, this parser function goes through the following steps. It reads data from the intended wiki page, provided that it holds data using either a wiki template or a JSON string Internally, it transforms the data to an array in PHP. Finally, it assigns data, optionally along with custom data, to one of the supported target formats below. In addition, this parser function also lets you fetch the raw, unprocessed content of a wiki page.

<h3>Formats</h3>
* Source formats: wiki template structure, JSON
* Target formats: wiki template structure, Smarty widget (Widget extension), Mustache, JSON (representation only), unparsed content

<h3>Parameters</h3>
<strong>page</strong>

<p>Required. The full name of the wiki page to serve as the data source. Supports use of the magic word <code>{{</code><code>FULLPAGENAME</code><code>}}</code>.</p>

<strong>slot</strong> (default: <code>main</code>)<p>Name of the content slot role (https://mediawiki.org/wiki/Manual:Slot). Defaults to the main slot.</p>

<strong>sourceformat</strong> (default: <code>template</code>)<p>The data format of the wiki page: `template`, `json`, or `raw`.</p><p><code>template</code>: Fetch template data from the page, assuming it is structured with a wiki template. <br><code>json</code>: Fetch the JSON of the page <br><code>raw</code>: Get the unprocessed source code <br></p>

<strong>sourcetemplate</strong><p>Used only if `sourceformat=template`. The template containing your data. To select an embedded multiple-instance template, use an expression in the format `ParentTemplate[parametername].ChildTemplate`, for instance `Recipe[Ingredients].Ingredient`, where `Recipe` is the parent template, `Ingredients` the name of the parameter that holds (nested) instances of `Ingredient`, which is the multiple-instance child template.</p>

<strong>sourcenode</strong><p>Used only if `sourceformat=json`. The node from which to traverse and get the data. Defaults to the root node.</p>

As for the target output, you can choose between <code>targettemplate</code>, <code>targetwidget</code>, <code>targetmustache</code> and <code>targetmodule</code>. If you omit any of these, a JSON representation of the data will be given instead.

<strong>targettemplate</strong>
<p>Name of the wiki template (without the `Template:` namespace prefix) to pass the data to.</p>

<strong>targetinstancetemplates</strong>
<p>One or multiple mappings for multiple-instance templates nested within their own template instance; in the format 'param1=templatename2,param2=templatename3'. Since v0.7. This can be used for instance, when you have saved JSON data with FlexForm's 'instance' feature.</p>

<strong>targetwidget</strong><p>Name of the Smarty widget (without the `Widget:` namespace prefix) to pass the data to. By widget is meant an instance of the parser function <code>#widget</code> from the Widget extension (https://www.mediawiki.org/wiki/Extension:Widgets)</p>

<strong>targetmodule</strong><p>Name of the Lua module (without `Module:` namespace prefix) to pass the data to, followed by an escaped pipe symbol (<code>{{</code>!<code>}}</code>) and function name. See https://www.mediawiki.org/wiki/Extension:Scribunto</p>

<strong>targetmustache</strong>
<p>Name of the Mustache template to pass the data to.</p>

<strong>targetmustachedir</strong>
<p>Optionally (if `targetmustache` is used), directory on the server containing the Mustache templates. Defaults to the location set in <code>$wgMustacheTemplatesDir</code>.</p>

<strong>target</strong>
<p>Can be set to `raw` to transfer code verbatim, especially for use in form inputs.</p>

<strong>data</strong> (default: <code>all</code>)<p>Formula to map the parameter names of the source code to parameters of the target, e.g. `sourceparam1=targetparam1, sourceparam2=targetparam2`. Use `data=all` (default) to map all parameters verbatim.</p>

<strong>indexname</strong> (default: <code>index</code>)
<p>Preferred name of the index parameter for the target.</p>

<strong>userparam*</strong><p>Optionally, multiple user parameters whose names begin with `userparam` and are followed by a number or string attached to it, e.g. `userparam1`, `userparam2`, `userparamTheme`, etc. Allows you to add custom data.</p>

<strong>mode</strong> (default: <code>normal</code>)
<p>Not normally used, but the output can be rendered in alternative formats (raw, pre, lazy).</p><p><code>normal</code>: Default. <br><code>raw</code>: Get the unparsed content. <br><code>pre</code>: Get preformatted content between `pre` tags. May be useful for debugging. <br><code>lazy</code>: Defer generating content until after page load. Experimentally supported if CODECSResources is installed. <br></p>

<strong>action</strong> (default: <code>replace</code>)<p>Associated with lazy mode, but not implemented.</p>

<h2>An example</h2>

Imagine you are using multiple-instance templates to do two things at the same time: store data in Semantic MediaWiki's <code>#subobject</code> notation as well as present those data in a helpful, human-readable format. In addition to user-provided information, you want each template (and subobject) to use information retrieved from the page as well as an index number, or counter, that allows you to keep track of their order of appearance. The traditional solution may have been to let each instance of a template query for additional data (e.g. the name of page as in `{{#show:{{FULLPAGENAME}}|?Has name}}`, or rely on the availability of a variable defined earlier on the page. In order to let an index number increment each time a new template instance comes along, the Variables extension used to come in handy.

Unfortunately, there are problems with this approach. First, the more numerous the template instances that are piling up, the less efficient it gets, because every single instance needs to repeat the same queries for itself. Second, relying on extensions like Variables, for the purposes illustrated, will eventually run counter with Parsoid’s move towards 'parallelised parsing' (more on that on mediawiki.org). In view of this, our use case may be particularly prone to obsolescence because a variable defined in a parent template needs to be made available to a nested template, where a new value is defined and passed on to the next in line, and so on. Parsoid would not approve.

The approach taken by this extension is to treat the raw source code of a template instance on a page as a simple data carrier. Through a parser function (<code>#tf-convert</code>) on the page, instances of this template are read and their data can be re-assigned to a new template, or a different target format, anywhere on the page - or even a different page if necessary. Because this involves an intermediate step, it allows for adding other data to the mix: you can now provide the result of a single query to each instance, without having to play back the same query repeatedly, and we have the opportunity to let you add a counter.

As a result, the target template will receive data for the following parameters:
- All the user-provided parameters according to the key mappings specified in <code>data</code> (use "all" to adopt the original parameter names).
- Additional parameters:
    - <code>index</code>, or the preferred name you've given to <code>indexname</code> - incremental counter, beginning with 1.
    - <code>fullpagename</code> - equals the output of the FULLPAGENAME magic word.
    - optionally, any number of parameters whose names begin with <code>userparam</code> (e.g. <code>userparam1</code>, <code>userparamTheme</code>, etc.) - may be used for adding custom data, such as the name of the page in our example. They can also be used to influence the behaviour of the target (template, module, etc.), which can be helpful if it should be flexible and adaptable to the different contexts in which it may be used and re-used.

How to use the source template and its target template is up to you. You could use the former for presentation and the latter for storing subobjects. You can also use the source template as a data container only, without any output, and use the target template as your vehicle for both presentation and data storage.

<h2>Mustache</h2>
Mustache templates are just files stored on the server rather than wiki pages. If you use them, it is recommended to reserve a dedicated location for them and override the default value of the config setting <code>$wgMustacheTemplatesDir</code> ("/extensions/TemplateFunc/src/templates"). This setting can be overridden directly by setting <code>targetmustachedir</code> (in <code>#tf-convert</code>) or <code>templatedir</code> (in <code>#tf-mustache</code>) to a different location.

There are two parser functions available for working with Mustache HTML templates:
* <code>#tf-convert</code>, which lets you reuse data sources, as seen above
* <code>#tf-mustache</code>, which lets you assign data directly:

<pre>
{{#tf-mustache:template=my-mustache-template
|templatedir=optionally, an alternative directory
|my-param-1=...
|my-param-2=...
|ingredients={{#arraymap:{{{Ingredients|}}}|;|xxx|xxx}}
}}
</pre>

Leave out the file extension (<code>.mustache</code>) from the template name.

<h2>Further information</h2>
https://codecs.vanhamel.nl/Show:Lab/TemplateFunc - project notes with some further examples

<h2>Version history</h2>

- 0.4. First steps to make the extension compatible with later versions of MW.
- 0.3. Fixed support for Lua modules using `modulename{{!}}functionname` notation. Removed stray lines intended for development only. Minor code cleanup.
- 0.2. Added 'targetinstancetemplates' parameter for converting nested arrays to multiple-instance templates as children of the parent template. Supports 'lazy' mode if the ParseRequest extension is installed. Some code cleanup and minor fixes.
- 0.1. First public release (March 2025).
- 0.1-beta (2023/24). Replaced my own methods for detecting templates with Marijn van Wezel's WSRecursiveParser (now https://github.com/WikibaseSolutions/mediawiki-template-parser), which is doing much the same thing and covers some edge cases that had not been on my mind, such as wiki table syntax.
