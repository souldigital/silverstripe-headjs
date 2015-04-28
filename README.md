#Silverstripe HeadJS Backend
==================
Use HeadJS for assets loading.

##Compatibility
==================
Tested with Silverstripe 3.1

##Features
==================
- 


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

###Specifying a section
An example of specifying a section for a CSS or JS requirement is below:

```
if(method_exists(Requirements::backend(), "add_to_section")){
    Requirements::backend()->add_to_section("framework/thirdparty/jquery/jquery.js", SectionedHeadJsBackend::SECTION_AFTER_BODY_OPEN);
}
```

##Maintainer
==================
Tim - tim@souldigital.com.au