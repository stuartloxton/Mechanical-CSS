<?php
class MCSS {
	
	var $css;
	var $path;
	var $variables = array();
	var $rules = array();
	var $flags = array();
		
	var $included = array();
	
	function MCSS($file, $path=false, $autorun=true) {
			
		if(!$path) {
			$path = dirname($file);
			$file = basename($file);
		}
		
		$filepath = $path ? $path.'/'.$file : $file;
		
		$this->css = file_get_contents($filepath);
		$this->path = $path;
		
		if($autorun) {
			$this->parseIncludes();
			
			$this->parseRules();
			$this->replaceRules();
			
			$this->parseVariables();
			$this->css = $this->replaceVariables($this->css, $this->variables);
			
			$this->parseFlags();
			$this->runFlags();
		}
	}
	
	/*************
		Variable Functions
	*************/
	function parseVariables() {
		$blocks = $this->_parseBlocks('@variables');
		foreach($blocks as $block) {
			$rules = $this->_parseRules($block['body']);
			foreach($rules as $key => $value) {
				$this->variables[$key] = $value;
			}
		}
	}
	
	function replaceVariables($body, $variables) {
		preg_match_all('/var\(([^\)]+)\)/', $body, $matches);
		foreach($matches[0] as $key => $rule) {
			$body = str_replace($rule, $variables[$matches[1][$key]], $body);
		}
		return $body;
	}
	
	/*************
		Rule Functions
	*************/
	function parseRules() {
		$blocks = $this->_parseBlocks('@rule');
		foreach($blocks as $block) {
			$rule = substr($block['parameters'][0], 0, -1);
			array_shift($block['parameters']);
			$this->rules[$rule] = array(
					'parameters' => $block['parameters'],
					'body' => $block['body']
				);
		}
	}
	
	function replaceRules() {
		foreach($this->rules as $rule => $data) {
			$regex = '/'.str_replace('-', '\-', $rule).':\s*([^;]+);/';
			preg_match_all($regex, $this->css, $matches);
			foreach($matches[0] as $key => $match) {
				$arguments = preg_split('/\s+/', trim($matches[1][$key]));
				$params = array();
				foreach($data['parameters'] as $key => $name) {
					$params[$name] = $arguments[$key];
				}
				$this->css = str_replace( $match, $this->replaceVariables($data['body'], $params), $this->css );
			}
		}
	}
	
	/*************
		Flag Functions
	*************/
	function parseFlags() {
		$blocks = $this->_parseBlocks('@flags');
		foreach($blocks as $block) {
			$rules = $this->_parseRules($block['body']);
			foreach($rules as $key => $val) {
				$this->flags[$key] = $val;
			}
		}
	}
	
	function runFlags() {
		foreach($this->flags as $flag => $value) {
			if(method_exists($this, $flag)) {
				$this->$flag($value);
			}
		}
	}
	
	function compress($value) {
		if( $value == 'true' ) {			
			$this->css = $this->compressCSS($this->css, 0, false);
		}
	}
	
