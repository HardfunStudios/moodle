<?php
// For debugging
/*
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(E_ALL);
*/

class MoodleUserInfoSync {
    static private $instance = null;
    private $moodle;
    private $lp;
    private $moodle_prefix = null;

    private $username = null;
    private $moodle_user_id = null;

    private $student = null;

    public function __construct()
    {
        if (isset($_GET['u'])) {
            $this->username = $_GET['u'];
        } else {
            $this->return_message(false, 'User not specified');
        }

        require_once("config-db-hf.php");
        $this->moodle_prefix = $MOODLE_DB_CFG['prefix'];
        $this->lp = new PDO($LP_DB_CFG['dbtype'].':host='.$LP_DB_CFG['dbhost'].';dbname='.$LP_DB_CFG['dbname'], $LP_DB_CFG['dbuser'], $LP_DB_CFG['dbpass']);
        $this->moodle = new PDO($MOODLE_DB_CFG['dbtype'].':host='.$MOODLE_DB_CFG['dbhost'].';dbname='.$MOODLE_DB_CFG['dbname'], $MOODLE_DB_CFG['dbuser'], $MOODLE_DB_CFG['dbpass']);

        // For debugging
        //$this->lp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //$this->moodle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function return_message($success, $message) {
        header('Content-Type: application/json');
        exit(json_encode([
            'success' => $success,
            'message' => $message
        ]));
    }

    public static function getInstance() {
        if(!self::$instance) {
            self::$instance = new MoodleUserInfoSync();
        }

        return self::$instance;
    }

    private function setStudent(){
        $sth = $this->lp->prepare('
            SELECT s.*, st.acronym as state_acronym, sc.name as school_name
            FROM students s
            LEFT JOIN cities c ON c.id = s.city_id
            LEFT JOIN states st ON st.id = c.state_id
            LEFT JOIN schools sc ON sc.id = s.school_id
            WHERE username = ?');
        $sth->execute(array($this->username));
        $result = $sth->fetchAll();

        if ($result) {
            $this->student = $result[0];
        } else {
            $this->return_message(false, 'LP User not found');
        }
    }

    private function setMoodleUserId() {
        $sth = $this->moodle->prepare('SELECT id FROM '.$this->moodle_prefix.'user WHERE username = ?');
        $sth->execute(array($this->username));
        $result = $sth->fetch();
        if (!$result) $this->return_message(false, 'Moodle User not found');
        $this->moodle_user_id = $result[0];
    }

    private function updateBasicInformation() {
        error_log(print_r('------------------------------------------', TRUE));
        error_log(print_r($this->student['email'], TRUE));
        error_log(print_r($this->student['gender'], TRUE));
        error_log(print_r($this->student['country'], TRUE));
        error_log(print_r('------------------------------------------', TRUE));
        $sth = $this->moodle->prepare('UPDATE '.$this->moodle_prefix.'user SET firstname=?, lastname=?, email=?, phone2=?, city=?, country=? WHERE id=?');
        $student_lp_basic_fields = array(
            $this->student['first_name'],
            $this->student['last_name'],
            $this->student['email'],
            $this->student['phone_number'],
            $this->student['city_name'] || "", // Moodle refuses null city
            $this->student['country'] || "BR",
            $this->moodle_user_id
        );
        if(!$sth->execute($student_lp_basic_fields)){
            $this->return_message(false,  "Couldn't update basic information");
        }
    }

    private function updateCustomFields() {
        // LP Columns mapping to Moodle field ids
        $this->student['subjects'] = $this->joinFieldValues($this->student['subjects']);
        $this->student['school_cycles'] = $this->joinFieldValues($this->student['school_cycles']);

        if(!is_null($this->student['online_courses_before'])){
            $this->student['online_courses_before'] = $this->student['online_courses_before'] == 1 ? 'Sim' : 'Não';
        }

        switch($this->student['act_in_school']){
            case 'sim':
                $this->student['act_in_school'] = 'Sim';
                break;
            case 'nao':
                $this->student['act_in_school'] = 'Não';
                break;
            case 'nao_trabalha':
                $this->student['act_in_school'] = 'Não estou trabalhando';
                break;
        }

        if(!is_null($this->student['birthdate'])) {
            $this->student['birthdate'] = strtotime($this->student['birthdate']) + 40000; // sum ~half day to prevent timezone differences between LP and moodle
        }

        $extra_fields_ids = array(
            'birthdate'				=> 3,
            'state_acronym'     	=> 4,
            'treatment_pronoun' 	=> 6,
            'job_position'	    	=> 8,
            'rural_school'	    	=> 9,
            'school_name'	    	=> 10,
            //''                    => 11,
            'online_courses_before' => 12,
            'graduation_area'	    => 13,
            'subjects'			    => 15,
            'act_in_school'		    => 16,
            'organization'  	    => 19,
            'education_work_since'  => 22,
            'workplace'			    => 23,
            //''    				=> 24,
            'school_cycles'			=> 25,
            'gender'				=> 26,
            'education_level'		=> 27,
            'family_income'			=> 28,
            'phone_number'			=> 29,
            'is_special'            => 36
        );

        foreach($extra_fields_ids as $key => $id) {
            if(is_null($this->student[$key]) || empty($this->student[$key])){
                $sth = $this->moodle->prepare('
                    DELETE FROM '.$this->moodle_prefix.'user_info_data
                    WHERE
                        '.$this->moodle_prefix.'user_info_data.fieldid=? AND
                        '.$this->moodle_prefix.'user_info_data.userid=?');
                $custom_field_values = array(
                    $id,
                    $this->moodle_user_id,
                );

                if (!$sth->execute($custom_field_values)) {
                    $this->return_message(false,  "Couldn't delete $key field");
                }
            } else {
                $sth = $this->moodle->prepare('
                    INSERT INTO '.$this->moodle_prefix.'user_info_data(data, fieldid, userid)
                        VALUES(?,?,?)
                    ON CONFLICT(fieldid, userid)
                    DO UPDATE SET
                        data=?
                    WHERE
                        '.$this->moodle_prefix.'user_info_data.fieldid=? AND
                        '.$this->moodle_prefix.'user_info_data.userid=?');
                $custom_field_values = array(
                    $this->student[$key],
                    $id,
                    $this->moodle_user_id,
                    $this->student[$key],
                    $id,
                    $this->moodle_user_id
                );

                if (!$sth->execute($custom_field_values)) {
                    $this->return_message(false,  "Couldn't insert/update $key field");
                }
            }
        }
    }

    private function joinFieldValues($values) {
        $values = '[' . substr($values, 1, -1)  . ']';
        $values = json_decode($values);
        $values = array_filter($values);
        return join("\r\n", $values);
    }

    public function execute() {
        $this->setStudent();
        $this->setMoodleUserId();
        $this->updateBasicInformation();
        $this->updateCustomFields();
        $this->return_message(true, 'User updated');
    }
}

$instance = MoodleUserInfoSync::getInstance()->execute();
