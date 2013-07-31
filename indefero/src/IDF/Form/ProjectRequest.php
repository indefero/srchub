<?php
class IDF_Form_ProjectRequest extends Pluf_Form
{
    public $user = null;

    public function initFields($extra=array())
    {

        $choices = array();
        $options = array(
            'git' => __('git'),
            'svn' => __('Subversion'),
            'mercurial' => __('mercurial'),
            'mtn' => __('monotone'),
        );
        foreach (Pluf::f('allowed_scm', array()) as $key => $class) {
            $choices[$options[$key]] = $key;
        }

        $this->fields['shortname'] = new Pluf_Form_Field_Varchar(
            array('required' => true,
                'label' => __('Name'),
                'initial' => '',
                'help_text' => __('This will be the name of your repo and of your project - however - you can change the project name later.'),
            ));

        $this->fields['repotype'] = new Pluf_Form_Field_Varchar(
            array('required' => true,
                'label' => __('Repository type'),
                'initial' => 'git',
                'widget_attrs' => array('choices' => $choices),
                'widget' => 'Pluf_Form_Widget_SelectInput',
            ));

        $this->fields['desc'] = new Pluf_Form_Field_Varchar(
            array('required' => true,
                'label' => __('Short description'),
                'help_text' => __('A one line description of the project.'),
                'initial' => '',
                'widget_attrs' => array('size' => '35'),
            ));

        $this->user = $extra['user'];
    }


    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }

        $checksql = new Pluf_SQL(sprintf("shortname='%s'", $this->cleaned_data['shortname']));
        $requestcheck = Pluf::factory("IDF_Project")->getCount(array('filter'=>$checksql->gen()));
        if ($requestcheck == 1)
            return false;
        try
        {
            $request = new IDF_ProjectRequest();
            $request->shortname = $this->cleaned_data['shortname'];
            $request->repotype = $this->cleaned_data['repotype'];
            $request->desc = $this->cleaned_data['desc'];
            $request->submitter = $this->user;
            $request->create();
            return true;
        } catch (Exception $e)
        {
            return false;
        }
    }

}