<?php

class HeadJsBackend extends Requirements_Backend {

	public $write_js_to_body = false;
	public static $do_not_wrap = array();

	/**
	 * Store all relevant callbacks for onLoad of files here
	 * @var array
	 */
	public $callbacks = array();

	/**
	 * Helper method to know if we are in the admin
	 * @return bool
	 */
	public function isBackendController() {
		return is_subclass_of(Controller::curr(), "LeftAndMain");
	}

	/**
	 * Do not wrap in head.ready a given customScript
	 * @param string $code
	 */
	public static function doNotWrap($code) {
		self::$do_not_wrap[] = $code;
	}

	/**
	 * Show the name of files in includes
	 * @return boolean
	 */
	public static function getNamedFiles() {
		return Config::inst()->get('HeadJsBackend', 'named_files');
	}
	
	/**
	 * Get the CDN source for headjs
	 * @return string
	 */
	public static function getCdnSource() {
		return Config::inst()->get('HeadJsBackend', 'cdn_source');
	}

	/**
	 * Get the local filesystem source
	 * @return string
	 */
	public static function getJavascriptSource() {
		return Config::inst()->get('HeadJsBackend', 'javascript_source');
	}

	/**
	 * Get the head js url
	 * @return string
	 */
	public static function getHeadJsUrl() {
		if (self::getJavascriptSource()) {
			return self::getJavascriptSource();
		}
		return self::getCdnSource();
	}

	/*
	 * Add a callback to the file load after HeadJS has load()'ed the file
	 * @param string $fileOrID
	 */
	public function add_callback($fileOrID, $callback) {
		$this->callbacks[$fileOrID] = $callback;
	}

	public function remove_callback($fileOrID){
		if(isset($this->callbacks[$fileOrID]))
			unset($this->callbacks[$fileOrID]);
	}

	public function get_callback($fileOrID){
		if(isset($this->callbacks[$fileOrID]))
			return $this->callbacks[$fileOrID];
		return false;
	}

	/**
	 * Update the given HTML content with the appropriate include tags for the registered
	 * requirements. Needs to receive a valid HTML/XHTML template in the $content parameter,
	 * including a <head> tag. The requirements will insert before the closing <head> tag automatically.
	 *
	 * @todo Calculate $prefix properly
	 *
	 * @param string $templateFilePath Absolute path for the *.ss template file
	 * @param string $content HTML content that has already been parsed from the $templateFilePath through {@link SSViewer}.
	 * @return string HTML content thats augumented with the requirements before the closing <head> tag.
	 */
	function includeInHTML($templateFile, $content) {
		if ($this->isBackendController()) {
			//currently, it's not loading tinymce otherwise
			return parent::includeInHTML($templateFile, $content);
		}
		$hasHead = (strpos($content, '</head>') !== false || strpos($content, '</head ') !== false);
		$hasRequirements = ($this->css || $this->javascript || $this->customCSS || $this->customScript || $this->customHeadTags);

		if (!$hasHead || !$hasRequirements) {
			return $content;
		}

		$script_template = "<script type=\"text/javascript\">\n//<![CDATA[\n". "%s" . "\n//]]>\n</script>\n";
		$ready_template = "head.ready(function() {\n" . "%s" . "\n});\n";
		$readyRequirements = $cleanRequirements = '';

		// Include HeadJS, the only script in your head
		$headerRequirements = "<script type=\"text/javascript\" src=\"" . self::getHeadJsUrl() . "\"></script>\n";

		// Combine files - updates $this->javascript and $this->css
		$this->process_combined_files();

		$named_files = self::getNamedFiles();
		$jsFiles = array_diff_key($this->javascript, $this->blocked);
		if (!empty($jsFiles)) {
			foreach ($jsFiles as $file => $dummy) {
				$name = str_replace('-','',basename($file, '.js'));
				$path = Convert::raw2xml($this->path_for_file($file));
				$path = str_replace('&amp;', '&', $path);
				if ($path) {
					if($named_files) {
						$path = $name . '":"' . $path;
					}
					$callback = (($this->get_callback($file))?", function(){".$this->get_callback($file)."}":"");
					$readyRequirements .= "head.load(\"" . $path . "\"{$callback});\n";
				}
			}
		}

		// add the css requirements, even allow a callback
		$cssFiles = array_diff_key($this->css, $this->blocked);
		if (!empty($cssFiles)) {
			foreach ($cssFiles as $file => $params) {
				$name = str_replace('-','',basename($file, '.css'));
				$path = Convert::raw2xml($this->path_for_file($file));
				if ($path) {
					if((isset($params['media']) && !empty($params['media']))){
						$headerRequirements .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"{$params['media']}\" href=\"$path\" />\n";
					}else{
						$path = str_replace('&amp;', '&', $path);
						if($named_files) {
							$path = $name . '":"' . $path;
						}
						$callback = (($this->get_callback($file))?", function(){".$this->get_callback($file)."}":"");
						$readyRequirements .= "head.load(\"" . $path . "\"{$callback});\n";
					}
				}
			}
		}

		// @todo: store all css files in arrays with media param as the key, then do a headjs test for each mediaparam

		// store all "custom" css in the header, because this may have critical styling to fix FOUC issues
		$customCSS = array_diff_key($this->customCSS, $this->blocked);
		if (!empty($customCSS)) {
			foreach ($customCSS as $css) {
				$headerRequirements .= "<style type=\"text/css\">\n$css\n</style>\n";
			}
		}


		// add all inline javascript *after* including external files which
		// they might rely on
		$customJS = array_diff_key($this->customScript, $this->blocked);
		if (!empty($customJS)) {
			foreach ($customJS as $script) {
				if(in_array($script, self::$do_not_wrap)){
					$cleanRequirements .= $script;
				}else{
					$readyRequirements .= $script;
				}
			}
		}

		foreach (array_diff_key($this->customHeadTags, $this->blocked) as $customHeadTag) {
			$headerRequirements .= "$customHeadTag\n";
		}

		// put the core js just before the </head>
		$content = preg_replace("/(<\/head>)/i", $headerRequirements . "\\1", $content);

		$readyRequirements = ($readyRequirements=="")?"":sprintf($ready_template, $readyRequirements);
		$readyRequirements = sprintf($script_template, $readyRequirements.$cleanRequirements);

		if ($this->force_js_to_bottom) {
			// Remove all newlines from code to preserve layout
			$readyRequirements = preg_replace('/>\n*/', '>', $readyRequirements);

			// We put script tags into the body, for performance.
			// We forcefully put it at the bottom instead of before
			// the first script-tag occurence
			$content = preg_replace("/(<\/body[^>]*>)/i", $readyRequirements . "\\1", $content);

		} elseif ($this->write_js_to_body) {
			// Remove all newlines from code to preserve layout
			$readyRequirements = preg_replace('/>\n*/', '>', $readyRequirements);

			// We put script tags into the body, for performance.
			// If your template already has script tags in the body, then we put our script
			// tags just before those. Otherwise, we put it at the bottom.
			$p2 = stripos($content, '<body');
			$p1 = stripos($content, '<script', $p2);

			// @todo - should we move the inline script tags below everything else?
			// we could do this with regex, or a while loop...
			if ($p1 !== false) {
				$content = substr($content, 0, $p1) . $readyRequirements . substr($content, $p1);
			} else {
				$content = preg_replace("/(<\/body[^>]*>)/i", $readyRequirements . "\\1", $content);
			}
		} else {
			$content = preg_replace("/(<\/head>)/i", $readyRequirements . "\\1", $content);
		}

		return $content;
	}



}
