<?php

/**
 * MinimalCMS Error Class
 *
 * A simple container for error codes, messages, and associated data.
 *
 * @package MinimalCMS
 * @since   {version}
 */

/**
 * Class MC_Error
 *
 * Collects error codes with messages and optional data.
 * Return this from functions instead of throwing exceptions for expected errors.
 *
 * @since {version}
 */
class MC_Error
{
	/**
	 * Error messages keyed by error code.
	 *
	 * @since {version}
	 * @var array<string, string[]>
	 */
	protected array $errors = array();

	/**
	 * Arbitrary data keyed by error code.
	 *
	 * @since {version}
	 * @var array<string, mixed>
	 */
	protected array $error_data = array();

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param string $code    Optional. Initial error code.
	 * @param string $message Optional. Initial error message.
	 * @param mixed  $data    Optional. Initial error data.
	 */
	public function __construct(string $code = '', string $message = '', mixed $data = '')
	{
		if ('' !== $code) {
			$this->add($code, $message, $data);
		}
	}

	/**
	 * Add an error or additional message to an existing error code.
	 *
	 * @since {version}
	 *
	 * @param string $code    Error code.
	 * @param string $message Human-readable message.
	 * @param mixed  $data    Optional associated data.
	 * @return void
	 */
	public function add(string $code, string $message, mixed $data = ''): void
	{
		$this->errors[$code][] = $message;

		if ('' !== $data) {
			$this->error_data[$code] = $data;
		}
	}

	/**
	 * Retrieve all error codes.
	 *
	 * @since {version}
	 *
	 * @return string[]
	 */
	public function get_error_codes(): array
	{
		if (empty($this->errors)) {
			return array();
		}

		return array_keys($this->errors);
	}

	/**
	 * Get the first error code.
	 *
	 * @since {version}
	 *
	 * @return string Error code or empty string.
	 */
	public function get_error_code(): string
	{
		$codes = $this->get_error_codes();
		return $codes[0] ?? '';
	}

	/**
	 * Get all messages for a given error code.
	 *
	 * @since {version}
	 *
	 * @param string $code Error code. Empty returns messages for the first code.
	 * @return string[]
	 */
	public function get_error_messages(string $code = ''): array
	{
		if ('' === $code) {
			$code = $this->get_error_code();
		}

		return $this->errors[$code] ?? array();
	}

	/**
	 * Get the first message for a given error code.
	 *
	 * @since {version}
	 *
	 * @param string $code Error code.
	 * @return string Error message or empty string.
	 */
	public function get_error_message(string $code = ''): string
	{
		$messages = $this->get_error_messages($code);
		return $messages[0] ?? '';
	}

	/**
	 * Get the data associated with an error code.
	 *
	 * @since {version}
	 *
	 * @param string $code Error code.
	 * @return mixed Error data or empty string.
	 */
	public function get_error_data(string $code = ''): mixed
	{
		if ('' === $code) {
			$code = $this->get_error_code();
		}

		return $this->error_data[$code] ?? '';
	}

	/**
	 * Check whether any errors have been registered.
	 *
	 * @since {version}
	 *
	 * @return bool
	 */
	public function has_errors(): bool
	{
		return !empty($this->errors);
	}

	/**
	 * Remove all messages and data for a given error code.
	 *
	 * @since {version}
	 *
	 * @param string $code Error code to remove.
	 * @return void
	 */
	public function remove(string $code): void
	{
		unset($this->errors[$code], $this->error_data[$code]);
	}
}
