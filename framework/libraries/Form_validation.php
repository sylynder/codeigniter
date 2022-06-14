<?php

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2019, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CodeIgniter
 * @author	EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
 * @copyright	Copyright (c) 2014 - 2019, British Columbia Institute of Technology (https://bcit.ca/)
 * @license	https://opensource.org/licenses/MIT	MIT License
 * @link	https://codeigniter.com
 * @since	Version 1.0.0
 * @filesource
 */
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Form Validation Class
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Validation
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/userguide3/libraries/form_validation.html
 */
class CI_Form_validation
{

	/**
	 * Reference to the CodeIgniter instance
	 *
	 * @var object
	 */
	protected $CI;

	/**
	 * Validation data for the current form submission
	 *
	 * @var array
	 */
	protected $_field_data		= [];

	/**
	 * Validation rules for the current form
	 *
	 * @var array
	 */
	protected $_config_rules	= [];

	/**
	 * Array of validation errors
	 *
	 * @var array
	 */
	protected $_error_array		= [];

	/**
	 * Array of custom error messages
	 *
	 * @var array
	 */
	protected $_error_messages	= [];

	/**
	 * Start tag for error wrapping
	 *
	 * @var string
	 */
	protected $_error_prefix	= '<p>';

	/**
	 * End tag for error wrapping
	 *
	 * @var string
	 */
	protected $_error_suffix	= '</p>';

	/**
	 * Custom error message
	 *
	 * @var string
	 */
	protected $error_string		= '';

	/**
	 * Whether the form data has been validated as safe
	 *
	 * @var bool
	 */
	protected $_safe_form_data	= false;

	/**
	 * Custom data to validate
	 *
	 * @var array
	 */
	public $validation_data	= [];

	/**
	 * Standard Date format
	 * @var string
	 */
	private $_standard_date_format = 'Y-m-d H:i:s';

	/**
	 * Initialize Form_Validation class
	 *
	 * @param	array	$rules
	 * @return	void
	 */
	public function __construct($rules = [])
	{
		$this->CI = &get_instance();

		// applies delimiters set in config file.
		if (isset($rules['error_prefix'])) {
			$this->_error_prefix = $rules['error_prefix'];
			unset($rules['error_prefix']);
		}
		if (isset($rules['error_suffix'])) {
			$this->_error_suffix = $rules['error_suffix'];
			unset($rules['error_suffix']);
		}

		// Validation rules can be stored in a config file.
		$this->_config_rules = $rules;

		// Automatically load the form helper
		$this->CI->load->helper('form');

		log_message('info', 'Form Validation Class Initialized');
	}

	// --------------------------------------------------------------------

	/**
	 * Set Rules
	 *
	 * This function takes an array of field names and validation
	 * rules as input, any custom error messages, validates the info,
	 * and stores it
	 *
	 * @param	mixed	$field
	 * @param	string	$label
	 * @param	mixed	$rules
	 * @param	array	$errors
	 * @return	CI_Form_validation
	 */
	public function set_rules($field, $label = '', $rules = [], $errors = [])
	{
		// No reason to set rules if we have no POST data
		// or a validation array has not been specified
		if ($this->CI->input->method() !== 'post' && empty($this->validation_data)) {
			return $this;
		}

		// If an array was passed via the first parameter instead of individual string
		// values we cycle through it and recursively call this function.
		if (is_array($field)) {
			foreach ($field as $row) {
				// Houston, we have a problem...
				if (!isset($row['field'], $row['rules'])) {
					continue;
				}

				// If the field label wasn't passed we use the field name
				$label = isset($row['label']) ? $row['label'] : $row['field'];

				// Add the custom error message array
				$errors = (isset($row['errors']) && is_array($row['errors'])) ? $row['errors'] : [];

				// Here we go!
				$this->set_rules($row['field'], $label, $row['rules'], $errors);
			}

			return $this;
		}

		// No fields or no rules? Nothing to do...
		if (!is_string($field) or $field === '' or empty($rules)) {
			return $this;
		} elseif (!is_array($rules)) {
			// BC: Convert pipe-separated rules string to an array
			if (!is_string($rules)) {
				return $this;
			}

			$rules = preg_split('/\|(?![^\[]*\])/', $rules);
		}

		// If the field label wasn't passed we use the field name
		$label = ($label === '') ? $field : $label;

		$indexes = [];

		// Is the field name an array? If it is an array, we break it apart
		// into its components so that we can fetch the corresponding POST data later
		if (($is_array = (bool) preg_match_all('/\[(.*?)\]/', $field, $matches)) === true) {
			sscanf($field, '%[^[][', $indexes[0]);

			for ($i = 0, $c = count($matches[0]); $i < $c; $i++) {
				if ($matches[1][$i] !== '') {
					$indexes[] = $matches[1][$i];
				}
			}
		}

		// Build our master array
		$this->_field_data[$field] = [
			'field'		=> $field,
			'label'		=> $label,
			'rules'		=> $rules,
			'errors'	=> $errors,
			'is_array'	=> $is_array,
			'keys'		=> $indexes,
			'postdata'	=> null,
			'error'		=> ''
		];

		return $this;
	}

	/**
	 * Alias to the method above
	 *
	 * This function takes an array of field names and validation
	 * rules as input, any custom error messages, validates the info,
	 * and stores it
	 *
	 * @param	mixed	$field
	 * @param	string	$label
	 * @param	mixed	$rules
	 * @param	array	$errors
	 * @return	CI_Form_validation
	 */
	public function rules($field, $label = '', $rules = [], $errors = [])
	{
		return $this->set_rules($field, $label, $rules, $errors);
	}

	/**
	 * Set Rules without label
	 * 
	 * @param string $field
	 * @param array $rules
	 * @param array $errors
	 * @return	CI_Form_validation
	 */
	public function rule($field = '', $rules = [], $errors = [])
	{
		$label = str_humanize($field, true);

		return $this->rules($field, $label, $rules, $errors);
	}

	/**
	 * Alias to $this->rules() method above
	 * 
	 * Using $this->input() to indicate an input field
	 * 
	 * @param string $field
	 * @param array $rules
	 * @param array $errors
	 * @return	CI_Form_validation
	 */
	public function input($field = '', $rules = [], $errors = [])
	{
		return $this->rule($field, $rules, $errors);
	}

	/**
	 * Alias to the method above
	 * 
	 * Using $this->file() to indicate a file field
	 * 
	 * @param string $field
	 * @param array $rules
	 * @param array $errors
	 * @return	CI_Form_validation
	 */
	public function file($field = '', $rules = [], $errors = [])
	{
		return $this->input($field, $rules, $errors);
	}

	// --------------------------------------------------------------------

	/**
	 * By default, form validation uses the $_POST array to validate
	 *
	 * If an array is set through this method, then this array will
	 * be used instead of the $_POST array
	 *
	 * Note that if you are validating multiple arrays, then the
	 * reset_validation() function should be called after validating
	 * each array due to the limitations of CI's singleton
	 *
	 * @param	array	$data
	 * @return	CI_Form_validation
	 */
	public function set_data(array $data)
	{
		if (!empty($data)) {
			$this->validation_data = $data;
		}

		return $this;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Alias to the method above
	 *
	 * @param array $data
	 * @return CI_Form_validation
	 */
	public function formData(array $data)
	{
		return $this->set_data($data);
	}

	// --------------------------------------------------------------------

	/**
	 * Set Error Message
	 *
	 * Lets users set their own error messages on the fly. Note:
	 * The key name has to match the function name that it corresponds to.
	 *
	 * @param	array
	 * @param	string
	 * @return	CI_Form_validation
	 */
	public function set_message($lang, $val = '')
	{
		if (!is_array($lang)) {
			$lang = [$lang => $val];
		}

		$this->_error_messages = array_merge($this->_error_messages, $lang);
		return $this;
	}

	/**
	 * Alias to the method above
	 *
	 * Lets users set their own error messages on the fly. Note:
	 * The key name has to match the function name that it corresponds to.
	 *
	 * @param	array
	 * @param	string
	 * @return	CI_Form_validation
	 */
	public function setMessage($lang, $val = '')
	{
		return $this->set_message($lang, $val);
	}

	// --------------------------------------------------------------------

	/**
	 * Set The Error Delimiter
	 *
	 * Permits a prefix/suffix to be added to each error message
	 *
	 * @param	string
	 * @param	string
	 * @return	CI_Form_validation
	 */
	public function set_error_delimiters($prefix = '<p>', $suffix = '</p>')
	{
		$this->_error_prefix = $prefix;
		$this->_error_suffix = $suffix;
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Error Message
	 *
	 * Gets the error message associated with a particular field
	 *
	 * @param    string $field Field name
	 * @param    string $prefix HTML start tag
	 * @param    string $suffix HTML end tag
	 * @return    string
	 */
	public function error($field, $prefix = '', $suffix = '')
	{
		if (empty($this->_field_data[$field]['error'])) {
			return '';
		}

		if ($prefix === '') {
			$prefix = $this->_error_prefix;
		}

		if ($suffix === '') {
			$suffix = $this->_error_suffix;
		}

		if (is_array($this->_field_data[$field]['error'])) {
			$error_messages = implode("<br />", $this->_field_data[$field]['error']);
		} else {
			$error_messages = $this->_field_data[$field]['error'];
		}

		return $prefix . $error_messages . $suffix;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Array of Error Messages
	 *
	 * Returns the error messages as an array
	 *
	 * @return	array
	 */
	public function error_array()
	{
		return $this->_error_array;
	}

	// --------------------------------------------------------------------

	/**
	 * Error String
	 *
	 * Returns the error messages as a string, wrapped in the error delimiters
	 *
	 * @param   string
	 * @param   string
	 * @return  string
	 */
	public function error_string($prefix = '', $suffix = '')
	{
		// No errors, validation passes!
		if (count($this->_error_array) === 0) {
			return '';
		}

		if ($prefix === '') {
			$prefix = $this->_error_prefix;
		}

		if ($suffix === '') {
			$suffix = $this->_error_suffix;
		}

		// Generate the error string
		$str = '';

		foreach ($this->_error_array as $val) {
			if ($val !== '') {
				//if field has more than one error, then all will be listed
				if (is_array($val)) {
					foreach ($val as $v) {
						$str .= $prefix . $v . $suffix . "\n";
					}
				} else {
					$str .= $prefix . $val . $suffix . "\n";
				}
			}
		}

		return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Run the Validator
	 *
	 * This function does all the work.
	 *
	 * @param	string	$group
	 * @return	bool
	 */
	public function run($group = '')
	{
		$validation_array = empty($this->validation_data)
			? $_POST
			: $this->validation_data;

		// Does the _field_data array containing the validation rules exist?
		// If not, we look to see if they were assigned via a config file
		if (count($this->_field_data) === 0) {
			// No validation rules?  We're done...
			if (count($this->_config_rules) === 0) {
				return false;
			}

			if (empty($group)) {
				// Is there a validation rule for the particular URI being accessed?
				$group = trim($this->CI->uri->ruri_string(), '/');
				isset($this->_config_rules[$group]) or $group = $this->CI->router->class . '/' . $this->CI->router->method;
			}

			$this->set_rules(isset($this->_config_rules[$group]) ? $this->_config_rules[$group] : $this->_config_rules);

			// Were we able to set the rules correctly?
			if (count($this->_field_data) === 0) {
				log_message('debug', 'Unable to find validation rules');
				return false;
			}
		}

		// Load the language file containing error messages
		$this->CI->lang->load('form_validation');

		// Cycle through the rules for each field and match the corresponding $validation_data item
		foreach ($this->_field_data as $field => &$row) {
			// Fetch the data from the validation_data array item and cache it in the _field_data array.
			// Depending on whether the field name is an array or a string will determine where we get it from.
			if ($row['is_array'] === true) {
				$this->_field_data[$field]['postdata'] = $this->_reduce_array($validation_array, $row['keys']);
			} elseif (isset($validation_array[$field])) {
				$this->_field_data[$field]['postdata'] = $validation_array[$field];
			}
		}

		// Execute validation rules
		// Note: A second foreach (for now) is required in order to avoid false-positives
		//	 for rules like 'matches', which correlate to other validation fields.
		foreach ($this->_field_data as $field => &$row) {
			// Don't try to validate if we have no rules set
			if (empty($row['rules'])) {
				continue;
			}

			$this->_execute($row, $row['rules'], $row['postdata']);
		}

		// Did we end up with any errors?
		$total_errors = count($this->_error_array);
		if ($total_errors > 0) {
			$this->_safe_form_data = true;
		}

		// Now we need to re-set the POST data with the new, processed data
		empty($this->validation_data) && $this->_reset_post_array();

		return ($total_errors === 0);
	}

	/**
	 * Alias to the method above
	 *
	 * This function does all the work.
	 *
	 * @param	string	$group
	 * @return	bool
	 */
	public function check($group = '')
	{
		return $this->run($group);
	}

	// --------------------------------------------------------------------

	/**
	 * Prepare rules
	 *
	 * Re-orders the provided rules in order of importance, so that
	 * they can easily be executed later without weird checks ...
	 *
	 * "Callbacks" are given the highest priority (always called),
	 * followed by 'required' (called if callbacks didn't fail),
	 * and then every next rule depends on the previous one passing.
	 *
	 * @param	array	$rules
	 * @return	array
	 */
	protected function _prepare_rules($rules)
	{
		$new_rules = [];
		$callbacks = [];

		foreach ($rules as &$rule) {
			// Let 'required' always be the first (non-callback) rule
			if ($rule === 'required') {
				array_unshift($new_rules, 'required');
			}
			// 'isset' is a kind of a weird alias for 'required' ...
			elseif ($rule === 'isset' && (empty($new_rules) or $new_rules[0] !== 'required')) {
				array_unshift($new_rules, 'isset');
			}
			// The old/classic 'callback_'-prefixed rules
			elseif (is_string($rule) && strncmp('callback_', $rule, 9) === 0) {
				$callbacks[] = $rule;
			}
			// Proper callables
			elseif (is_callable($rule)) {
				$callbacks[] = $rule;
			}
			// "Named" callables; i.e. ['name' => $callable]
			elseif (is_array($rule) && isset($rule[0], $rule[1]) && is_callable($rule[1])) {
				$callbacks[] = $rule;
			}
			// Everything else goes at the end of the queue
			else {
				$new_rules[] = $rule;
			}
		}

		return array_merge($callbacks, $new_rules);
	}

	// --------------------------------------------------------------------

	/**
	 * Traverse a multidimensional $_POST array index until the data is found
	 *
	 * @param	array
	 * @param	array
	 * @param	int
	 * @return	mixed
	 */
	protected function _reduce_array($array, $keys, $i = 0)
	{
		if (is_array($array) && isset($keys[$i])) {
			return isset($array[$keys[$i]]) ? $this->_reduce_array($array[$keys[$i]], $keys, ($i + 1)) : null;
		}

		// null must be returned for empty fields
		return ($array === '') ? null : $array;
	}

	// --------------------------------------------------------------------

	/**
	 * Re-populate the _POST array with our finalized and processed data
	 *
	 * @return	void
	 */
	protected function _reset_post_array()
	{
		foreach ($this->_field_data as $field => $row) {
			if ($row['postdata'] !== null) {
				if ($row['is_array'] === false) {
					isset($_POST[$field]) && $_POST[$field] = is_array($row['postdata']) ? null : $row['postdata'];
				} else {
					// start with a reference
					$post_ref = &$_POST;

					// before we assign values, make a reference to the right POST key
					if (count($row['keys']) === 1) {
						$post_ref = &$post_ref[current($row['keys'])];
					} else {
						foreach ($row['keys'] as $val) {
							$post_ref = &$post_ref[$val];
						}
					}

					$post_ref = $row['postdata'];
				}
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Executes the Validation routines
	 *
	 * @param	array
	 * @param	array
	 * @param	mixed
	 * @param	int
	 * @return	mixed
	 */
	protected function _execute($row, $rules, $postdata = null, $cycles = 0)
	{
		// If the $_POST data is an array we will run a recursive call
		//
		// Note: We MUST check if the array is empty or not!
		//       Otherwise empty arrays will always pass validation.
		if (is_array($postdata) && !empty($postdata)) {
			foreach ($postdata as $key => $val) {
				$this->_execute($row, $rules, $val, $key);
			}

			return;
		}

		$rules = $this->_prepare_rules($rules);
		foreach ($rules as $rule) {
			$_in_array = false;

			// We set the $postdata variable with the current data in our master array so that
			// each cycle of the loop is dealing with the processed data from the last cycle
			if ($row['is_array'] === true && is_array($this->_field_data[$row['field']]['postdata'])) {
				// We shouldn't need this safety, but just in case there isn't an array index
				// associated with this cycle we'll bail out
				if (!isset($this->_field_data[$row['field']]['postdata'][$cycles])) {
					continue;
				}

				$postdata = $this->_field_data[$row['field']]['postdata'][$cycles];
				$_in_array = true;
			} else {
				// If we get an array field, but it's not expected - then it is most likely
				// somebody messing with the form on the client side, so we'll just consider
				// it an empty field
				$postdata = is_array($this->_field_data[$row['field']]['postdata'])
					? null
					: $this->_field_data[$row['field']]['postdata'];
			}

			// Is the rule a callback?
			$callback = $callable = false;
			if (is_string($rule)) {
				if (strpos($rule, 'callback_') === 0) {
					$rule = substr($rule, 9);
					$callback = true;
				}
			} elseif (is_callable($rule)) {
				$callable = true;
			} elseif (is_array($rule) && isset($rule[0], $rule[1]) && is_callable($rule[1])) {
				// We have a "named" callable, so save the name
				$callable = $rule[0];
				$rule = $rule[1];
			}

			// Strip the parameter (if exists) from the rule
			// Rules can contain a parameter: max_length[5]
			$param = false;
			if (!$callable && preg_match('/(.*?)\[(.*)\]/', $rule, $match)) {
				$rule = $match[1];
				$param = $match[2];
			}

			// Ignore empty, non-required inputs with a few exceptions ...
			if (
				($postdata === null or $postdata === '')
				&& $callback === false
				&& $callable === false
				&& !in_array($rule, ['required', 'isset', 'matches'], true)
			) {
				continue;
			}

			// Call the function that corresponds to the rule
			if ($callback or $callable !== false) {
				if ($callback) {
					if (!method_exists($this->CI, $rule)) {
						log_message('debug', 'Unable to find callback validation rule: ' . $rule);
						$result = false;
					} else {
						// Run the function and grab the result
						$result = $this->CI->$rule($postdata, $param);
					}
				} else {
					$result = is_array($rule)
						? $rule[0]->{$rule[1]}($postdata)
						: $rule($postdata);

					// Is $callable set to a rule name?
					if ($callable !== false) {
						$rule = $callable;
					}
				}

				// Re-assign the result to the master data array
				if ($_in_array === true) {
					$this->_field_data[$row['field']]['postdata'][$cycles] = is_bool($result) ? $postdata : $result;
				} else {
					$this->_field_data[$row['field']]['postdata'] = is_bool($result) ? $postdata : $result;
				}
			} elseif (!method_exists($this, $rule)) {
				// If our own wrapper function doesn't exist we see if a native PHP function does.
				// Users can use any native PHP function call that has one param.
				if (function_exists($rule)) {
					// Native PHP functions issue warnings if you pass them more parameters than they use
					$result = ($param !== false) ? $rule($postdata, $param) : $rule($postdata);

					if ($_in_array === true) {
						$this->_field_data[$row['field']]['postdata'][$cycles] = is_bool($result) ? $postdata : $result;
					} else {
						$this->_field_data[$row['field']]['postdata'] = is_bool($result) ? $postdata : $result;
					}
				} else {
					log_message('debug', 'Unable to find validation rule: ' . $rule);
					$result = false;
				}
			} else {
				$result = $this->$rule($postdata, $param);

				if ($_in_array === true) {
					$this->_field_data[$row['field']]['postdata'][$cycles] = is_bool($result) ? $postdata : $result;
				} else {
					$this->_field_data[$row['field']]['postdata'] = is_bool($result) ? $postdata : $result;
				}
			}

			// Did the rule test negatively? If so, grab the error.
			if ($result === false) {
				// Callable rules might not have named error messages
				if (!is_string($rule)) {
					$line = $this->CI->lang->line('form_validation_error_message_not_set') . '(Anonymous function)';
				} else {
					$line = $this->_get_error_message($rule, $row['field']);
				}

				// Is the parameter we are inserting into the error message the name
				// of another field? If so we need to grab its "field label"
				if (isset($this->_field_data[$param], $this->_field_data[$param]['label'])) {
					$param = $this->_translate_fieldname($this->_field_data[$param]['label']);
				}
				
				// Build the error message
				$message = $this->_build_error_msg($line, $this->_translate_fieldname($row['label']), $param);

				// Save the error message
				$this->_field_data[$row['field']]['error'] = $message;

				if (!isset($this->_error_array[$row['field']])) {
					$this->_error_array[$row['field']] = $message;
				}

				return;
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Get the error message for the rule
	 *
	 * @param 	string $rule 	The rule name
	 * @param 	string $field	The field name
	 * @return 	string
	 */
	protected function _get_error_message($rule, $field)
	{
		// check if a custom message is defined through validation config row.
		if (isset($this->_field_data[$field]['errors'][$rule])) {
			return $this->_field_data[$field]['errors'][$rule];
		}
		// check if a custom message has been set using the set_message() function
		elseif (isset($this->_error_messages[$rule])) {
			return $this->_error_messages[$rule];
		} elseif (false !== ($line = $this->CI->lang->line('form_validation_' . $rule))) {
			return $line;
		}
		// DEPRECATED support for non-prefixed keys, lang file again
		elseif (false !== ($line = $this->CI->lang->line($rule, false))) {
			return $line;
		}

		return $this->CI->lang->line('form_validation_error_message_not_set') . '(' . $rule . ')';
	}

	// --------------------------------------------------------------------

	/**
	 * Translate a field name
	 *
	 * @param	string	the field name
	 * @return	string
	 */
	protected function _translate_fieldname($fieldname)
	{
		// Do we need to translate the field name? We look for the prefix 'lang:' to determine this
		// If we find one, but there's no translation for the string - just return it
		if (sscanf($fieldname, 'lang:%s', $line) === 1 && false === ($fieldname = $this->CI->lang->line($line, false))) {
			return $line;
		}

		return $fieldname;
	}

	// --------------------------------------------------------------------

	/**
	 * Build an error message using the field and param.
	 *
	 * @param	string	The error message line
	 * @param	string	A field's human name
	 * @param	mixed	A rule's optional parameter
	 * @return	string
	 */
	protected function _build_error_msg($line, $field = '', $param = '')
	{
		// Check for %s in the string for legacy support.
		if (strpos($line, '%s') !== false) {
			return sprintf($line, $field, $param);
		}

		return str_replace(['{field}', '{param}'], [$field, $param], $line);
	}

	// --------------------------------------------------------------------

	/**
	 * Checks if the rule is present within the validator
	 *
	 * Permits you to check if a rule is present within the validator
	 *
	 * @param	string	the field name
	 * @return	bool
	 */
	public function has_rule($field)
	{
		return isset($this->_field_data[$field]);
	}

	// --------------------------------------------------------------------

	/**
	 * Get the value from a form
	 *
	 * Permits you to repopulate a form field with the value it was submitted
	 * with, or, if that value doesn't exist, with the default
	 *
	 * @param	string	the field name
	 * @param	string
	 * @return	string
	 */
	public function set_value($field = '', $default = '')
	{
		if (!isset($this->_field_data[$field], $this->_field_data[$field]['postdata'])) {
			return $default;
		}

		// If the data is an array output them one at a time.
		//	E.g: form_input('name[]', set_value('name[]');
		if (is_array($this->_field_data[$field]['postdata'])) {
			return array_shift($this->_field_data[$field]['postdata']);
		}

		return $this->_field_data[$field]['postdata'];
	}

	// --------------------------------------------------------------------

	/**
	 * Set Select
	 *
	 * Enables pull-down lists to be set to the value the user
	 * selected in the event of an error
	 *
	 * @param	string
	 * @param	string
	 * @param	bool
	 * @return	string
	 */
	public function set_select($field = '', $value = '', $default = false)
	{
		if (!isset($this->_field_data[$field], $this->_field_data[$field]['postdata'])) {
			return ($default === true && count($this->_field_data) === 0) ? ' selected="selected"' : '';
		}

		$field = $this->_field_data[$field]['postdata'];
		$value = (string) $value;
		if (is_array($field)) {
			// Note: in_array('', [0]) returns true, do not use it
			foreach ($field as &$v) {
				if ($value === $v) {
					return ' selected="selected"';
				}
			}

			return '';
		} elseif (($field === '' or $value === '') or ($field !== $value)) {
			return '';
		}

		return ' selected="selected"';
	}

	// --------------------------------------------------------------------

	/**
	 * Set Radio
	 *
	 * Enables radio buttons to be set to the value the user
	 * selected in the event of an error
	 *
	 * @param	string
	 * @param	string
	 * @param	bool
	 * @return	string
	 */
	public function set_radio($field = '', $value = '', $default = false)
	{
		if (!isset($this->_field_data[$field], $this->_field_data[$field]['postdata'])) {
			return ($default === true && count($this->_field_data) === 0) ? ' checked="checked"' : '';
		}

		$field = $this->_field_data[$field]['postdata'];
		$value = (string) $value;
		if (is_array($field)) {
			// Note: in_array('', [0]) returns true, do not use it
			foreach ($field as &$v) {
				if ($value === $v) {
					return ' checked="checked"';
				}
			}

			return '';
		} elseif (($field === '' or $value === '') or ($field !== $value)) {
			return '';
		}

		return ' checked="checked"';
	}

	// --------------------------------------------------------------------

	/**
	 * Set Checkbox
	 *
	 * Enables checkboxes to be set to the value the user
	 * selected in the event of an error
	 *
	 * @param	string
	 * @param	string
	 * @param	bool
	 * @return	string
	 */
	public function set_checkbox($field = '', $value = '', $default = false)
	{
		// Logic is exactly the same as for radio fields
		return $this->set_radio($field, $value, $default);
	}

	// --------------------------------------------------------------------

	/**
	 * Required
	 *
	 * @param	string
	 * @return	bool
	 */
	public function required($str = '')
	{
		return is_array($str)
			? (empty($str) === false)
			: (isset($str) ? trim($str) !== '' : false);
	}

	// --------------------------------------------------------------------

	/**
	 * Checks if the required file is uploaded
	 *
	 * @param    mixed $file
	 * @return    bool
	 */
	public function file_required($file)
	{
		if ($file['size'] === 0) {
			return false;
		}

		return true;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns false if the file is bigger than the given size
	 *
	 * @param    mixed $file
	 * @param    string
	 * @return   bool
	 */
	public function file_max_size($file, $max_size)
	{
		$max_size_bit = $this->set_to_bit($max_size);

		if ($file['size'] > $max_size_bit) {
			
			$this->set_message('file_max_size', "The %s file is too big. (max size allowed is $max_size)");
			
			return false;
		}

		return true;
	}

	// --------------------------------------------------------------------

	/**
	 * Returns false if the file is smaller than the given size
	 *
	 * @param    mixed $file
	 * @param    string
	 * @return   bool
	 */
	public function file_min_size($file, $min_size)
	{
		$min_size_bit = $this->set_to_bit($min_size);

		if ($file['size'] < $min_size_bit) {

			$this->set_message('file_min_size', "The %s file is too small. (min size allowed is $min_size)");
			
			return false;

		}
		return true;
	}

	// --------------------------------------------------------------------

	/**
	 * Given a string in format of ###AA
	 * converts to number of bits it is assigned in
	 *
	 * @param    string  
	 * @return   integer number of bits
	 */
	public function set_to_bit($sizeValue)
	{
		// Split value from name
		if (!preg_match('/([0-9]+)([ptgmkb]{1,2}|)/ui', $sizeValue, $matched)) { // Invalid input
			return false;
		}

		if (empty($matched[2])) { // No name -> Enter default value
			$matched[2] = 'KB';
		}

		if (strlen($matched[2]) == 1) { // Shorted name -> full name
			$matched[2] .= 'B';
		}

		$bit = (substr($matched[2], -1) == 'B') ? 1024 : 1000;
		
		// Calculate bits:
		switch (strtoupper(substr($matched[2], 0, 1))) {
			case 'P':
				$matched[1] *= $bit;
			case 'T':
				$matched[1] *= $bit;
			case 'G':
				$matched[1] *= $bit;
			case 'M':
				$matched[1] *= $bit;
			case 'K':
				$matched[1] *= $bit;
				break;
		}

		// Return the value in bits
		return $matched[1];
	}

	// --------------------------------------------------------------------


	/**
	 * Checks the file extension for no-valid file types
	 *
	 * @param    mixed $file
	 * @param    mixed
	 * @return   bool
	 */
	public function file_disallowed_type($file, $type)
	{
		if ($this->file_allowed_type($file, $type) == false) {
			return true;
		}

		return false;
	}

	// --------------------------------------------------------------------


	/**
	 * Checks the file extension for valid file types
	 *
	 * @param    mixed $file
	 * @param    mixed
	 * @return   bool
	 */
	public function file_allowed_type($file, $type)
	{

		// is type of format a,b,c,d? -> convert to array
		$exts = explode(',', $type);

		// is $type array? run self recursively
		if (count($exts) > 1) {

			foreach ($exts as $v) {
				$rc = $this->file_allowed_type($file, $v);
				if ($rc === true) {
					return true;
				}
			}

		}

		// is type a group type? image, application, word_document, code, zip .... -> load proper array
		$ext_groups = [];
		$ext_groups['image'] = ['jpg', 'jpeg', 'gif', 'png', 'webp', 'avif', 'svg', 'tiff'];
		$ext_groups['image_icon'] = ['jpg', 'jpeg', 'gif', 'png', 'ico', 'image/x-icon'];
		$ext_groups['application'] = ['exe', 'dll', 'so', 'cgi'];
		$ext_groups['php_code'] = ['php', 'php4', 'php5', 'inc', 'phtml'];
		$ext_groups['word_document'] = ['rtf', 'doc', 'docx'];
		$ext_groups['compressed'] = ['zip', 'gzip', 'tar', 'gz'];
		$ext_groups['document'] = ['txt', 'text', 'doc', 'docx', 'dot', 'dotx', 'word', 'rtf', 'rtx', 'pdf'];
		$ext_groups['spreadsheet'] = ['csv', 'excel', 'xls', 'xlsx', 'ods', 'json'];
		$ext_groups['presentation'] = ['ppt', 'pptx', 'pps', 'ppsx', 'xps', 'odp', 'htm', 'html', 'pdf'];

		// if there is a group type in the $type var and not a ext alone, we get it
		if (array_key_exists($exts[0], $ext_groups)) {
			$exts = $ext_groups[$exts[0]];
		}

		$exts_types = array_flip($exts);
		$intersection = array_intersect_key($this->CI->output->mimes, $exts_types);

		// if we can use the finfo function to check the mime AND the mime
		// exists in the mime file of codeigniter...
		if (function_exists('finfo_open') and !empty($intersection)) {
			
			$exts = [];

			foreach ($intersection as $in) {
				if (is_array($in)) {
					$exts = array_merge($exts, $in);
				} else {
					$exts[] = $in;
				}
			}

			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$file_type = finfo_file($finfo, $file['tmp_name']);
		} else {
			// get file ext
			$file_type = strtolower(strrchr($file['name'], '.'));
			$file_type = substr($file_type, 1);
		}

		if (!in_array($file_type, $exts)) {
			$this->set_message('file_allowed_type', "The %s file allowed should be a/an $type.");
			return false;
		} else {
			return true;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Attempts to determine the image dimension
	 *
	 * @param    mixed
	 * @return   array
	 */
	public function get_image_dimension($file_name)
	{
		log_message('debug', $file_name);

		if (function_exists('getimagesize')) {
			return @getimagesize($file_name);
		}

		return false;
	}

    // --------------------------------------------------------------------

	/**
     * Returns false if the image is bigger than given dimension
     *
     * @param    string
     * @param    array
     * @return    bool
     */
	public function file_image_maxdim($file, $dimension)
    {
        log_message('debug', 'Form_validation: file_image_maxdim ' . $dimension);

		$dimension = explode(',', $dimension);

        if (count($dimension) !== 2)
        {
            // Bad size given
            log_message('error', 'Form_validation: invalid rule, expecting a rule like [150,300].');
			$this->set_message('file_image_maxdim', 'The %s file has invalid rule, expecting a rule like [150,300].');
           
            return false;
        }

        log_message('debug', 'Form_validation: file_image_maxdim ' . $dimension[0] . ' ' . $dimension[1]);

        // get image size
        $dim = $this->get_image_dimension($file['tmp_name']);

		if (is_array($dim)) {
			log_message('debug', $dim[0] . ' ' . $dim[1]);
		}

        if (!$dim)
        {
			log_message('error', 'Form_validation: dimensions not detected for file with type ' . $file['type'] . '.');

			$this->set_message('file_image_maxdim', 'The %s file dimensions was not detected.');
            return false;
        }

        if ($dim[0] <= $dimension[0] && $dim[1] <= $dimension[1])
        {
            return true;
        }

		$this->set_message('file_image_maxdim', 'The %s file image size is too big.');
        return false;
    }

    // --------------------------------------------------------------------

    /**
     * Returns false if the image is smaller than given dimension
     *
     * @param    mixed
     * @param    array
     * @return   bool
     */
	public function file_image_mindim($file, $dimension)
    {
		$dimension = explode(',', $dimension);

        if (count($dimension) !== 2)
        {
            // Bad size given
            log_message('error', 'Form_validation: invalid rule, expecting a rule like [150,300].');
			
			$this->set_message('file_image_mindim', 'The %s file has invalid rule, expecting a rule like [150,300].');
           
            return false;
        }

        // get image size
        $dim = $this->get_image_dimension($file['tmp_name']);

        if (!$dim)
        {
            log_message('error', 'Form_validation: dimensions not detected for file with type '.$file['type'].'.');
			
			$this->set_message('file_image_mindim', 'The %s file dimensions was not detected.');
			
			return false;
        }

		if (is_array($dim)) {
			log_message('debug', $dim[0] . ' ' . $dim[1]);
		}

        if ($dim[0] >= $dimension[0] && $dim[1] >= $dimension[1])
        {
            return true;
        }

		$this->set_message('file_image_mindim', 'The %s file image size is too small.');
		
		return false;
    }

    // --------------------------------------------------------------------

    /**
     * Returns false if the image is not the given exact dimension
     *
     * @param    mixed
     * @param    array
     * @return   bool
     */
	public function file_image_exactdim($file, $dimension)
    {
		$dimension = explode(',', $dimension);

        if (count($dimension) !== 2)
        {
            // Bad size given
            log_message('error', 'Form_validation: invalid rule, expecting a rule like [150,300].');
			
			$this->set_message('file_image_exactdim', 'The %s file has invalid rule, expecting a rule like [150,300].');
           
            return false;
        }

        // get image size
        $dim = $this->get_image_dimension($file['tmp_name']);

        if (!$dim)
        {
			log_message('error', 'Form_validation: dimensions not detected for file with type ' . $file['type'] . '.');
			$this->set_message('file_image_exactdim', 'The %s file dimensions was not detected.');
			
            return false;
        }

		if (is_array($dim)) {
			log_message('debug', $dim[0] . ' ' . $dim[1]);
		}

        if ($dim[0] == $dimension[0] && $dim[1] == $dimension[1])
        {
            return true;
        }

		$this->set_message('file_image_exactdim', 'The %s file image size is not the exact dimension.');
		
		return false;
    }

	// --------------------------------------------------------------------

	/**
	 * Check if the field's value is in the list
	 *
	 * Alias to parent::in_list
	 * 
	 * @param string $value
	 * @param string $list
	 *
	 * @return bool
	 */
	public function is_exactly(string $value = null, string $list): bool
	{
		return $this->in_list($value, $list);
	}

	/**
	 * Check if the field's value is not permitted
	 *
	 * @param    string
	 * @param    string
	 * @return    bool
	 */
	public function is_not($str, $list)
	{
		$list = str_replace(', ', ',', $list); // Just taking some precautions
		
		$list = explode(',', $list);

		if (in_array(trim($str), $list)) {
			return false;
		} else {
			return true;
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Strip html tags from Wysiwyg Editors
	 * Except anchor tags
	 *
	 * @param string $str
	 * @return string
	 */
	public function strip_editor_tags($str)
	{
		return strip_tags($str, '<strong><b><p><ul><ol><li><span>');
	}

	// --------------------------------------------------------------------

	/**
	 * Strip html tags from Wysiwyg Editors
	 * 
	 * Set any tag you want to strip too
	 * 
	 * @param string $str
	 * @param string $tags
	 * @return string
	 */
	public function strip_editor_all_tags($str, $tags = '<strong><b><p><ul><ol><li><a><span>')
	{
		return strip_tags($str, $tags);
	}

	// --------------------------------------------------------------------

	/**
	 * Check if the field's value is a valid 24 hour
	 *
	 * @param string $hour
	 * @param string $type
	 * @return bool
	 */
	public function valid_hour(string $hour, string $type = '24H'): bool
	{
		if (substr_count($hour, ':') >= 2) {
			$has_seconds = true;
		} else {
			$has_seconds = false;
		}

		$pattern = "/^" . (($type == '24H') ? "([1-2][0-3]|[01]?[1-9])" : "(1[0-2]|0?[1-9])") . ":([0-5]?[0-9])" . (($has_seconds) ? ":([0-5]?[0-9])" : "") . (($type == '24H') ? '' : '( AM| PM| am| pm)') . "$/";

		if (preg_match($pattern, $hour)) {
			return true;
		} else {
			return false;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Checks for a valid date and matches a given date format
	 *
	 * @param string $str
	 * @param string $format
	 *
	 * @return bool
	 */
	public function valid_date(string $str = null, string $format = null): bool
	{
		if (empty($format))
		{
			return (bool) strtotime($str);
		}

		$date = DateTime::createFromFormat($format, $str);

		return (bool) $date && DateTime::getLastErrors()['warning_count'] === 0 && DateTime::getLastErrors()['error_count'] === 0;
	}

	// --------------------------------------------------------------------

	private function _date_parse_from_format($format, $date)
	{
		// reverse engineer date formats
		$keys = [
			'Y' => ['year', '\d{4}'],
			'y' => ['year', '\d{2}'],
			'm' => ['month', '\d{2}'],
			'n' => ['month', '\d{1,2}'],
			'M' => ['month', '[A-Z][a-z]{3}'],
			'F' => ['month', '[A-Z][a-z]{2,8}'],
			'd' => ['day', '\d{2}'],
			'j' => ['day', '\d{1,2}'],
			'D' => ['day', '[A-Z][a-z]{2}'],
			'l' => ['day', '[A-Z][a-z]{6,9}'],
			'u' => ['hour', '\d{1,6}'],
			'h' => ['hour', '\d{2}'],
			'H' => ['hour', '\d{2}'],
			'g' => ['hour', '\d{1,2}'],
			'G' => ['hour', '\d{1,2}'],
			'i' => ['minute', '\d{2}'],
			's' => ['second', '\d{2}']
		];

		// convert format string to regex
		$regex = '';
		$chars = str_split($format);

		foreach ($chars as $n => $char) {

			$lastChar = isset($chars[$n - 1]) ? $chars[$n - 1] : '';
			$skipCurrent = '\\' == $lastChar;

			if (!$skipCurrent && isset($keys[$char])) {
				$regex .= '(?P<' . $keys[$char][0] . '>' . $keys[$char][1] . ')';
			} else {
				if ('\\' == $char) {
					$regex .= $char;
				} else {
					$regex .= preg_quote($char);
				}
			}
		}

		$dt = [];

		// now try to match it
		if (preg_match('#^' . $regex . '$#', $date, $dt)) {
			foreach ($dt as $k => $v) {
				if (is_int($k)) {
					unset($dt[$k]);
				}
			}
			if (!checkdate($dt['month'], $dt['day'], $dt['year'])) {
				$dt['error_count'] = 1;
			} else {
				$dt['error_count'] = 0;
			}
		} else {
			$dt['error_count'] = 1;
		}

		$dt['errors'] = [];
		$dt['fraction'] = '';
		$dt['warning_count'] = 0;
		$dt['warnings'] = [];
		$dt['is_localtime'] = 0;
		$dt['zone_type'] = 0;
		$dt['zone'] = 0;
		$dt['is_dst'] = '';

		return $dt;
	}

	// --------------------------------------------------------------------

	/**
	 *  Check if the field's value has a valid range of two date format, if not provided,
	 *  it will use the $_standard_date_format value
	 *
	 * @param string $str
	 * @param string $format
	 * @return bool
	 */
	public function valid_range_date($str, $format = null)
	{

		if (is_null($format) or $format === false) {
			$format = $this->_standard_date_format;
		}

		$separation_char = '-';


		$exploded = explode($separation_char, $str);

		foreach ($exploded as $key => $e) {
			$exploded[$key] = trim($e);
		}

		if (count($exploded) > 2) {
			//in case we are using dates like Y-m-d and separation char is - etc...

			$sub_exploded = $exploded;
			$count_rows = count($exploded);

			$exploded = [];

			$vector_exploded = [];

			for ($i = 0; $i < ($count_rows / 2); $i++) {
				$vector_exploded[] = $sub_exploded[$i];
			}

			$exploded[0] = implode($separation_char, $vector_exploded);
			$vector_exploded = [];

			for ($i = ($count_rows / 2); $i < $count_rows; $i++) {
				$vector_exploded[] = $sub_exploded[$i];
			}

			$exploded[1] = implode($separation_char, $vector_exploded);
		}

		$dates = [];
		$valid_dates = true;
		foreach ($exploded as $e) {
			if (function_exists('date_parse_from_format')) {
				$parsed = date_parse_from_format($format, $e);
			} else {
				$parsed = $this->_date_parse_from_format($format, $e);
			}


			$dates[] = $parsed;
			if ($parsed['warning_count'] > 0 or $parsed['error_count'] > 0) {
				$valid_dates = false;
			}
		}
		if ($valid_dates == false) {
			return false;
		}
		// why use strtotime when you can get hardcore!
		if (
			mktime($dates[0]['hour'], $dates[0]['minute'], $dates[0]['second'], $dates[0]['month'], $dates[0]['day'], $dates[0]['year']) >
			mktime($dates[1]['hour'], $dates[1]['minute'], $dates[1]['second'], $dates[1]['month'], $dates[1]['day'], $dates[1]['year'])
		) {
			return false;
		}

		return true;
	}

	// --------------------------------------------------------------------

	/**
	 * Validates a given latitude $lat
	 *
	 * @param float|int|string $lat Latitude
	 * @return bool `true` if $lat is valid, `false` if not
	 */
	public function valid_latitude($latitude)
	{
		return (bool) preg_match('/^(\+|-)?(?:90(?:(?:\.0{1,6})?)|(?:[0-9]|[1-8][0-9])(?:(?:\.[0-9]{1,6})?))$/', $latitude);
	}

	// --------------------------------------------------------------------

	/**
	 * Validates a given longitude $long
	 *
	 * @param float|int|string $long Longitude
	 * @return bool `true` if $long is valid, `false` if not
	 */
	public function valid_longitude($longitude) {
		return (bool) preg_match('/^(\+|-)?(?:180(?:(?:\.0{1,6})?)|(?:[0-9]|[1-9][0-9]|1[0-7][0-9])(?:(?:\.[0-9]{1,6})?))$/', $longitude);
	}

	// --------------------------------------------------------------------

	/**
	 * Validates a given coordinate
	 *
	 * @param float|int|string $lat Latitude
	 * @param float|int|string $long Longitude
	 * @return bool `true` if the coordinate is valid, `false` if not
	 */
	public function valid_latlong($latitude, $longitude)
	{
		return (bool) preg_match('/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?),[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $latitude . ',' . $longitude);
	}

	// --------------------------------------------------------------------

	/**
	 * Performs a Regular Expression match test.
	 *
	 * @param	string
	 * @param	string	regex
	 * @return	bool
	 */
	public function regex_match($str, $regex)
	{
		return (bool) preg_match($regex, $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Match one field to another
	 *
	 * @param	string	$str	string to compare against
	 * @param	string	$field
	 * @return	bool
	 */
	public function matches($str, $field)
	{
		return isset($this->_field_data[$field], $this->_field_data[$field]['postdata'])
			? ($str === $this->_field_data[$field]['postdata'])
			: false;
	}

	// --------------------------------------------------------------------

	/**
	 * Differs from another field
	 *
	 * @param	string
	 * @param	string	field
	 * @return	bool
	 */
	public function differs($str, $field)
	{
		return !(isset($this->_field_data[$field]) && $this->_field_data[$field]['postdata'] === $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Is Unique
	 *
	 * Check if the input value doesn't already exist
	 * in the specified database field.
	 * 
	 * Can also check for uniqueness when updating an
	 * already existing field in database field
	 *
	 * @param	string	$str
	 * @param	string	$field
	 * @return	bool
	 */
	public function is_unique($str, $field)
	{
		$query = null;

		if (strpos($field, ',') !== false && substr_count($field, ',') == 2) {

			list($table, $field) = explode('.', $field);
			list($field, $field_column, $field_value) = explode(',', $field);

			$query = $this->CI->db->limit(1)->where($field, $str)->where($field_column . ' != ', $field_value)->get($table);
		} else {

			list($table, $field) = explode('.', $field);
			$query = $this->CI->db->limit(1)->get_where($table, [$field => $str]);
		}

		return $query->num_rows() === 0;
	}

	/**
	 * Alias to the method above
	 *
	 * Check if the input value doesn't already exist
	 * in the specified database field.
	 * 
	 * Can also check for uniqueness when updating an
	 * already existing field in database field
	 *
	 * @param	string	$str
	 * @param	string	$field
	 * @return	bool
	 */
	public function exists($str, $field)
	{
		$this->is_unique($str, $field);
	}

	// --------------------------------------------------------------------

	/**
	 * Minimum Length
	 *
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function min_length($str, $val)
	{
		if (!is_numeric($val)) {
			return false;
		}

		return ($val <= mb_strlen($str));
	}

	// --------------------------------------------------------------------

	/**
	 * Max Length
	 *
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function max_length($str, $val)
	{
		if (!is_numeric($val)) {
			return false;
		}

		return ($val >= mb_strlen($str));
	}

	// --------------------------------------------------------------------

	/**
	 * Exact Length
	 *
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function exact_length($str, $val)
	{
		if (!is_numeric($val)) {
			return false;
		}

		return (mb_strlen($str) === (int) $val);
	}

	// --------------------------------------------------------------------

	/**
	 * Valid URL
	 *
	 * @param	string	$str
	 * @return	bool
	 */
	public function valid_url($str)
	{
		if (empty($str)) {
			return false;
		} elseif (preg_match('/^(?:([^:]*)\:)?\/\/(.+)$/', $str, $matches)) {
			if (empty($matches[2])) {
				return false;
			} elseif (!in_array(strtolower($matches[1]), ['http', 'https'], true)) {
				return false;
			}

			$str = $matches[2];
		}

		// Apparently, FILTER_VALIDATE_URL doesn't reject digit-only names for some reason ...
		// See https://github.com/bcit-ci/CodeIgniter/issues/5755
		if (ctype_digit($str)) {
			return false;
		}

		// PHP 7 accepts IPv6 addresses within square brackets as hostnames,
		// but it appears that the PR that came in with https://bugs.php.net/bug.php?id=68039
		// was never merged into a PHP 5 branch ... https://3v4l.org/8PsSN
		if (preg_match('/^\[([^\]]+)\]/', $str, $matches) && !is_php('7') && filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
			$str = 'ipv6.host' . substr($str, strlen($matches[1]) + 2);
		}

		return (filter_var('http://' . $str, FILTER_VALIDATE_URL) !== false);
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Email
	 *
	 * @param	string
	 * @return	bool
	 */
	public function valid_email($str)
	{
		if (function_exists('idn_to_ascii') && preg_match('#\A([^@]+)@(.+)\z#', $str, $matches)) {
			$domain = defined('INTL_IDNA_VARIANT_UTS46')
				? idn_to_ascii($matches[2], 0, INTL_IDNA_VARIANT_UTS46)
				: idn_to_ascii($matches[2]);

			if ($domain !== false) {
				$str = $matches[1] . '@' . $domain;
			}
		}

		return (bool) filter_var($str, FILTER_VALIDATE_EMAIL);
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Emails
	 *
	 * @param	string
	 * @return	bool
	 */
	public function valid_emails($str)
	{
		if (strpos($str, ',') === false) {
			return $this->valid_email(trim($str));
		}

		foreach (explode(',', $str) as $email) {
			if (trim($email) !== '' && $this->valid_email(trim($email)) === false) {
				return false;
			}
		}

		return true;
	}

	// --------------------------------------------------------------------

	/**
	 * Validate IP Address
	 *
	 * @param	string
	 * @param	string	'ipv4' or 'ipv6' to validate a specific IP format
	 * @return	bool
	 */
	public function valid_ip($ip, $which = '')
	{
		return $this->CI->input->valid_ip($ip, $which);
	}

	// --------------------------------------------------------------------

	/**
	 * Validate Password
	 *
	 * https://gist.github.com/natanfelles/f5d4b83161363d3e66f67078edeb7d7d
	 * 
	 * @param string $password
	 *
	 * @return bool
	 */
	public function valid_password($password = '')
	{
		$password = trim($password);

		$regex_lowercase = '/[a-z]/';
		$regex_uppercase = '/[A-Z]/';
		$regex_number = '/[0-9]/';
		$regex_special = '/[!@#$%^&*()\-_=+{};:,<.>§~]/';

		if (empty($password)) {
			
			$this->form_validation->set_message('valid_password', 'The {field} field is required.');

			return false;
		}

		if (preg_match_all($regex_lowercase, $password) < 1) {
			
			$this->form_validation->set_message('valid_password', 'The {field} field must be at least one lowercase letter.');

			return false;
		}

		if (preg_match_all($regex_uppercase, $password) < 1) {
			
			$this->form_validation->set_message('valid_password', 'The {field} field must be at least one uppercase letter.');

			return false;
		}

		if (preg_match_all($regex_number, $password) < 1) {
			
			$this->form_validation->set_message('valid_password', 'The {field} field must have at least one number.');

			return false;
		}

		if (preg_match_all($regex_special, $password) < 1) {
			
			$this->form_validation->set_message('valid_password', 'The {field} field must have at least one special character.' . ' ' . htmlentities('!@#$%^&*()\-_=+{};:,<.>§~'));

			return false;
		}

		if (strlen($password) < 5) {
			
			$this->form_validation->set_message('valid_password', 'The {field} field must be at least 5 characters in length.');

			return false;
		}

		if (strlen($password) > 32) {
			
			$this->form_validation->set_message('valid_password', 'The {field} field cannot exceed 32 characters in length.');

			return false;
		}

		return true;
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha
	 *
	 * @param	string
	 * @return	bool
	 */
	public function alpha($str)
	{
		return ctype_alpha($str);
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha-numeric
	 *
	 * @param	string
	 * @return	bool
	 */
	public function alpha_numeric($str)
	{
		return ctype_alnum((string) $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha-numeric w/ spaces
	 *
	 * @param	string
	 * @return	bool
	 */
	public function alpha_numeric_spaces($str)
	{
		return (bool) preg_match('/^[A-Z0-9 ]+$/i', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha-numeric with underscores and dashes
	 *
	 * @param	string
	 * @return	bool
	 */
	public function alpha_dash($str)
	{
		return (bool) preg_match('/^[a-z0-9_-]+$/i', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Numeric
	 *
	 * @param	string
	 * @return	bool
	 */
	public function numeric($str)
	{
		return (bool) preg_match('/^[\-+]?[0-9]*\.?[0-9]+$/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Integer
	 *
	 * @param	string
	 * @return	bool
	 */
	public function integer($str)
	{
		return (bool) preg_match('/^[\-+]?[0-9]+$/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Decimal number
	 *
	 * @param	string
	 * @return	bool
	 */
	public function decimal($str)
	{
		return (bool) preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Greater than
	 *
	 * @param	string
	 * @param	int
	 * @return	bool
	 */
	public function greater_than($str, $min)
	{
		return is_numeric($str) ? ($str > $min) : false;
	}

	// --------------------------------------------------------------------

	/**
	 * Equal to or Greater than
	 *
	 * @param	string
	 * @param	int
	 * @return	bool
	 */
	public function greater_than_equal_to($str, $min)
	{
		return is_numeric($str) ? ($str >= $min) : false;
	}

	// --------------------------------------------------------------------

	/**
	 * Less than
	 *
	 * @param	string
	 * @param	int
	 * @return	bool
	 */
	public function less_than($str, $max)
	{
		return is_numeric($str) ? ($str < $max) : false;
	}

	// --------------------------------------------------------------------

	/**
	 * Equal to or Less than
	 *
	 * @param	string
	 * @param	int
	 * @return	bool
	 */
	public function less_than_equal_to($str, $max)
	{
		return is_numeric($str) ? ($str <= $max) : false;
	}

	// --------------------------------------------------------------------

	/**
	 * Value should be within an array of values
	 *
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function in_list($value, $list)
	{
		return in_array($value, explode(',', $list), true);
	}

	// --------------------------------------------------------------------

	/**
	 * Is a Natural number  (0,1,2,3, etc.)
	 *
	 * @param	string
	 * @return	bool
	 */
	public function is_natural($str)
	{
		return ctype_digit((string) $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Is a Natural number, but not a zero  (1,2,3, etc.)
	 *
	 * @param	string
	 * @return	bool
	 */
	public function is_natural_no_zero($str)
	{
		return ($str != 0 && ctype_digit((string) $str));
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Base64
	 *
	 * Tests a string for characters outside of the Base64 alphabet
	 * as defined by RFC 2045 http://www.faqs.org/rfcs/rfc2045
	 *
	 * @param	string
	 * @return	bool
	 */
	public function valid_base64($str)
	{
		return (base64_encode(base64_decode($str)) === $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Prep data for form
	 *
	 * This function allows HTML to be safely shown in a form.
	 * Special characters are converted.
	 *
	 * CI deprecated @3.0.6	Not used anywhere within the framework and pretty much useless
	 * @param	mixed	$data	Input data
	 * @return	mixed
	 */
	public function prep_for_form($data)
	{
		if ($this->_safe_form_data === false or empty($data)) {
			return $data;
		}

		if (is_array($data)) {
			foreach ($data as $key => $val) {
				$data[$key] = $this->prep_for_form($val);
			}

			return $data;
		}

		return str_replace(["'", '"', '<', '>'], ['&#39;', '&quot;', '&lt;', '&gt;'], stripslashes($data));
	}

	// --------------------------------------------------------------------

	/**
	 * Prep URL
	 *
	 * @param	string
	 * @return	string
	 */
	public function prep_url($str = '')
	{
		if ($str === 'http://' or $str === '') {
			return '';
		}

		if (strpos($str, 'http://') !== 0 && strpos($str, 'https://') !== 0) {
			return 'http://' . $str;
		}

		return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Strip Image Tags
	 *
	 * @param	string
	 * @return	string
	 */
	public function strip_image_tags($str)
	{
		return $this->CI->security->strip_image_tags($str);
	}

	// --------------------------------------------------------------------

	/**
	 * Convert PHP tags to entities
	 *
	 * @param	string
	 * @return	string
	 */
	public function encode_php_tags($str)
	{
		return str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Reset validation vars
	 *
	 * Prevents subsequent validation routines from being affected by the
	 * results of any previous validation routine due to the CI singleton.
	 *
	 * @return	CI_Form_validation
	 */
	public function reset_validation()
	{
		$this->_field_data = [];
		$this->_error_array = [];
		$this->_error_messages = [];
		$this->error_string = '';
		return $this;
	}
}
