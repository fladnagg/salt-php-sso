<?php
/**
 * Handler class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dto
 */
namespace sso;

/**
 * Parent class of application plugin handlers
 */
abstract class Handler {

	/**
	 * @var string the path of the application
	 */
	protected $path;

	/**
	 * Set the path of the application
	 * @param string $path Path of the application
	 */
	public function setPath($path) {
		$this->path = $path;
	}

	/**
	 * Initialize an application for a user logged in SSO.
	 *
	 * After this call, the used have to be logged in application.
	 *
	 * @param AuthUser $user The user
	 * @param SsoClient $sso SSO instance
	 */
	abstract public function init(AuthUser $user, SsoClient $sso);
}
