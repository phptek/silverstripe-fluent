<?php

/**
 * Fluent extension for main CMS admin
 *
 * @see LeftAndMain
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentLeftAndMain extends LeftAndMainExtension
{
    /**
     * @var array
     */
    private static $allowed_actions = array(
        'untranslate'
    );
    
    public function init()
    {
        $dirName = basename(dirname(dirname(dirname(__FILE__))));
        $locales = json_encode(Fluent::locale_names());
        $locale = json_encode(Fluent::current_locale());
        $param = json_encode(Fluent::config()->query_param);
        $buttonTitle = json_encode(_t('Fluent.ChangeLocale', 'Change Locale'));

        // Force the variables to be written to the head, to ensure these are available for other scripts to pick up.
        Requirements::insertHeadTags(<<<EOT
<script type="text/javascript">
//<![CDATA[
	var fluentLocales = $locales;
	var fluentLocale = $locale;
	var fluentParam = $param;
	var fluentButtonTitle = $buttonTitle;
//]]>
</script>
EOT
            , 'FluentHeadScript'
        );
        Requirements::javascript("$dirName/javascript/fluent.js");
        Requirements::css("$dirName/css/fluent.css");
    }

    /**
     * Controller method invoked via AJX within the CMS that allows the current model's Fluent enabled fields to be
     * reset to their DB defaults:
     * 
     * - Fields with a default of NULL will be set to NULL
     * - Fields with a non-NULL default, will be reset to that default
     *
     * @return bool The return value can be used by receiving JS logic to determine if any problems ocurred
     */
    public function untranslate($locales)
    {
        $request = $this->getOwner()->getRequest();
        if (!$request->isAjax()) {
            return false;
        }
        
        $class = $request->postVar('ClassName');
        $id = $request->postVar('ID');
        if (!$class || !$id) {
            return false;
        }
        
        $object = DataObject::get_by_id($class, $id);
        if (!$fields = $object->getTranslatedDBFields()) {
            return false;
        }
        
        foreach ($fields as $field) {
            $field->setValue($field->nullValue());
        }
        
        // Where applicable, CMS authors will still need to Save & Publish just like any other edit
        return (bool) $object->write();
    }
}
