<?php

class HeadJsBackend extends Requirements_Backend {

	public $write_js_to_body = false;
	
	public static $do_not_wrap = array();

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
	 * Get the CDN source for headjs
	 * @return string
	 */
	public static function getCdnSource() {
		return Config::inst()->get('HeadJsBackend','cdnSource');
	}

	/**
	 * Get the local filesystem source
	 * @return string
	 */
	public static function getJavascriptSource() {
		return Config::inst()->get('HeadJsBackend','javascriptSource');
	}

	/**
	 * Get the head js url
	 * @return string
	 */
	public static function getHeadJsUrl() {
		if(self::getJavascriptSource()) {
			return self::getJavascriptSource();
		}
		return self::getCdnSource();
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
		if($this->isBackendController()) {
			//currently, it's not loading tinymce otherwise
			return parent::includeInHTML($templateFile, $content);
		}
		$hasHead = (strpos($content, '</head>') !== false || strpos($content, '</head ') !== false);
		$hasRequirements = ($this->css || $this->javascript || $this->customCSS || $this->customScript || $this->customHeadTags);

		if (!$hasHead || !$hasRequirements) {
			return $content;
		}

		$requirements = '';
		$jsRequirements = '';

		// Include HeadJS, the only script in your head
		$jsRequirements .= "<script type=\"text/javascript\" src=\"" . self::getHeadJsUrl() . "\"></script>\n";

		// Combine files - updates $this->javascript and $this->css
		$this->process_combined_files();

		$jsFiles = array_diff_key($this->javascript, $this->blocked);
		if (!empty($jsFiles)) {
			$paths = array();
			foreach ($jsFiles as $file => $dummy) {
				$path = Convert::raw2xml($this->path_for_file($file));
				$path = str_replace('&amp;', '&', $path);
				if ($path) {
					$paths[] = $path;
				}
			}
			if (!empty($paths)) {
				$jsRequirements .= "<script type=\"text/javascript\">\n//<![CDATA[\n";
				$jsRequirements .= "head.load(['".  implode("','", $paths)."']);";
				$jsRequirements .= "\n//]]>\n</script>\n";
			}
		}

		// add all inline javascript *after* including external files which
		// they might rely on
		if ($this->customScript) {
			foreach (array_diff_key($this->customScript, $this->blocked) as $script) {
				$wrap = !in_array($script, self::$do_not_wrap);
				
				$jsRequirements .= "<script type=\"text/javascript\">\n//<![CDATA[\n";
				if($wrap) {
					$jsRequirements .= "head.ready(function() {\n";
				}
				$jsRequirements .= "$script\n";
				if($wrap) {
					$jsRequirements .= "});\n";
				}
				$jsRequirements .= "\n//]]>\n</script>\n";
			}
		}

		foreach (array_diff_key($this->css, $this->blocked) as $file => $params) {
			$path = Convert::raw2xml($this->path_for_file($file));
			if ($path) {
				$media = (isset($params['media']) && !empty($params['media'])) ? " media=\"{$params['media']}\"" : "";
				$requirements .= "<link rel=\"stylesheet\" type=\"text/css\"{$media} href=\"$path\" />\n";
			}
		}

		foreach (array_diff_key($this->customCSS, $this->blocked) as $css) {
			$requirements .= "<style type=\"text/css\">\n$css\n</style>\n";
		}

		foreach (array_diff_key($this->customHeadTags, $this->blocked) as $customHeadTag) {
			$requirements .= "$customHeadTag\n";
		}

		if ($this->force_js_to_bottom) {
			// Remove all newlines from code to preserve layout
			$jsRequirements = preg_replace('/>\n*/', '>', $jsRequirements);

			// We put script tags into the body, for performance.
			// We forcefully put it at the bottom instead of before
			// the first script-tag occurence
			$content = preg_replace("/(<\/body[^>]*>)/i", $jsRequirements . "\\1", $content);

			// Put CSS at the bottom of the head
			$content = preg_replace("/(<\/head>)/i", $requirements . "\\1", $content);
		} elseif ($this->write_js_to_body) {
			// Remove all newlines from code to preserve layout
			$jsRequirements = preg_replace('/>\n*/', '>', $jsRequirements);

			// We put script tags into the body, for performance.
			// If your template already has script tags in the body, then we put our script
			// tags just before those. Otherwise, we put it at the bottom.
			$p2 = stripos($content, '<body');
			$p1 = stripos($content, '<script', $p2);

			if ($p1 !== false) {
				$content = substr($content, 0, $p1) . $jsRequirements . substr($content, $p1);
			} else {
				$content = preg_replace("/(<\/body[^>]*>)/i", $jsRequirements . "\\1", $content);
			}

			// Put CSS at the bottom of the head
			$content = preg_replace("/(<\/head>)/i", $requirements . "\\1", $content);
		} else {
			$content = preg_replace("/(<\/head>)/i", $requirements . "\\1", $content);
			$content = preg_replace("/(<\/head>)/i", $jsRequirements . "\\1", $content);
		}

		return $content;
	}

}
