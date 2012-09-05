<?
ini_set('xdebug.var_display_max_depth', '10');
/**
 * A custom JSDoc parser that extracts what we need to generate automatic
 * documentation pages.
 *
 * Written to be compatible with the JSDoc comments and code style in the
 * Isogenic Game Engine 1.1.x and may not work with other JSDoc commented
 * files. IGE 1.1.0 uses a class-based pattern and standard JSDoc parsers
 * seem to have a problem understanding them so this was written. It is
 * probably not the fastest, cleanest way to achieve the required result
 * but it *works* - that's all that matters here!
 *
 * @author Rob Evans (rob@irrelon.com)
 * @param $path {String} The path to the file to parse.
 * @return {Array} A multi-dimension associative array parser result data.
 */
function parseFile($path) {
	// Read javascript file contents
	$code = file_get_contents($path);

	// Check we got some code!
	if ($code) {
		// Get all comments and the code beneath them
		preg_match_all("/\/\*\*([\s\S]*?)\*\/([\s\S]*?){/", $code, $matches);
		foreach ($matches[1] as $key => $value) {
			unset($item);
			unset($parameters);
			$codeLine = $matches[2][$key];

			echo $codeLine . '<BR>';
			if (strstr($codeLine, 'var IgeClass = (function () ')) {
				echo 'LINE -------------------------------------------';
				$codeLine = str_replace('var IgeClass = (function () ', 'var IgeClass = none.extend(', $codeLine);
			}

			//echo "<B>Comment</B>: " . var_dump($value) . '<BR>';
			//echo "<B>Code</B>:" . $matches[2][$key] . '<BR><BR>';

			// Get the description from the comment
			preg_match_all("/\*\s(.*?)\n/", $value, $descriptionLines);

			// Strip and concat the description lines
			$description = '';
			foreach ($descriptionLines[1] as $descripKey => $descripText) {
				// Continue adding lines until we reach an @ marker
				if (substr($descripText, 0, 1) === '@') {
					break;
				}
				$description .= trim($descripText) . ' ';
			}
			$description = trim($description);

			// Grab the parameter markers
			reset($descriptionLines);
			$paramText = '';
			$parametersArray = Array();
			$privateMethod = false;
			$constructorMethod = false;
			// TODO: Handle @return markers as well!
			foreach ($descriptionLines[1] as $descripKey => $descripText) {
				if (substr($descripText, 0, 1) === '@') {
					if (substr($descripText, 0, 6) === '@param') {
						if (trim($paramText)) {
							// We have an existing parameter's data, store it
							$parametersArray[] = trim($paramText);

							// Now clear the text ready for the next parameter
							$paramText = '';
						}
						$paramText .= $descripText;
					}

					if (substr($descripText, 0, 8) === '@private') {
						$privateMethod = true;
					}

					if (substr($descripText, 0, 12) === '@constructor') {
						$constructorMethod = true;
					}
				} else {
					if ($paramText) {
						$paramText .= $descripText . ' ';
					}
				}
			}

			if (trim($paramText)) {
				$parametersArray[] = trim($paramText);
			}

			// Extract type, name and description from parameter line
			foreach ($parametersArray as $paramKey => $paramVal) {
				// Check for type
				preg_match("/\{(.*?)\}/", $paramVal, $paramType);
				unset($param);
				$param['type'] = $paramType[1];
				$param['optional'] = false;
				$param['name'] = '';
				$param['desc'] = '';

				if ($param['type']) {
					// Check if the parameter is optional
					if (substr($param['type'], strlen($param['type']) - 1, 1) === '=') {
						// The parameter is optional, remove the = from the type name
						$param['type'] = substr($param['type'], 0, strlen($param['type']) - 1);
						$param['optional'] = true;
					} else {
						$param['optional'] = false;
					}

					// Extract the parameter name
					preg_match("/\}\s(.*?)$/", $paramVal, $paramLine);

					// Check if more than one word exists in the line
					if (strstr($paramLine[1], ' ')) {
						// Split the text by space
						$textSplit = explode(' ', $paramLine[1]);
						$param['name'] = trim($textSplit[0], ' ');

						foreach ($textSplit as $paramDescKey => $paramDescVal) {
							if ($paramDescKey !== 0) {
								$param['desc'] .= $paramDescVal . ' ';
							}
						}

						$param['desc'] = trim($param['desc']);
					} else {
						$param['name'] = $paramLine[1];
					}
				} else {
					// Extract the parameter name
					preg_match("/\s(.*?)$/", $paramVal, $paramLine);

					// Check if more than one word exists in the line
					if (strstr($paramLine[1], ' ')) {
						// Split the text by space
						$textSplit = explode(' ', $paramLine[1]);
						$param['name'] = trim($textSplit[0], ' ');

						foreach ($textSplit as $paramDescKey => $paramDescVal) {
							if ($paramDescKey !== 0) {
								$param['desc'] .= $paramDescVal . ' ';
							}
						}

						$param['desc'] = trim($param['desc']);
					} else {
						$param['name'] = $paramLine[1];
					}
				}

				/*echo "Parameter <B>" . $param['name'] . "</B> {" . $param['type'] . "}";
				if ($param['optional']) { echo " (*Optional*)"; }
				if ($param['desc']) { echo " " . $param['desc']; }
				echo "<BR>";*/
				$parameters[] = $param;
			}

			// Check if the code is a class that is extending another
			preg_match_all("/=\s(.*?)\.extend/", $codeLine, $extendedClass);
			$itemType = '';

			if ($extendedClass[1]) {
				$extendedClass = $extendedClass[1][0];
				$itemType = 'class';

				// Extract the class name
				preg_match("/(.*?)\s=/", $codeLine, $classNameArr);
				$className = trim(str_replace('var ', '', $classNameArr[1]));
				/*echo "Extending from " . $extendedClass . '<BR><BR>';*/
			} else {
				$extendedClass = '';
				$arguments = Array();

				// Check if it is a object property-style function declaration
				if (strstr($codeLine, ': function')) {
					$itemType = 'function';
					preg_match("/(.*?):\s*?function\s*?\((.*?)\)/", $codeLine, $argArray);
				}

				// Check if it is a function-style declaration
				if (strstr($codeLine, '= function')) {
					$itemType = 'function';
					preg_match("/(.*?)=\s*?function\s*?\((.*?)\)/", $codeLine, $argArray);
				}

				if (strstr($codeLine, '= (function')) {
					$itemType = 'function';
					preg_match("/(.*?)=\s*?\(function\s*?\((.*?)\)/", $codeLine, $argArray);
				}

				if ($itemType === 'function') {
					if ($argArray[1]) {
						$functionName = trim($argArray[1]);
					}
					if ($argArray[2]) {
						$argArray[2] = str_replace(' ', '', $argArray[2]);
						$arguments = explode(',', $argArray[2]);
					}

					/*echo "<B>Function</B>: " . $functionName . '<BR>';

					foreach($arguments as $argKey => $argValue) {
						echo "<B>Argument</B>: " . $argValue . '<BR>';
					}

					echo "<B>Desc</B>: " . $description . '<BR><BR>';*/
				}
			}

			// Add the details to the data array
			switch ($itemType) {
				case 'class':
					$item['type'] = 'class';
					$item['name'] = $className;
					$item['extends'] = $extendedClass;
					break;

				case 'function':
					$item['type'] = 'function';
					$item['name'] = $functionName;
					$item['params'] = $parameters;
					$item['arguments'] = $arguments;
					$item['private'] = $privateMethod;
					$item['constructor'] = $constructorMethod;
					break;
			}

			// Add shared data
			$item['desc'] = $description;

			// If the item was assigned a type (we recognise it), add it to the items array
			if ($item['type']) {
				$docItem[] = $item;
			}
		}

		return $docItem;
	}

	return null;
}
?>