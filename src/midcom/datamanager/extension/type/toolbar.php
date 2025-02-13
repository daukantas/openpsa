<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 */

namespace midcom\datamanager\extension\type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use midcom;
use midcom\datamanager\controller;
use midcom\datamanager\extension\compat;

/**
 * Experimental autocomplete type
 */
class toolbar extends AbstractType
{
    /**
     *  Symfony 2.6 compat
     *
     * {@inheritDoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $this->configureOptions($resolver);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array
        (
            'operations' => array(),
            'mapped' => false
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $l10n = midcom::get()->i18n->get_l10n('midcom.helper.datamanager2');
        foreach ($options['operations'] as $operation => $button_labels)
        {
            foreach ((array) $button_labels as $key => $label)
            {
                if ($label == '')
                {
                    $label = "form submit: {$operation}";
                }
                $attributes = array
                (
                    'operation' => $operation,
                    'label' => $l10n->get($label),
                    'attr' => array('class' => 'submit ' . $operation)
                );
                if ($operation == controller::SAVE)
                {
                    //@todo Move to template?
                    $attributes['attr']['accesskey'] = 's';
                    $attributes['attr']['class'] .= ' save_' . $key;
                }
                else if ($operation == controller::CANCEL)
                {
                    //@todo Move to template?
                    $attributes['attr']['accesskey'] = 'd';
                    $attributes['attr']['formnovalidate'] = true;
                }

                $builder->add($operation . $key, compat::get_type_name('submit'), $attributes);
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * Symfony < 2.8 compat
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'toolbar';
    }
}