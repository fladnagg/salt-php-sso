<?php namespace sso;

abstract class Handler {

	protected $path;

	public function setPath($path) {
		$this->path = $path;
	}
	
	/**
	 * @param AuthUser $user
	 */
	abstract public function init(AuthUser $user, SsoClient $sso);
}
