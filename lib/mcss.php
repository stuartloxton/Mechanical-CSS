<?php

/*
 *
 * Mechanical CSS - CSS extensions for variables, mixins, imports and flags.
 * Copyright (C) 2009 Stuart Loxton
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
class MCSS
{
	
	var $path = '';
	var $body = '';
	var $variables = array();
	var $rules = array();
	var $flags = array();
	
	function MCSS($path = false) {
		if( $path ) {
			$this->setPath($path);
			$this->parseImports();
			$this->parseFlags();
			$this->parseVariables();
			$this->replaceVariables();
			$this->parseRules();
			$this->replaceRules();
			$this->cleanUp();
			$this->finalFlags();
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
	
	function parseFlags() {
		preg_match_all('/\@flag\s*"([^"]+)";\s*/', $this->body, $matches);
		foreach($matches[0] as $index => $flag) {
			$this->body = str_replace($flag, '', $this->body);
			$this->flags[ $matches[1][$index] ] = true;
		}
	}
	
	function finalFlags() {
		foreach($this->flags as $flag => $value) {
			$method = 'FLAG_'.$flag;
			if( method_exists($this, $method) ) {
				$this->$method( $value );
			}
		}
	}
	
	function cleanUp() {
		$this->body = preg_replace('/\$[0-9]+/', '', $this->body);
	}
	
	
	
	function FLAG_compress($ignore = true) {
		// Replace multiple white space with one space
		$this->body = preg_replace('/\s+/', ' ', $this->body);
		
		// Remove final semi-colon and space from each block
		$this->body = preg_replace('/;?\s*}\s*/', '}', $this->body);
		
		// Remove first spaces around each block beginning
		$this->body = preg_replace('/\s*{\s*/', '{', $this->body);
		
		// Remove spaces between key: value
		$this->body = preg_replace('/\s*\:\s*/', ':', $this->body);
		
		// Remove spaces after semi-colons
		$this->body = preg_replace('/;\s+/', ';', $this->body);
		
		// Remove comments
		$this->body = preg_replace('/\/\*[^\*]*\*\//', '', $this->body);
		
		// Remove beggining whitespace
		$this->body = preg_replace('/^\s+/', '', $this->body);
	}
	
	
}

if( isset($_GET) && isset($_GET['url']) ) {
	header('Content-Type: text/css');
	$mcss = new MCSS($_GET['url']);
	echo $mcss->body;
} else if( isset($argv) ) {
	if( count($argv) > 1 ) {
		array_shift($argv);
		foreach($argv as $arg) {
			unset($mcss);
			$mcss = new MCSS($arg);
			file_put_contents(str_replace('mcss', 'css', $arg), $mcss->body);
		}
	} else {
		echo 'Nothing to do...'."\n";
	}
}

?>