	function compressCSS($css, $oldlength=0, $skip=true) {
		// Replace multiple white space with one space
		$css = preg_replace('/\s+/', ' ', $css);

		// Remove first spaces around each block beginning
		$css = preg_replace('/\s*{\s*/', '{', $css);

		// Remove spaces between key: value
		$css = preg_replace('/\s*\:\s*/', ':', $css);

		// Remove spaces after and before semi-colons
		$css = preg_replace('/\s*;\s*/', ';', $css);

		// Remove comments
		$css = preg_replace('/\/\*[^\*]*\*\//', '', $css);

		// Remove beggining whitespace
		$css = preg_replace('/^\s+/', '', $css);

		// Remove final semi-colon and space from each block
		$css = preg_replace('/;?\s*}\s*/', '}', $css);
		
		// Remove spaces inbetween selectors
		$css = preg_replace('/,\s*/', ',', $css);
		
		
		
		// Compress colours
		$css = preg_replace(array('/#F00/i', '/#FF0000/i'), 'red', $css);
		$css = preg_replace('/#([a0z0-9])\1([a0z0-9])\2([a0z0-9])\3/', '#$1$2$3', $css);


		// Remove measurements from 0
		$css = preg_replace('/([^0-9])0(em|px|\%)/', '$1 0', $css);
		
		// Change CSS values for font-weight
		$css = preg_replace('/(font\-)(weight)?\:bold/', '$1$2:700', $css);
		$css = preg_replace('/(font)(\-weight)?\:normal/', '$1$2:400', $css);
		

		// Remove empty blocks
		$css = '}'.$css;
		$css = preg_replace('/\}([^\{]*)\{\s*\}/', '}', $css);
		$css = substr($css, 1);
		
		// Compress margin and padding rules
		preg_match_all('/(margin|padding)\:([^;\}]+)/', $css, $matches);
		foreach($matches[0] as $key => $string) {
			$params = explode(' ', $matches[2][$key]);
			if(count($params) == 4) {
				if( $params[1] == $params[3]) {					
					$css = str_replace($string, $matches[1][$key].':'.$params[0].' '.$params[1].' '.$params[2], $css);
				}
			} else if(count($params) == 3) {
				if($params[0] == $params[2]) {
					$css = str_replace($string, $matches[1][$key].':'.$params[0].' '.$params[1], $css);
				}
			} else if(count($params) == 2) {
				if($params[0] == $params[1]) {
					$css = str_replace($string, $matches[1][$key].':'.$params[0], $css);
				}
			}
		}
		
		// Join identical selector blocks together (only works on single selector queries)
		$css = '}'.$css;
		preg_match_all('/\}([^\{]+)\{([^\}]+)\}/', $css, $matches);
		$selectors = array();
		foreach($matches[1] as $index => $selector) {
			$hash = md5($selector); // Make selector safe for PHP array key
			if(!isset($selectors[$hash])) {
				$selectors[$hash] = array(
					'selector' => $selector,
					'blocks' => array()
				);
			}
			$selectors[$hash]['blocks'][] = $matches[2][$index];
		}
		
		foreach($selectors as $hash => $selector) {
			if(count($selector['blocks']) > 1) {
				$block = implode(';', $selector['blocks']);
				for($i = 0; $i < (count($selector['blocks']) - 1); $i++) {
					$css = str_replace($selector['selector'].'{'.$selector['blocks'][$i].'}', '', $css);
				}
				$css = str_replace($selector['selector'].'{'.$selector['blocks'][$i].'}', $selector['selector'].'{'.$block.'}', $css);
			}
		}
		$css = substr($css, 1);
		
		
		
		// Remove duplicates of same rule in same block definition
		$css = '}'.$css;
		preg_match_all('/\}([^\{]+)\{([^\}]+)\}/', $css, $matches);
		foreach($matches[2] as $index => $block) {
			$rules = explode(';', $block);
			$freshrules = array();
			foreach($rules as $rule) {
				preg_match('/([^:]+):(.+)/', $rule, $match);
				$freshrules[$match[1]] = $match[2];
			}
			$rules = array();
			foreach($freshrules as $key => $val) {
				$rules[] = $key.':'.$val;
			}			
			$css = str_replace($block, implode(';', $rules), $css);
		}
		$css = substr($css, 1);
		
		
		
		// Check if any blocks are identical
		$blocks = array();
		foreach($matches[2] as $index => $block) {
			$hash = strlen($block).'_'.md5($block);
			if(!isset($blocks[$hash])) {
				$blocks[$hash] = array(
					'block' => $block,
					'selectors' => array()
				);
			}
			$blocks[$hash]['selectors'][] = $matches[1][$index];
		}
		foreach($blocks as $hash => $block) {
			if(count($block['selectors']) > 1) {
				$bigselector = implode(',', $block['selectors']);
				for($i = 0; $i < (count($block['selectors']) - 1); $i++) {
					$css = str_replace($block['selectors'][$i].'{'.$block['block'].'}', '', $css);
				}
				$css = str_replace($block['selectors'][$i].'{'.$block['block'].'}', $bigselector.'{'.$block['block'].'}', $css);
			}
		}
		
		
		$css = trim($css);
		if($oldlength != strlen($css)) {
			return $this->compressCSS($css, strlen($css));
		} else {
			return $css;
		}
	}
	
	/*************
		Include Functions
	*************/
	function parseIncludes() {
		$rules = $this->_parseDirectives($this->css, '@include');
		foreach($rules as $include) {
			preg_match('/^[\'"]([^\'"]+)/', $include, $matches);
					
			if( strpos($matches[1], '://') !== false ) {
				if( !in_array($matches[1], $this->included) ) {
					$subcss = new MCSS($matches[1], false, false);
					$subcss->parseIncludes();
					$this->included[] = $matches[1];
				}
			} else {
				$path = $this->path.'/'.dirname($matches[1]);
				$file = basename($matches[1]);
				if(!in_array($path.'/'.$file, $this->included)) {
					$subcss = new MCSS($file, $path, false);
					$subcss->parseIncludes();
					$this->included[] = $path.'/'.$file;
				}
			}
			if( isset($subcss) ) {
				$this->css = substr_replace($this->css, $subcss->css, strpos($this->css, '@include '.$include.';'), strlen('@include '.$include.';'));
			}
			$this->css = str_replace('@include '.$include.';', '',  $this->css);
			unset($subcss);
		}
	}
	
	/*************
		Internal Functions
	*************/
	function _parseBlocks($type, $remove=true) {
		$regex = '/' . $type . '\s*([^\{]*)\{([^\}]+)\}/';
		preg_match_all($regex, $this->css, $matches);		
		$blocks = array();
		
		foreach($matches[0] as $key => $content) {
			$params = explode(' ', $matches[1][$key]);
			$cleanedParams = array();
			foreach($params as $param) {
				if(trim($param) != '') $cleanedParams[] = trim($param);
			}
			$blocks[] = array(
					'parameters' => $cleanedParams,
					'body' => trim($matches[2][$key]),
					'block' => $content
				);
			if($remove) $this->css = str_replace($content, '', $this->css);
		}
		
		return $blocks;
	}
	
	function _parseRules($body) {
		preg_match_all('/([^:]+):\s*([^;]+);\s*/', $body, $rules);
		$return = array();
		foreach($rules[1] as $i => $key) {
			$return[$key] = $rules[2][$i];
		}
		return $return;
	}
	
	function _parseDirectives($body, $directive) {
		preg_match_all('/('.$directive.')\s*([^;]+);\s*/', $body, $rules);
		$return = array();
		foreach($rules[1] as $i => $key) {
			$return[] = $rules[2][$i];
		}
		return $return;
	}
	
}

?>