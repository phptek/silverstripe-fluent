<?php

/**
 * SiteTree extension class for translatable objects
 *
 * @see SiteTree
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentSiteTree extends FluentExtension {

	public function MetaTags(&$tags) {
		$tags .= $this->owner->renderWith('FluentSiteTree_MetaTags');
	}

	public function onBeforeWrite() {
		// Fix issue with MenuTitle not containing the correct translated value
		$this->owner->setField('MenuTitle', $this->owner->MenuTitle);
        
        // Set default value on each fluent-aware field
        $this->owner->setTranslatedFieldDefaults();

		parent::onBeforeWrite();
	}

	/**
	 * Ensure that the controller is correctly initialised
	 *
	 * @param ContentController $controller
	 */
	public function contentcontrollerInit($controller) {
		Fluent::install_locale();
	}

	public function updateRelativeLink(&$base, &$action) {

		// Don't inject locale to subpages
		if($this->owner->ParentID && SiteTree::config()->nested_urls) {
			return;
		}

		// For blank/temp pages such as Security controller fallback to querystring
		$locale = Fluent::current_locale();
		if(!$this->owner->exists()) {
			$base = Controller::join_links($base, '?'.Fluent::config()->query_param.'='.urlencode($locale));
			return;
		}

		// Check if this locale is the default for its own domain
		$domain = Fluent::domain_for_locale($locale);
		if($locale === Fluent::default_locale($domain)) {
			// For home page in the default locale, do not alter home url
			if($base === null) return;

			// For all pages on a domain where there is only a single locale,
			// then the domain itself is sufficient to distinguish that domain
			// See https://github.com/tractorcow/silverstripe-fluent/issues/75
			$domainLocales = Fluent::locales($domain);
			if(count($domainLocales) === 1) return;
		}

		// Simply join locale root with base relative URL
		$localeURL = Fluent::alias($locale);
		$base = Controller::join_links($localeURL, $base);
	}

	public function LocaleLink($locale) {

		// For blank/temp pages such as Security controller fallback to querystring
		if(!$this->owner->exists()) {
			$url = Controller::curr()->getRequest()->getURL();
			return Controller::join_links($url, '?'.Fluent::config()->query_param.'='.urlencode($locale));
		}

		return parent::LocaleLink($locale);
	}

	public function updateCMSFields(FieldList $fields) {
		parent::updateCMSFields($fields);

		// Fix URLSegment field issue for root pages
		if(!SiteTree::config()->nested_urls || empty($this->owner->ParentID)) {
			$baseLink = Director::absoluteURL(Controller::join_links(
				Director::baseURL(),
				Fluent::alias(Fluent::current_locale()),
				'/'
			));
			$urlsegment = $fields->dataFieldByName('URLSegment');
			$urlsegment->setURLPrefix($baseLink);
		}
	}
    
    /**
     * 
     * Copy the value of each non-locale-aware field to the equivalent locale-aware 
     * field when the following are true:
     * 
     *  1. Any given field is currently NULL
     *  2. This record is not being written in the default_locale
     * 
     * Notes: 
     * - Usually invoked via {@link $this->onBeforeWrite()}.
     * - This should really be a private method as we don't want any TDH to set this. Needs to be public for tests :-(
     * 
     * @todo See TODO below
     * @return void
     */
    public function setTranslatedFieldDefaults() {
        // Userland config can still insist on default behaviour. Also useful for testing
        $mode = Fluent::config()->initial_field_mode;
        if(!$mode || $mode === 'default') {
            var_dump($mode);
            die('#TEST2');
            return;
        }
                
        $fluentFields = array_keys(FluentExtension::translated_fields_for(get_class($this)));
        $isDefault = (Fluent::current_locale() === Fluent::config()->default_locale);
        
        foreach($fluentFields as $fieldName) {
            $fluentField = $this->owner->getField($fieldName);
            // TODO is there a static method on Fluent.php to get the original, untranslated field name?
            $origField = $this->owner->getField(preg_replace("#_.+#", '', $fieldName));
            $fluentFieldIsNull = is_null($fluentField->value());
            $origFieldIsNull = is_null($origField->value());
            
            if($fluentFieldIsNull && !$origFieldIsNull && !$isDefault) {
                $fluentField->setValue($origField->value());
            }
        }
    }
}
