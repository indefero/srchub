<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008-2011 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');

/**
 * Allow a user to update its details.
 */
class IDF_Form_UserAccount  extends Pluf_Form
{
    public $user = null;

    public function initFields($extra=array())
    {
        $this->user = $extra['user'];
        $user_data = IDF_UserData::factory($this->user);

        $this->fields['first_name'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('First name'),
                                            'initial' => $this->user->first_name,
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));
        $this->fields['last_name'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Last name'),
                                            'initial' => $this->user->last_name,
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 20,
                                                                    ),
                                            ));

        $this->fields['email'] = new Pluf_Form_Field_Email(
                                      array('required' => true,
                                            'label' => __('Your email'),
                                            'initial' => $this->user->email,
                                            'help_text' => __('If you change your email address, an email will be sent to the new address to confirm it.'),
                                            ));

        $this->fields['language'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Language'),
                                            'initial' => $this->user->language,
                                            'widget' => 'Pluf_Form_Widget_SelectInput',
                                            'widget_attrs' => array(
                                                       'choices' =>
                                                       Pluf_L10n::getInstalledLanguages()
                                                                    ),
                                            ));

        $this->fields['password'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Your password'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_PasswordInput',
                                            'help_text' => Pluf_Template::markSafe(__('Leave blank if you do not want to change your password.').'<br />'.__('Your password must be hard for other people to find it, but easy for you to remember.')),
                                            'widget_attrs' => array(
                                                       'autocomplete' => 'off',
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));
        $this->fields['password2'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Confirm your password'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_PasswordInput',
                                            'widget_attrs' => array(
                                                       'autocomplete' => 'off',
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));

        $this->fields['description'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Description'),
                                            'initial' => $user_data->description,
                                            'widget_attrs' => array('rows' => 3,
                                                                    'cols' => 40),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            ));

        $this->fields['twitter'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Twitter username'),
                                            'initial' => $user_data->twitter,
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));

        $this->fields['public_email'] = new Pluf_Form_Field_Email(
                                      array('required' => false,
                                            'label' => __('Public email address'),
                                            'initial' => $user_data->public_email,
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));

        $this->fields['website'] = new Pluf_Form_Field_Url(
                                      array('required' => false,
                                            'label' => __('Website URL'),
                                            'initial' => $user_data->website,
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));

        $this->fields['custom_avatar'] = new Pluf_Form_Field_File(
                                      array('required' => false,
                                            'label' => __('Upload custom avatar'),
                                            'initial' => '',
                                            'max_size' => Pluf::f('max_upload_size', 2097152),
                                            'move_function_params' => array('upload_path' => Pluf::f('upload_path').'/avatars',
                                                                            'upload_path_create' => true,
                                                                            'upload_overwrite' => true,
                                                                            'file_name' => 'user_'.$this->user->id.'_%s'),
                                            'help_text' => __('An image file with a width and height not larger than 60 pixels (bigger images are scaled down).'),
                                            ));

        $this->fields['remove_custom_avatar'] = new Pluf_Form_Field_Boolean(
                                      array('required' => false,
                                            'label' => __('Remove custom avatar'),
                                            'initial' => false,
                                            'widget' => 'Pluf_Form_Widget_CheckboxInput',
                                            'widget_attrs' => array(),
                                            'help_text' => __('Tick this to delete the custom avatar.'),
                                            ));

        $this->fields['public_key'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Add a public key'),
                                            'initial' => '',
                                            'widget_attrs' => array('rows' => 3,
                                                                    'cols' => 40),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'help_text' => __('Paste an SSH or monotone public key. Be careful to not provide your private key here!')
                                            ));

        $this->fields['secondary_mail'] = new Pluf_Form_Field_Email(
                                      array('required' => false,
                                            'label' => __('Add a secondary email address'),
                                            'initial' => '',
                                            'help_text' => __('You will get an email to confirm that you own the address you specify.'),
                                            ));
        $otp = "";
        if ($this->user->otpkey != "")
            $otp = Pluf_Utils::convBase($this->user->otpkey, '0123456789abcdef', 'abcdefghijklmnopqrstuvwxyz234567');
        $this->fields['otpkey'] = new Pluf_Form_Field_Varchar(
                                        array('required' => false,
                                            'label' => __('Add a OTP Key'),
                                            //'initial' => (!empty($user_data->otpkey)) ?  : "",
                                            //'initial' => (string)(!empty($user_data->otpkey)),
                                            'initial' => $otp,
                                            'help_text' => __('Key must be in base32 for generated QRcode and import into Google Authenticator.'),
                                            'widget_attrs' => array(
                                                'maxlength' => 50,
                                                'size' => 32,
                                            ),
                                        ));
    }



    private function send_validation_mail($new_email, $secondary_mail=false)
    {
        if ($secondary_mail) {
            $type = "secondary";
        } else {
            $type = "primary";
        }
        $cr = new Pluf_Crypt(md5(Pluf::f('secret_key')));
        $encrypted = trim($cr->encrypt($new_email.':'.$this->user->id.':'.time().':'.$type), '~');
        $key = substr(md5(Pluf::f('secret_key').$encrypted), 0, 2).$encrypted;
        $url = Pluf::f('url_base').Pluf_HTTP_URL_urlForView('IDF_Views_User::changeEmailDo', array($key), array(), false);
        $urlik = Pluf::f('url_base').Pluf_HTTP_URL_urlForView('IDF_Views_User::changeEmailInputKey', array(), array(), false);
        $context = new Pluf_Template_Context(
             array('key' => Pluf_Template::markSafe($key),
                   'url' => Pluf_Template::markSafe($url),
                   'urlik' => Pluf_Template::markSafe($urlik),
                   'email' => $new_email,
                   'user'=> $this->user,
                   )
        );
        $tmpl = new Pluf_Template('idf/user/changeemail-email.txt');
        $text_email = $tmpl->render($context);
        $email = new Pluf_Mail(Pluf::f('from_email'), $new_email,
                               __('Confirm your new email address.'));
        $email->addTextMessage($text_email);
        $email->sendMail();
        $this->user->setMessage(sprintf(__('A validation email has been sent to "%s" to validate the email address change.'), Pluf_esc($new_email)));
    }

    /**
     * Save the model in the database.
     *
     * @param bool Commit in the database or not. If not, the object
     *             is returned but not saved in the database.
     * @return Object Model with data set from the form.
     */
    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }
        unset($this->cleaned_data['password2']);
        $update_pass = false;
        if (strlen($this->cleaned_data['password']) == 0) {
            unset($this->cleaned_data['password']);
        } else {
            $update_pass = true;
        }
        $old_email = $this->user->email;
        $new_email = $this->cleaned_data['email'];
        unset($this->cleaned_data['email']);
        if ($old_email != $new_email) {
            $this->send_validation_mail($new_email);
        }
        $this->user->setFromFormData($this->cleaned_data);
        // Add key as needed.
        if ('' !== $this->cleaned_data['public_key']) {
            $key = new IDF_Key();
            $key->user = $this->user;
            $key->content = $this->cleaned_data['public_key'];
            if ($commit) {
                $key->create();
            }
        }
        if ('' !== $this->cleaned_data['secondary_mail']) {
            $this->send_validation_mail($this->cleaned_data['secondary_mail'], true);
        }

        if ($commit) {
            if ($this->cleaned_data["otpkey"] != "")
                $this->user->otpkey = Pluf_Utils::convBase($this->cleaned_data["otpkey"], 'abcdefghijklmnopqrstuvwxyz234567', '0123456789abcdef');
            $this->user->update();

            // FIXME: go the extra mile and check the input lengths for
            // all fields here!
            // FIXME: this is all doubled in admin/UserUpdate!

            $user_data = IDF_UserData::factory($this->user);

            // Add or remove avatar - we need to do this here because every
            // single setter directly leads to a save in the database
            if ($user_data->avatar != '' &&
                    ($this->cleaned_data['remove_custom_avatar'] == 1 ||
                     $this->cleaned_data['custom_avatar'] != '')) {
                $avatar_path = Pluf::f('upload_path').'/avatars/'.basename($user_data->avatar);
                if (basename($avatar_path) != '' && is_file($avatar_path)) {
                    unlink($avatar_path);
                }
                $user_data->avatar = '';
            }

            if ($this->cleaned_data['custom_avatar'] != '') {
                $user_data->avatar = $this->cleaned_data['custom_avatar'];
            }

            $user_data->description  = $this->cleaned_data['description'];
            $user_data->twitter      = $this->cleaned_data['twitter'];
            $user_data->public_email = $this->cleaned_data['public_email'];
            $user_data->website      = $this->cleaned_data['website'];

            if ($update_pass) {
                /**
                 * [signal]
                 *
                 * Pluf_User::passwordUpdated
                 *
                 * [sender]
                 *
                 * IDF_Form_UserAccount
                 *
                 * [description]
                 *
                 * This signal is sent when the user updated his
                 * password from his account page.
                 *
                 * [parameters]
                 *
                 * array('user' => $user)
                 *
                 */
                $params = array('user' => $this->user);
                Pluf_Signal::send('Pluf_User::passwordUpdated',
                                  'IDF_Form_UserAccount', $params);
            }
        }
        return $this->user;
    }

    /**
     * Check arbitrary public keys.
     *
     * It will throw a Pluf_Form_Invalid exception if it cannot
     * validate the key.
     *
     * @param $key string The key
     * @param $user int The user id of the user of the key (0)
     * @return string The clean key
     */
    public static function checkPublicKey($key, $user=0)
    {
        $key = trim($key);
        if (strlen($key) == 0) {
            return '';
        }

        $keysearch = '';
        if (preg_match('#^(ssh\-(?:dss|rsa)\s+\S+)(.*)#', $key, $m)) {
            $basekey = preg_replace('/\s+/', ' ', $m[1]);
            $comment = trim(preg_replace('/[\r\n]/', ' ', $m[2]));
           
            $keysearch = $basekey.'%';
            $key = $basekey;
            if (!empty($comment))
                $key .= ' '.$comment;

            if (Pluf::f('idf_strong_key_check', false)) {

                $tmpfile = Pluf::f('tmp_folder', '/tmp').'/'.$user.'-key';
                file_put_contents($tmpfile, $key, LOCK_EX);
                $cmd = Pluf::f('idf_exec_cmd_prefix', '').
                    'ssh-keygen -l -f '.escapeshellarg($tmpfile).' > /dev/null 2>&1';
                exec($cmd, $out, $return);
                unlink($tmpfile);

                if ($return != 0) {
                    throw new Pluf_Form_Invalid(
                        __('Please check the key as it does not appear '.
                           'to be a valid SSH public key.')
                    );
                }
            }
        }
        else if (preg_match('#^\[pubkey [^\]]+\]\s*(\S+)\s*\[end\]$#', $key, $m)) {
            $keysearch = '%'.$m[1].'%';
            
            if (Pluf::f('idf_strong_key_check', false)) {

                // if monotone can read it, it should be valid
                $mtn_opts = implode(' ', Pluf::f('mtn_opts', array()));
                $cmd = Pluf::f('idf_exec_cmd_prefix', '').
                    sprintf('%s %s -d :memory: read >/tmp/php-out 2>&1',
                            Pluf::f('mtn_path', 'mtn'), $mtn_opts);
                $fp = popen($cmd, 'w');
                fwrite($fp, $key);
                $return = pclose($fp);

                if ($return != 0) {
                       throw new Pluf_Form_Invalid(
                        __('Please check the key as it does not appear '.
                           'to be a valid monotone public key.')
                    );
                }
            }
        }
        else {
            throw new Pluf_Form_Invalid(
                __('Public key looks like neither an SSH '.
                   'nor monotone public key.'));
        }

        // If $user, then check if not the same key stored
        if ($user) {
            $ruser = Pluf::factory('Pluf_User', $user);
            if ($ruser->id > 0) {
                $sql = new Pluf_SQL('content LIKE %s AND user=%s', array($keysearch, $ruser->id));
                $keys = Pluf::factory('IDF_Key')->getList(array('filter' => $sql->gen()));
                if (count($keys) > 0) {
                    throw new Pluf_Form_Invalid(
                        __('You already have uploaded this key.')
                    );
                }
            }
        }
        return $key;
    }


    function clean_custom_avatar()
    {
        // Just png, jpeg/jpg or gif
        if (!preg_match('/\.(png|jpg|jpeg|gif)$/i', $this->cleaned_data['custom_avatar']) &&
            $this->cleaned_data['custom_avatar'] != '') {
            @unlink(Pluf::f('upload_path').'/avatars/'.$this->cleaned_data['custom_avatar']);
            throw new Pluf_Form_Invalid(__('For security reason, you cannot upload a file with this extension.'));
        }
        return $this->cleaned_data['custom_avatar'];
    }


    function clean_last_name()
    {
        $last_name = trim($this->cleaned_data['last_name']);
        if ($last_name == mb_strtoupper($last_name)) {
            return mb_convert_case(mb_strtolower($last_name),
                                   MB_CASE_TITLE, 'UTF-8');
        }
        return $last_name;
    }

    function clean_first_name()
    {
        $first_name = trim($this->cleaned_data['first_name']);
        if ($first_name == mb_strtoupper($first_name)) {
            return mb_convert_case(mb_strtolower($first_name),
                                   MB_CASE_TITLE, 'UTF-8');
        }
        return $first_name;
    }

    function clean_email()
    {
        $this->cleaned_data['email'] = mb_strtolower(trim($this->cleaned_data['email']));
        $user = Pluf::factory('IDF_EmailAddress')->get_user_for_email_address($this->cleaned_data['email']);
        if ($user != null and $user->id != $this->user->id) {
            throw new Pluf_Form_Invalid(sprintf(__('The email "%s" is already used.'), $this->cleaned_data['email']));
        }
        return $this->cleaned_data['email'];
    }

    function clean_secondary_mail()
    {
        $this->cleaned_data['secondary_mail'] = mb_strtolower(trim($this->cleaned_data['secondary_mail']));
        if (Pluf::factory('IDF_EmailAddress')->get_user_for_email_address($this->cleaned_data['secondary_mail']) != null) {
            throw new Pluf_Form_Invalid(sprintf(__('The email "%s" is already used.'), $this->cleaned_data['secondary_mail']));
        }
        return $this->cleaned_data['secondary_mail'];
    }

    function clean_public_key()
    {
        $this->cleaned_data['public_key'] =
                self::checkPublicKey($this->cleaned_data['public_key'],
                                     $this->user->id);
        return $this->cleaned_data['public_key'];
    }

    /**
     * Check to see if the 2 passwords are the same
     */
    public function clean()
    {
        if (!isset($this->errors['password'])
            && !isset($this->errors['password2'])) {
            $password1 = $this->cleaned_data['password'];
            $password2 = $this->cleaned_data['password2'];
            if ($password1 != $password2) {
                throw new Pluf_Form_Invalid(__('The passwords do not match. Please give them again.'));
            }
        }

        return $this->cleaned_data;
    }


}
