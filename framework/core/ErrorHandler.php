<?php

/**
 * Beautiful Error Handler for Webby
 * 
 * Forked From https://github.com/brqt/
 * 
 * @author Kwame Oteng Appiah-Nti <developerkwame@gmail.com>
 * @link  https://github.com/sylynder/error-handler
 * @license  MIT
 * 
 */
class ErrorHandler
{
	/**
	 * Error constants to check
	 */
	private const ERRORS = E_ALL & ~E_NOTICE;

	/**
     * Parse Error Constant
     */
    private const PARSE_ERROR = 4;

    /**
     * Error Zero Constant
     */
    private const ERROR_ZERO = 0;


	/**
	 * For plates template engine
	 *
	 * @var string
	 */
	private $evaluatedFile = null;

	public function __construct()
	{
		\register_shutdown_function([$this, 'checkForFatal']);
		\set_error_handler([$this, 'logError'], self::ERRORS);
		\set_exception_handler([$this, 'logException']);
	}

	/**
	 * Check for Fatal Errors
	 *
	 * @return void
	 */
	public function checkForFatal()
	{
		$error = error_get_last();

		if (isset($error) && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING)))
		{
			$this->logError($error["type"], $error["message"], $error["file"], $error["line"]);
		}
	}

	/**
	 * Log Error Output
	 *
	 * @param string|int $num
	 * @param string $str
	 * @param string $file
	 * @param string $line
	 * @param mixed $context
	 * @return void
	 */
	public function logError($num, $str, $file, $line, $context = null)
	{
		get_instance()->load->library('session');

		if ($this->checkEvaluated($file)) {
			$this->evaluatedFile = get_instance()
				->session
				->userdata('__view_path');
		}
		
		if ($this->evaluatedFile) {
			$file = $this->evaluatedFile;
		}
		
		$_error = & load_class('Exceptions', 'core');
		$_error->log_exception($num, $str, $file, $line);
		
		$this->logException(new \ErrorException($str, $num, $num, $file, $line));
		
	}

	/**
	 * Log Exception Output
	 *
	 * @param mixed $e
	 * @return void
	 */
	public function logException($e)
	{
		if ($this->checkEvaluated($e->getFile())) {
			$this->evaluatedFile = get_instance()
				->session
				->userdata('__view_path');
		}
		
		$file = ($this->evaluatedFile) ? $this->evaluatedFile : $e->getFile();
		$line = $e->getLine();
		$message = $e->getMessage();
		$num = $e->getCode();

		if ($num == self::ERROR_ZERO) { 
			$num = self::PARSE_ERROR;
		}

		$_error = & load_class('Exceptions', 'core');
		$_error->log_exception($num, 'Exception: ' . $message, $file, $line);

		$trace = '';
		$traceArr = $e->getTrace();

		foreach ($traceArr as $key => $traced) {

			if (!array_key_exists('line', $traced) || !array_key_exists('file', $traced)) continue;
			
			$trace .= '<tr>';
			$trace .= '<td>' . $traced['line'] . '</td>';
			$trace .= '<td>' . $traced['file'] . '</td>';
			$trace .= '<td>' . (!array_key_exists('class', $traced) ? '' : $traced['class']) . '</td>';
			$trace .= '<td>' . (!array_key_exists('function', $traced) ? '' : $traced['function']) . '</td>';
			$trace .= '</tr>';
		}

		$extra = '';

		try {
			$extraArr = $e->getExtra();

			foreach ($extraArr as $key => $value)
			{
				$extra .= '<tr>';
				$extra .= '<td>' . $key . '</td>';
				$extra .= '<td>' . $value . '</td>';
				$extra .= '</tr>';
			}
		} catch(\Throwable $th) {}

		$code = '';

		try {

			$i = 0;
			$iline = 0;
			$line_start = (int) $e->getLine() - 10;

			if ($line_start < 0) {
				$line_start = 0;
			}

			if (!$this->evaluatedFile) {

				$source = file($file);
				$source = array_slice($source, $line_start > 1 ? $line_start - 1 : $line_start, 20);

				foreach ($source as $key => $line) {

					$line_number = $line_start + $key;
					$class = ($e->getLine() == $line_number) ? 'bg-danger' : '';
					$class2 = in_array($line_number, [$e->getLine() - 1, $e->getLine() + 1]) ? 'bg-danger2' : '';
					$code .= "\n<tr class=\"{$class}\">";
					$code .= "\n<td class=\"code-line_number\">" . $line_number . '</td>';
					$code .= "\n<td class=\"{$class2}\">" . $this->highlight($line) . '</td>';
					$code .= "\n</tr>";
				}

			}

			if ($this->evaluatedFile) {

				$source = file($file);
				$source = array_slice($source, $line_start > 1 ? $line_start - 1 : $line_start, 20);

				foreach ($source as $key => $line) {

					$line_number = $line_start + $key;
					$class = ($e->getLine() == $line_number) ? 'bg-danger' : '';
					$class2 = in_array($line_number, [$e->getLine() - 1, $e->getLine() + 1]) ? 'bg-danger2' : '';
					$code .= "\n<tr class=\"{$class}\">";
					$code .= "\n<td class=\"code-line_number\">" . $line_number . '</td>';
					$code .= "\n<td class=\"{$class2}\">" . $this->highlight($line) . '</td>';
					$code .= "\n</tr>";
				}

				get_instance()
					->session
					->unset_userdata('__view_path');
				
				$this->evaluatedFile = '';
			}

		} catch(\Throwable $th) {}

		$html = $this->getHtml();
		$values = [
			'{title}' => 'Webby Error', 
			'{message}' => $e->getMessage(), 
			'{class}' => get_class($e), 
			'{file}' => $file,
			'{line}' => $e->getLine(), 
			'{uri}' => $this->getUrl(), 
			'{trace}' => $trace, 
			'{extra}' => $extra, 
			'{code}' => $code, 
			'{extra_status}' => $extra ? '' : 'hide',
		];

		print str_replace(array_keys($values) , $values, $html);
		exit;
	}

	private function checkEvaluated($file)
	{
		$evaluated = false;

		if (_evaluated($file)) {
			$evaluated = true;
		}

		return $evaluated;
	}

	/**
	 * Get Url
	 *
	 * @return string
	 */
	private function getUrl()
	{
		$protocol = (isset($_SERVER['HTTPS']) && @$_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
		return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	/**
	 * Highlight Code Output
	 *
	 * @param string $code
	 * @return string
	 */
	private function highlight(string $code)
	{
		$code = str_replace("\n", "", $code);
		$code = htmlspecialchars($code, ENT_QUOTES, 'UTF-8', true);

		$code = preg_replace("/(&quot;(.*)&quot;)/ui", "<span class=\"code-string\">$1</span>", $code);
		$code = preg_replace("/(&#039;(.*)&#039;)/ui", "<span class=\"code-string\">$1</span>", $code);

		$code = preg_replace("/(if|for|switch|elseif|while|foreach)(\s*\()/ui", "<span class=\"code-core\">$1</span>$2", $code);
		$code = preg_replace("/((function|public|class|private|const|use|namespace|throw|new|require_once|require|include|include_once)\s+)/ui", "<span class=\"code-core\">$1</span>", $code);
		$code = preg_replace("/((null|true|false|else|continue|break|self::|self|static::|static|\$this)\s*)/ui", "<span class=\"code-core\">$1</span>", $code);
		$code = preg_replace("/((echo|return|extends|implements|protected)\s+)/ui", "<span class=\"code-core\">$1</span>", $code);

		$code = preg_replace("/([a-z_]+[a-z_0-9]*\s*)(\()/ui", "<span class=\"code-func\">$1</span>$2", $code);
		$code = preg_replace("/([a-z_]+\s*)(\()/ui", "<span class=\"code-func\">$1</span>$2", $code);

		$code = preg_replace("/(-&gt;)([a-z]+[a-z0-9_]*)/ui", "$1<span class=\"code-variable\">$2</span>", $code);
		$code = preg_replace("/(\\$([a-z_]+[a-z0-9_]*))/ui", "<span class=\"code-variable\">$1</span>", $code);

		$code = preg_replace("/(\)|\(|\}|\{)/ui", "<span class=\"code-brace\">$1</span>", $code);
		$code = preg_replace("/(\]|\[)/ui", "<span class=\"code-arr\">$1</span>", $code);

		$code = preg_replace("/((\/|\s)\*+(.*))/ui", "<span class=\"code-comment\">$1</span>", $code);
		$code = preg_replace("/^(\*+(.*))/ui", "<span class=\"code-comment\">$1</span>", $code);
		$code = preg_replace("/(\/\/(.*)$)/ui", "<span class=\"code-comment\">$1</span>", $code);

		return $code;
	}

	/**
	 * Get Html Output
	 *
	 * @return string
	 */
	private function getHtml()
	{
		return '

		<!DOCTYPE html>
			<html lang="ar">
				<head>
					<meta charset="UTF-8">
					<meta name="viewport" content="width=device-width, initial-scale=1.0">
					<meta http-equiv="X-UA-Compatible" content="ie=edge">
					<title>Handle - {title}</title>
					<link href="https://fonts.googleapis.com/css?family=Nunito|Courier+Prime|Inconsolata&display=swap" rel="stylesheet">
					<style>
						.code-comment,
						.code-comment * {
							color: #666 !important;
						}

						.code-line_number {
							color: #555;
							background: #2c3136;
							min-width: 10px;
							user-select: none;
							text-align: center;
						}

						.code-string *,
						.code-string {
							color: #76B256;
						}

						.code-core {
							color: #DB7C33;
						}

						.code-func {
							color: #F0C65F;
						}

						.code-variable *,
						.code-variable {
							color: inherit;
							color: #BA8ECE;
						}

						.code-brace {
							color: #DA70D6;
						}

						.code-arr {
							color: #96f9fe;
						}

						* {
							box-sizing: border-box;
						}

						body {
							background-color: #EFEEF5;
							color: #666;
							font-family: \'Nunito\', sans-serif;
							font-size: 15px;
						}

						a,
						a:link,
						a:visited,
						a:active {
							color: #99c4d0;
							text-decoration: underline;
						}

						#error-box,
						#trace-box,
						#code-box {
							border-radius: 5px;
							border:2px solid #48049c;
							display: block;
							margin: 25px auto;
							width: 60%;
							min-width: 70vw;
							background-color: #fff;
							padding: 10px;
							box-shadow: 0 .15rem 1.75rem 0 rgba(58, 59, 69, .15) !important;
						}

						.title {
							margin-top: -5px;
							margin-right: -5px;
							margin-left: -5px;
							padding: 10px;
							background-color: #f9f9f9;
							display: flex;
							flex-direction: column;
						}

						p {
							padding: 5px 20px;
						}

						.bg-danger td:first-of-type {
							background-color: #ce0000 !important;
							color: #f0f0f0;
							position: relative;
						}

						.bg-danger td:first-of-type:after {
							position: absolute;
							right: -16px;
							top: 0;
							content: "";
							width: 0;
							height: 1px;
							border-width: 8px;
							border-color: #ce0000;
							border-style: solid;
							border-top-color: transparent;
							border-bottom-color: transparent;
							border-right-color: transparent;
						}

						.bg-danger {
							background-color: rgba(255, 100, 100, .15) !important;
							color: #f0f0f0;
						}

						.bg-danger2 {
							background-color: rgba(255, 100, 100, .05) !important;
							color: #f0f0f0;
						}

						.small {
							font-size: 11px;
							color: #999;
							padding: 5px 10px;
						}

						.file-line {
							padding: 5px 0;
							color: #aaa;
							font-size: 13px;
							font-weight: normal;
						}

						#rights {
							display: block;
							margin: 5px auto 20px auto;
							text-align: center;
							font-size: 12px;
							color: #888;
						}

						#rights,
						#rights a {
							color: #888;
						}

						.overflow {
							overflow-x: auto;
							padding-bottom: 15px;
						}

						#trace-box .title {
							color: #999;
							border-bottom: 2px solid #a0a4fc;
						}

						#code-box pre {
							padding: 0 !important;
							margin: 0 !important;
							font-family: \'Inconsolata\', \'Courier Prime\', monospace;
							color: #ccc;
							font-size: 12px !important;
							background-color: #23272C;
							border-radius: 5px;
						}

						#code-box .title2 {
							background-color: #23272C;
							border-radius: 5px;
							padding: 10px;
							color: #eee;
							margin: 0 0 10px 0;
						}

						#code-box pre ::selection {
							background: #999 !important;
						}

						#code-box table {
							border-collapse: collapse;
						}

						#code-box table td:nth-of-type(2) {
							padding-left: 15px;
						}

						#code-box {
							background-color: #fff;
							padding: 10px;
						}

						table {
							width: 100%;
						}

						table td {
							padding: 2px 5px;
						}

						table thead td {
							background-color: #f0f0f0;
						}

						table.table tbody tr:nth-child(even) {
							background-color: #f9f9f9;
						}

						.hide {
							display: none !important;
						}

						.bordered {
							background-color: #fefefe;
							border: 1px solid #eee;
						}

						.extraInfo {
							margin: 10px 20px;
						}

						.extraInfo *:not(h3) {
							font-size: 13px;
						}

						.extraInfo thead td {
							padding: 0;
						}

						.extraInfo .title {
							margin: 0;
						}
					</style>
				</head>
				<body>
					<div id="error-box">
						<h3 class="title">
							<span>{class} &nbsp; </span>
							<span class="file-line">{file} : {line}</span>
						</h3>
						<p>{message}</p>
						<div  class="{extra_status} overflow bordered extraInfo">
							<table class="table">
								<thead>
									<tr>
										<td colspan="2">
											<h3 class="title">Extra info</h3>
										</td>
									</tr>
								</thead>
								<tbody>
									{extra}
								</tbody>
							</table>
						</div>
						<div class="small">{uri}</div>
					</div>
					<div id="code-box">
						<h3 class="title2">
							<span>Code</span>
							&nbsp;
							<span class="file-line">{file}:{line} &nbsp;|&nbsp;</span>
							<a href="vscode://file/{file}:{line}"><span class="file-line">Open in VSCode</span></a>
						</h3>
						<pre class="overflow"><table>
							<tbody>
							<tr><td class="code-line_number">&nbsp;</td><td>&nbsp;</td</tr>
							{code}
							<tr><td class="code-line_number">&nbsp;</td><td>&nbsp;</td</tr>
							</tbody>
						</table></pre>
					</div>
					<div id="trace-box">
						<h3 class="title">Trace</span></h3>
						<div class="overflow">
							<table class="table">
								<thead>
									<tr>
										<td>Line</td>
										<td>File</td>
										<td>Class</td>
										<td>Function</td>
									</tr>
								</thead>
								<tbody>
									{trace}
								</tbody>
							</table>
						</div>
					</div>
					<div id="rights">
						Webby Error Handler By:
						<a target="_blank" href="https://github.com/sylynder/error-handler">
							Sylynder © <script>document.write((new Date).getFullYear())</script>
						</a>
					</div>
				</body>
			</html>
		';
	}
}
