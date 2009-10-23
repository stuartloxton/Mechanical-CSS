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
			// Replace multiple white space with one space
			$this->css = preg_replace('/\s+/', ' ', $this->css);

			// Remove first spaces around each block beginning
			$this->css = preg_replace('/\s*{\s*/', '{', $this->css);

			// Remove spaces between key: value
			$this->css = preg_replace('/\s*\:\s*/', ':', $this->css);

			// Remove spaces after and before semi-colons
			$this->css = preg_replace('/\s*;\s*/', ';', $this->css);

			// Remove comments
			$this->css = preg_replace('/\/\*[^\*]*\*\//', '', $this->css);

			// Remove beggining whitespace
			$this->css = preg_replace('/^\s+/', '', $this->css);

			// Remove final semi-colon and space from each block
			$this->css = preg_replace('/;?\s*}\s*/', '}', $this->css);
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