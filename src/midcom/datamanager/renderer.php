<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 */

namespace midcom\datamanager;

use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use midcom;
use midcom_services_i18n_l10n;

/**
 * Experimental renderer class
 */
class renderer extends FormRenderer
{
    /**
     * @var \midcom_services_i18n_l10n
     */
    protected $l10n;

    public function set_l10n(midcom_services_i18n_l10n $l10n)
    {
        $this->l10n = $l10n;
    }

    public function set_template(FormView $view, template\base $template)
    {
        $this->getEngine()->setTheme($view, $template);
    }

    public function start(FormView $view, array $attributes = array())
    {
        return $this->renderBlock($view, 'form_start', $attributes);
    }

    public function end(FormView $view, array $attributes = array())
    {
        return $this->renderBlock($view, 'form_end', $attributes);
    }

    public function widget(FormView $view, array $attributes = array())
    {
        return $this->searchAndRenderBlock($view, 'widget', $attributes);
    }

    public function block(FormView $view, $name, array $attributes = array())
    {
        return $this->renderBlock($view, $name, $attributes);
    }

    public function rest(FormView $view, array $attributes = array())
    {
        return $this->searchAndRenderBlock($view, 'rest', $attributes);
    }

    public function errors(FormView $view, array $attributes = array())
    {
        return $this->searchAndRenderBlock($view, 'errors', $attributes);
    }

    public function row(FormView $view, array $attributes = array())
    {
        return $this->searchAndRenderBlock($view, 'row', $attributes);
    }

    public function label(FormView $view, array $attributes = array())
    {
        return $this->searchAndRenderBlock($view, 'label', $attributes);
    }

    public function humanize($string)
    {
        $translate_string = strtolower($string);

        if ($this->l10n->string_available($translate_string))
        {
            return $this->l10n->get($translate_string);
        }
        if (midcom::get()->i18n->get_l10n('midcom.datamanager')->string_available($translate_string))
        {
            return midcom::get()->i18n->get_l10n('midcom.datamanager')->get($translate_string);
        }
        if (midcom::get()->i18n->get_l10n('midcom')->string_available($translate_string))
        {
            return midcom::get()->i18n->get_l10n('midcom')->get($translate_string);
        }

        return $string;
    }

}