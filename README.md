#Silverstripe HeadJS Backend
==================
Use HeadJS for assets loading.

##Compatibility
==================
Tested with Silverstripe 3.1

##Features
==================
- Async loading of requirements using the native Requirements engine
- Specific targeting of requirement locations in DOM via SectionedHeadJsBackend
- Critical js/css files to be required in the head while all other requirements are loaded above the closing body 
- Add JS callbacks to requirements
- Add dependencies to JS / CSS files 


##How do I use this thing?
==================

###Setup
If you just want to use the HeadJS integration for your Requirements backend, add the following to your `mysite/_config.php`

`Requirements::set_backend(Injector::inst()->get("HeadJsBackend"));`

If you'd like to use the Sectioned Head JS, add the following to your `mysite/_config.php`

`Requirements::set_backend(Injector::inst()->get("SectionedHeadJsBackend"));`

###Adding a callback
An example of adding a callback to a CSS or JS requirement is below:

```
$custom_js = <<<JS
    (function ($) {
        $(document).ready(function () {
            alert("Loaded!");
        });
    })($);
JS;

Requirements::javascript("framework/thirdparty/jquery/jquery.js");
if(method_exists(Requirements::backend(), "add_callback")){
    Requirements::backend()->add_callback("framework/thirdparty/jquery/jquery.js", $custom_js);
}else{
    Requirements::customScript($custom_js, "loadedalert");
}
```

###Adding a dependency
An example of adding a dependency to a CSS or JS requirement is below:

``` 
Requirements::javascript("framework/thirdparty/jquery/jquery-ui.js");
if(method_exists(Requirements::backend(), "add_dependency")){
    Requirements::backend()->add_dependency("framework/thirdparty/jquery-ui/jquery-ui.js", "framework/thirdparty/jquery/jquery.js");
} 
```

In the example above, we require jQuery UI, but make sure that it is only loaded _after_ jQuery has loaded. Note that we can add multiple dependent files to the jQuery JS file if we want, but there is a limitation around chaining dependencies; i.e. we can only have a single level of dependency - in the example above, we can not add a new file that is dependent on jQuery UI. 


###Specifying a section
An example of specifying a section for a CSS or JS requirement is below:

```
if(method_exists(Requirements::backend(), "add_to_section")){
    Requirements::backend()->add_to_section("framework/thirdparty/jquery/jquery.js", SectionedHeadJsBackend::SECTION_AFTER_BODY_OPEN);
}
```

##Maintainer
Tim - tim@souldigital.com.au

##Original Repo
[silverstripe-headjs](https://github.com/lekoala/silverstripe-headjs) by the awesome [lekoala](https://github.com/lekoala)