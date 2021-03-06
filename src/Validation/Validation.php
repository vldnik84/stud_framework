<?php
/**
 * Created by PhpStorm.
 * User: flameseeker
 * Date: 03.06.18
 * Time: 22:52
 */

namespace Mindk\Framework\Validation;

use Mindk\Framework\Http\Request\Request;
use Mindk\Framework\Exceptions\ValidationException;
use Mindk\Framework\DI\Injector;

class Validation
{
    /**
     * Validate request
     *
     * @param Request $request
     * @param $data
     * @return array|null
     * @throws ValidationException
     */
    public function validate(Request $request, $data){
        $errors = [];
        foreach($data as $field => $rules){
            $field_rules = is_int(strpos($rules, '|')) ? explode('|', $rules) : [$rules];
            if(!in_array('required', $field_rules) && !$request->check($field)){
                continue;
            }
            foreach($field_rules as $rule){
                $rule_array = is_int(strpos($rule, ':')) ? explode(':', $rule) : [$rule];
                if(!method_exists($this, $rule_array[0])){
                    throw new ValidationException($rule_array[0] . ' not found in rules');
                }
                if(count($rule_array) === 1){
                    if($rule_array[0] == 'confirmed'){
                        $result = $this->{$rule_array[0]}($field, $request->get($field), $request);
                    }
                    else {
                        $result = $this->{$rule_array[0]}($field, $request->get($field));
                    }
                    if(is_array($result)){
                       $errors += $result;
                       break;
                    }
                }
                if(count($rule_array) === 2){
                    $result = $this->{$rule_array[0]}($field, $request->get($field), $rule_array[1]);
                    if(is_array($result)){
                        $errors += $result;
                        break;
                    }
                }
                if(count($rule_array) === 3) {
                    $result = $this->{$rule_array[0]}($field, $request->get($field), $rule_array[1], $rule_array[2]);
                    if (is_array($result)) {
                        $errors += $result;
                        break;
                    }
                }
            }
        }
        
        return empty($errors) ? null : $errors;
    }

    /**
     * Check min length
     *
     * @param $field
     * @param $field_value
     * @param int $min
     * @return array|bool
     */
    public function min($field, $field_value, int $min) {

        return strlen($field_value) >= $min ? true : [$field => ucfirst($field) . " must be at least $min characters"];
    }

    /**
     * Check max length
     *
     * @param $field
     * @param $field_value
     * @param int $max
     * @return array|bool
     */
    public function max($field, $field_value, int $max) {

        return strlen($field_value) <= $max ? true : [$field =>  ucfirst($field) . " must not exceed $max characters"];
    }

    /**
     * Сhecks if the field is a loaded file
     *
     * @param $file_field
     * @param $field_value
     * @return array|bool
     */
    public function file($file_field, $field_value) {

        return isset($field_value['tmp_name']) && is_file($field_value['tmp_name']) ? true : [$file_field => ucfirst($file_field) . " is not a file"];
    }

    /**
     * Сhecks if the field is a email
     *
     * @param $field
     * @param $field_value
     * @return array|bool
     */
    public function email($field, $field_value) {

        return is_string(filter_var($field_value, FILTER_VALIDATE_EMAIL)) ? true : [$field => "Incorrect email"];
    }

    /**
     * Required field
     *
     * @param $field
     * @param $field_value
     * @return array|bool
     */
    public function required($field, $field_value) {
        return !empty($field_value) ? true : [$field => ucfirst($field) . " is required"];
    }

    /**
     * Verifies the field with the confirmation field
     *
     * @param $field
     * @param $field_value
     * @param Request $request
     * @return array|bool
     * @throws ValidationException
     */
    public function confirmed($field, $field_value, Request $request){
        $confirmed_field = 'confirm_' . lcfirst($field);
        if(!$request->has($confirmed_field)){
            throw new ValidationException($confirmed_field . ' not found in Request');
        }

        return $field_value === $request->get($confirmed_field, null, 'string') ? true : [$field => ucfirst($field) . 's do not match'];
    }

    /**
     * Сhecks the field in the specified table for uniqueness
     * 
     * @param $field
     * @param $field_value
     * @param $table_name
     * @return array
     * @throws ValidationException
     */
    public function unique($field, $field_value, $table_name){
        $namespace = $table_name == 'users' ? '\Mindk\Framework\Models\\' : '\App\Models\\';
        $model_name = $namespace . ucfirst(substr($table_name, 0, -1)) . 'Model';
        $model = Injector::make($model_name);
        $columns = $model->getColumnsNames();
        if(!in_array($field, $columns)){
            throw new ValidationException("Column '$field' not found in '$table_name'");
        }
        $check = $model->exist($field, $field_value);

        return empty($check) ?: [$field => ucfirst($field) . ' already exists'];
    }

    /**
     * Сhecks if the field is a string
     * 
     * @param $field
     * @param $field_value
     * @return array|bool
     */
    public function string($field, $field_value){
        return is_string($field_value) ? true : [$field => ucfirst($field) . ' is not a string'];
    }

    /**
     * Сhecks if the field is a integer
     * 
     * @param $field
     * @param $field_value
     * @return array|bool
     */
    public function int($field, $field_value){
        return is_int((int) $field_value) ? true : [$field => ucfirst($field) . ' is not a integer'];
    }

}