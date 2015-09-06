<?php

class Pluf_Form_Widget_TimeZoneInput extends Pluf_Form_Widget_Input
{
    public $input_type = 'text';
    public $initial = "";

    public function __construct($attrs = []) {
        parent::__construct($attrs);
    }

    public function render($name, $value, $extra_attrs=array()) {
        return Pluf_Template::markSafe($this->getHTML($name, $value));
    }

    public function getHTML($name, $value) {
        // Based off snippet here: http://stackoverflow.com/a/17355238/195722
        static $regions = array(
            DateTimeZone::AFRICA,
            DateTimeZone::AMERICA,
            DateTimeZone::ANTARCTICA,
            DateTimeZone::ASIA,
            DateTimeZone::ATLANTIC,
            DateTimeZone::AUSTRALIA,
            DateTimeZone::EUROPE,
            DateTimeZone::INDIAN,
            DateTimeZone::PACIFIC,
        );

        $timezones = array();
        foreach( $regions as $region )
        {
            $timezones = array_merge( $timezones, DateTimeZone::listIdentifiers( $region ) );
        }

        $timezone_offsets = array();
        foreach( $timezones as $timezone )
        {
            $tz = new DateTimeZone($timezone);
            $timezone_offsets[$timezone] = $tz->getOffset(new DateTime);
        }

        // sort timezone by timezone name
        ksort($timezone_offsets);

        $timezone_list = array();
        foreach( $timezone_offsets as $timezone => $offset )
        {
            $offset_prefix = $offset < 0 ? '-' : '+';
            $offset_formatted = gmdate( 'H:i', abs($offset) );

            $pretty_offset = "UTC${offset_prefix}${offset_formatted}";

            $t = new DateTimeZone($timezone);
            $c = new DateTime(null, $t);
            $current_time = $c->format('g:i A');

            $timezone_list[$timezone] = "(${pretty_offset}) $timezone - $current_time";
        }

        $listArray = [];
        foreach($timezone_list as $key => $val) {
            if ($key == $value) {
                $listArray[] = "<option selected=\"selected\" value=\"$key\">$val</option>";
            } else {
                $listArray[] = "<option value=\"$key\">$val</option>";
            }
        }

        return "<select name=\"$name\">" . implode("\n", $listArray) . "</select>";
    }
}