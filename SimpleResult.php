<?php
/**
 * Created by PhpStorm.
 * User: Light
 * Date: 16/05/2016
 * Time: 16:32
 */

namespace DB\SimpleRecord;


class SimpleResult extends SimpleRecord
{
    private $rw_values;
    private $changed;

    function __construct ( $values, $pk ) {
        $this->rw_values  = $values;
        $this->changed    = false;
        $this->primaryKey = $pk;
    }

    public function __call($method, $params) {
        if ( preg_match("#^set#i", $method) ) {
            return $this->handleSetCalls($method);
        }
        if ( preg_match("#^get#i", $method) ) {
            return $this->handleGetCalls($method);
        }
    }

    private function set( $field, $value ) {
        $field = strtolower($field);
        if ( array_key_exists($field, $this->rw_values) ) {
            $this->rw_values[ $field ] = $value;
            $this->changed = true;
            return $this;
        } else {
            parent::error_msg('Operation failed: not found @field('.$field.')');
            return false;
        }
    }

    private function get( $key ) {
        $key = strtolower($key);
        if ( array_key_exists($key, $this->rw_values) ) {
            return $this->rw_values[ $key ];
        } else {
            parent::error_msg('Operation failed: not found @field('.$field.')');
            return false;
        }
    }

    public function save() {
        if ( $this->changed ) {
            $cond[ $this->primaryKey ] = $this->rw_values[ $this->primaryKey ];
            $data = array_slice($this->rw_values, 1);

            parent::update($data, $cond);
            $this->changed = false;
        }
    }

    /**
     * @param $method
     * @return SimpleResult
     */
    private function handleSetCalls($method)
    {
        $field = str_replace('set', '', $method);
        $value = (string) array_shift($params);
        return $this->set($field, $value);
    }

    /**
     * @param $method
     * @return bool
     */
    private function handleGetCalls($method)
    {
        $field = str_replace('get', '', $method);
        return $this->get($field);
    }
}