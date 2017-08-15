<?php
/**
 * RecommendedTheme class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\themes\recommended
 */
namespace sso;

/**
 * Recommended Theme.
 *
 * Internal class
 */
class RecommendedTheme extends Theme {

	/**
	 * {@inheritDoc}
	 * @see \sso\Theme::description()
	 */
	public function description() {
		return L::label_theme_description_recommended;
	}
}

