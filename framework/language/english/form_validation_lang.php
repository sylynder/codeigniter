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

$lang['form_validation_required']        = 'The {field} field is required.';
$lang['form_validation_isset']            = 'The {field} field must have a value.';
$lang['form_validation_valid_email']        = 'The {field} field must contain a valid email address.';
$lang['form_validation_valid_emails']        = 'The {field} field must contain all valid email addresses.';
$lang['form_validation_valid_url']        = 'The {field} field must contain a valid URL.';
$lang['form_validation_valid_ip']        = 'The {field} field must contain a valid IP.';
$lang['form_validation_valid_base64']        = 'The {field} field must contain a valid Base64 string.';
$lang['form_validation_min_length']        = 'The {field} field must be at least {param} characters in length.';
$lang['form_validation_max_length']        = 'The {field} field cannot exceed {param} characters in length.';
$lang['form_validation_exact_length']        = 'The {field} field must be exactly {param} characters in length.';
$lang['form_validation_alpha']            = 'The {field} field may only contain alphabetical characters.';
$lang['form_validation_alpha_numeric']        = 'The {field} field may only contain alpha-numeric characters.';
$lang['form_validation_alpha_numeric_spaces']    = 'The {field} field may only contain alpha-numeric characters and spaces.';
$lang['form_validation_alpha_dash']        = 'The {field} field may only contain alpha-numeric characters, underscores, and dashes.';
$lang['form_validation_numeric']        = 'The {field} field must contain only numbers.';
$lang['form_validation_is_numeric']        = 'The {field} field must contain only numeric characters.';
$lang['form_validation_integer']        = 'The {field} field must contain an integer.';
$lang['form_validation_regex_match']        = 'The {field} field is not in the correct format.';
$lang['form_validation_matches']        = 'The {field} field does not match the {param} field.';
$lang['form_validation_differs']        = 'The {field} field must differ from the {param} field.';
$lang['form_validation_is_unique']         = 'The {field} field must contain a unique value.';
$lang['form_validation_exists']         = 'The {field} field exists already.';
$lang['form_validation_is_natural']        = 'The {field} field must only contain digits.';
$lang['form_validation_is_natural_no_zero']    = 'The {field} field must only contain digits and must be greater than zero.';
$lang['form_validation_decimal']        = 'The {field} field must contain a decimal number.';
$lang['form_validation_less_than']        = 'The {field} field must contain a number less than {param}.';
$lang['form_validation_less_than_equal_to']    = 'The {field} field must contain a number less than or equal to {param}.';
$lang['form_validation_greater_than'] = 'The {field} field must contain a number greater than {param}.';
$lang['form_validation_greater_than_equal_to'] = 'The {field} field must contain a number greater than or equal to {param}.';
$lang['form_validation_error_message_not_set'] = 'Unable to access an error message corresponding to your field name {field}.';
$lang['form_validation_in_list']        = 'The {field} field must be one of: {param}.';
$lang['form_validation_honey_check']  = 'The {field} field must be filled with style.';
$lang['form_validation_honey_time']  = 'The {field} field may only be needed once.';
$lang['form_validation_is_exactly']  = "The {field} field does not contain expected values.";
$lang['form_validation_is_not'] = "The {field} field does not accept entered value.";
$lang['form_validation_valid_json'] = "The {field} field must be a valid json.";
$lang['form_validation_valid_hour'] = "The {field} field must be a valid hour.";
$lang['form_validation_valid_date'] = "The {field} field must be a valid date.";
$lang['form_validation_valid_range_date'] = "The {field} field must be a valid date range.";
$lang['form_validation_valid_latitude']   = "The %s latitude doesn't have a correct position.";
$lang['form_validation_valid_longitude']  = "The %s longitude doesn't have a correct position.";
$lang['form_validation_valid_latlong']    = "The %s map coodinates doesn't have a correct position.";

// --------------------------------------------------------------------

$lang['file_required']                    = 'The {field} field needs a file to upload.';
$lang['file_max_size']                    = "The {field} file is too big (max size is {param}).";
$lang['file_min_size']                    = "The {field} file is too small (min size is {param}).";
$lang['file_allowed_type']                = "The {field} file allowed should be a/an {param}.";
$lang['file_disallowed_type']             = "The {field} file type {param}, is not allowed.";
$lang['file_image_maxdim']                = "The dimensions of the {field} file are too big.";
$lang['file_image_mindim']                = "The dimensions of the {field} file are too small.";
$lang['file_image_exactdim']              = "The {field} file doesn't have the right dimensions.";
$lang['error_max_filesize_phpini']        = "The uploaded file exceeds the maximum upload size of PHP."; // from php.ini
$lang['error_max_filesize_form']          = "The uploaded file exceeds the MAX_FILE_SIZE allowed."; // from form validation
$lang['error_partial_upload']             = "The file is only partially uploaded.";
$lang['error_temp_dir']                   = "Temp directory error.";
$lang['error_disk_write']                 = "Disk write error.";
$lang['error_stopped']                    = "File upload stopped by extension";
$lang['error_unexpected']                 = "Unexpected file upload error. Error: {field}";
$lang['honey_check']                      = 'The {field} field must be filled with style.';
$lang['honey_time']                       = 'The {field} field may only be needed once.';
