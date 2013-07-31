<?php
/*
 * This is the model for people to request a repo
 * An administrator can then approve/deny the repo
 *
 *
 */

class IDF_ProjectRequest extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_projectrequest';
        $this->_a['model'] = __CLASS__;

        $this->_a['cols'] = array(
            // It is mandatory to have an "id" column.
            'id' =>
            array(
                'type' => 'Pluf_DB_Field_Sequence',
                'blank' => true,
            ),
            'shortname' =>
            array(
                'type' => 'Pluf_DB_Field_Varchar',
                'blank' => false,
                'size' => 50,
                'verbose' => __('shortname'),
                'unique' => true,
            ),
            'repotype' =>
            array(
                 'type' => 'Pluf_DB_Field_Varchar',
                 'blank' => false,
                 'size' => 25,
                 'verbose' => __('Repository Type'),
            ),
            'desc' =>
            array(
                'type' => 'Pluf_DB_Field_Varchar',
                'blank' => false,
                'size' => 250,
                'verbose' => __('Description'),
            ),
            'creation_dtime' =>
            array(
                'type' => 'Pluf_DB_Field_Datetime',
                'blank' => true,
                'verbose' => __('creation date'),
            ),
            'submitter' =>
            array(
                'type' => 'Pluf_DB_Field_Foreignkey',
                'model' => 'Pluf_User',
                'blank' => false,
                'verbose' => __('submitter'),
            ));
    }

    /**
     * String representation of the abstract.
     */
    function __toString()
    {
        return $this->shortname;
    }

    /**
     * String ready for indexation.
     */
    function _toIndex()
    {
        return '';
    }

    function preSave($create=false)
    {
        if ($this->id == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
        }

    }
}