<?php

/**
* MCSS - Mutable CSS (Make your own CSS)
*
*
*
*
* REGEX for finding imports used from Jon Gilkison's CSS Compiler
*
*/
class MCSS
{
	
	var $path = '';
	var $body = '';
	var $variables = array();
	var $rules = array();
	
	function MCSS($path = false, $cleanUp = true) {
		if( $path ) {
			$this->path = str_replace( basename($path), '', $path );
			$this->body = file_get_contents( $path );
			$this->parseImports();
			$this->parseVariables();
			$this->replaceVariables();
			$this->parseRules();
			$this->replaceRules();
		}
		
		if($cleanUp) {
			$this->cleanUp();
		}
	}
	
	function setPath($path) {
		$this->path = str_replace( basename($path), '', $path );
		$this->body = file_get_contents( $path );
	}
	
	function parseImports() {
		preg_match_all('/\@import\s*(?:url\()*\s*[\'"]([^.]+).mcss[\'"]\s*(?:\)*);\s*/is', $this->body, $matches);
		foreach($matches[1] as $key => $file) {
			$mcss = new MCSS;
			$mcss->setPath($this->path.$file.'.mcss');
			$mcss->parseImports();
			$mcss->parseVariables();
			$mcss->parseRules();
			$this->variables = $mcss->variables;
			$this->rules = $mcss->rules;
			$this->body = str_replace( $matches[0][$key], $mcss->body, $this->body );
		}
	}
	
	function parseVariables() {
		preg_match_all('/\@variables\s*{\s*([^}]*)}\s*/i', $this->body, $matches);
		foreach( $matches[1] as $key => $match ) {
			preg_match_all('/([^:]+):\s*([^;]+);\s*/', $match, $rules);
			foreach( $rules[1] as $key2 => $rule ) {
				$this->variables[ $rule ] = $rules[2][$key2];
			}
			$this->body = str_replace( $matches[0][$key], '', $this->body );
		}
	}
	
	function replaceVariables() {
		preg_match_all('/var\(([^\)]*)\)/', $this->body, $matches);
		foreach( $matches[0] as $key => $match ) {
			$this->body = str_replace( $match, $this->variables[ $matches[1][$key] ], $this->body );
		}
	}
	
	function parseRules() {
		preg_match_all('/@rule\s*([^:]+):?\s*([^{]+){\s*([^}]*)}\s*/', $this->body, $matches);
		foreach( $matches[0] as $key => $match ) {
			$params = explode(' ', trim($matches[2][$key]));
			foreach( $params as $replace => $find ) {
				$matches[3][$key] = str_replace($find, '$'.$replace, $matches[3][$key]);
			}
			$this->rules[ $matches[1][$key] ] = $matches[3][$key];
			$this->body = str_replace($match, '', $this->body);
		}
	}
	
	function replaceRules() {
		foreach( $this->rules as $name => $rule ) {
			preg_match_all('/'.str_replace('-', '\-', $name).'\s*:\s*([^;]+);\s*/', $this->body, $matches);
			foreach($matches[0] as $key => $string) {
				$params = explode(' ', trim($matches[1][$key]));
				$replaces = array();
				foreach($params as $index => $param) {
					$replaces[ '$'.$index ] = $param;
				}
				$this->body = str_replace($string, str_replace( array_keys($replaces), array_values($replaces), $rule ), $this->body);
			}
		}
	}
	
	function cleanUp() {
		$this->body = preg_replace('/\$[0-9]+/', '', $this->body);
	}
	
}

header('Content-Type: text/css');
$mcss = new MCSS('../styles/main.mcss');
echo $mcss->body;

?>