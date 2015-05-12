<?php

	/**
	 * Class SectionedHeadJsBackend
	 *
	 * This backend is more stringent with its positioning of <link> and <script> to comply with the Soul Digital
	 * HeadJS integration
	 *
	 * WARNING: this will _not_ work very well if there are any inline scripts in SS template files.
	 */

	class SectionedHeadJsBackend extends HeadJsBackend {

		public $sections = array();
		public $default;

		const SECTION_BEFORE_HEAD_CLOSE = "before_head_close";
		const SECTION_AFTER_BODY_OPEN = "after_body_open";
		const SECTION_BEFORE_BODY_CLOSE = "before_body_close";


		/**
		 * Needed to actively position the inclusion of a file in sections.
		 * You can just block items with an ID, so make sure you add a unique
		 * identifier to customCSS() and customScript().
		 *
		 * @param string $fileOrID
		 */
		public function add_to_section($fileOrID, int $sectionID) {
			$this->sections[$sectionID][$fileOrID] = $fileOrID;
		}


		public function get_section_id_for_include($fileOrID){
			foreach($this->sections as $sectionID => $section) {
				if(isset($section[$fileOrID])) {
					return $sectionID;
				}
			}
			return $this->get_default_section_id();
		}

		public function move_to_section($fileOrID, $newSectionID){
			if( $sectionID = $this->get_section_id_for_include($fileOrID) ){
				unset($this->sections[$sectionID][$fileOrID]);
			}

			$this->add_to_section($fileOrID, $newSectionID);
		}

		public function get_all_in_section($sectionID){
			if(isset($this->sections[$sectionID]))
				return array_diff_key($this->sections[$sectionID], $this->blocked);
			return false;
		}

		public function get_default_section_id(){
			if($this->default){
				return $this->default;
			}elseif($this->force_js_to_bottom){
				return self::SECTION_BEFORE_BODY_CLOSE;
			}elseif($this->write_js_to_body){
				return self::SECTION_AFTER_BODY_OPEN;
			}
			return self::SECTION_BEFORE_HEAD_CLOSE;
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
			$sectionRequirements = array();

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
						$sectionID = $this->get_section_id_for_include($file);
						$sectionRequirements[$sectionID]["ready"][] = "head.load(\"" . $path . "\"{$callback});\n";
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
							$sectionID = $this->get_section_id_for_include($file);
							$sectionRequirements[$sectionID]["ready"][] = "head.load(\"" . $path . "\"{$callback});\n";
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
				foreach ($customJS as $key => $script) {
					$sectionID = $this->get_section_id_for_include($key);
					if(in_array($script, self::$do_not_wrap)){
						$sectionRequirements[$sectionID]["clean"][] = $script;
					}else{
						$sectionRequirements[$sectionID]["ready"][] = $script;
					}
				}
			}

			foreach (array_diff_key($this->customHeadTags, $this->blocked) as $customHeadTag) {
				$headerRequirements .= "$customHeadTag\n";
			}

			// put the core js just before the </head>
			$content = preg_replace("/(<\/head>)/i", $headerRequirements . "\\1", $content);

			// sections
			foreach($sectionRequirements as $sectionID => $requirements){
				if(empty($requirements) || (empty($requirements["clean"]) && empty($requirements["ready"]))) continue;

				$cleanRequirements = empty($requirements["clean"])?"":implode("",$requirements["clean"]);
				$readyRequirements = empty($requirements["ready"])?"":implode("",$requirements["ready"]);
				$readyRequirements = ($readyRequirements=="")?"":sprintf($ready_template, $readyRequirements);
				$requirements = sprintf($script_template, $readyRequirements.$cleanRequirements);

				switch($sectionID){
					case self::SECTION_BEFORE_HEAD_CLOSE:
						$content = preg_replace("/(<\/head>)/i", $requirements . "\\1", $content);
					break;
					case self::SECTION_AFTER_BODY_OPEN:
						$requirements = preg_replace('/>\n*/', '>', $requirements);
						$p2 = stripos($content, '<body');
						$p1 = stripos($content, '>', $p2) + 1; // after the closing >
						$content = substr($content, 0, $p1) . $requirements . substr($content, $p1);
					break;
					case self::SECTION_BEFORE_BODY_CLOSE:
					default:
						$requirements = preg_replace('/>\n*/', '>', $requirements);
						$content = preg_replace("/(<\/body[^>]*>)/i", $requirements . "\\1", $content);
					break;
				}
			}
			return $content;
		}

	}