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

    public function clean_shortname()
    {
        $shortname = mb_strtolower($this->cleaned_data['shortname']);
        if (preg_match('/[^\-A-Za-z0-9]/', $shortname)) {
            throw new Pluf_Form_Invalid(__('This shortname contains illegal characters, please use only letters, digits and dash (-).'));
        }
        if (mb_substr($shortname, 0, 1) == '-') {
            throw new Pluf_Form_Invalid(__('The shortname cannot start with the dash (-) character.'));
        }
        if (mb_substr($shortname, -1) == '-') {
            throw new Pluf_Form_Invalid(__('The shortname cannot end with the dash (-) character.'));
        }

        return trim($shortname);
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

            // The trim really isn't needed - but does ensure that no whitespace will end up in the name
            $request->shortname = $this->cleaned_data['shortname'];
            $request->repotype = $this->cleaned_data['repotype'];
            $request->desc = $this->cleaned_data['desc'];
            $request->submitter = $this->user;
            $request->create();

            $from_email = Pluf::f('from_email');
            $email = new Pluf_Mail($from_email, "",
                __('[Action Required] New Repo Request')); //send to no-one but admins will be BCCed
            $email->addTextMessage(sprintf("%s has requested a new repo with the name of %s - please login and approve or deny it", $this->user, $request->shortname));
            $email->sendMail();

            return true;
        } catch (Exception $e)
        {
            return false;
        }
    }

}