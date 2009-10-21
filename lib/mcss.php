<?php
class MCSS {
	
	var $css;
	var $path;
	var $variables = array();
	var $rules = array();
	var $flags = array();
	
	function MCSS($file, $path=false) {
		
		$filepath = $path.'/'.$file;
		$this->css = file_get_contents($filepath);
		$this->path = $path;
		
		$this->parseRules();
		$this->replaceRules();
		
		$this->parseVariables();
		$this->css = $this->replaceVariables($this->css, $this->variables);
		
		$this->parseFlags();
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
	
	function _parseDirective($type, $body) {
		echo $body;
	}
	
	function _parseRules($body) {
		preg_match_all('/([^:]+):\s*([^;]+);\s*/', $body, $rules);
		$return = array();
		foreach($rules[1] as $i => $key) {
			$return[$key] = $rules[2][$i];
		}
		return $return;
	}
	
}

?>