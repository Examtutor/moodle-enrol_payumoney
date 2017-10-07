 <?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Listens for Instant Payment Notification from PayUMoney.com
 *
 * This script waits for Payment notification from PayUMoney.com, 
 * then it sets up the enrolment for that user.
 *
 * @package    enrol_payumoney
 * @copyright  2017 Exam Tutor, Venkatesan R Iyengar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Disable moodle specific debug messages and any errors in output,
// comment out when debugging or better look into error log!
//define('NO_DEBUG_DISPLAY', true);

require("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

global $DB, $CFG;

$id = required_param('id', PARAM_INT);

$response = $DB->get_record('enrol_payumoney', array('id' => $id));
$responsearray = json_decode($response->auth_json, true);
//print_r($response);
//print_r($responsearray);

$merchantSALT = get_config('enrol_payumoney', 'merchantsalt');
$txnid = $responsearray['txnid'];
$amount = $responsearray['amount'];
$merchantkey = $responsearray['key'];
$useremail = $responsearray['email'];
$userfirstname = $responsearray['firstname'];
$productinfo = $responsearray['productinfo'];
$status = $responsearray['status'];
$udf1 = $responsearray['udf1'];
$udf2 = $responsearray['udf2'];
$str = $merchantSALT . "|" . $status . "|||||||||" . $udf2. "|" . $udf1 . "|" . $useremail . "|" . $userfirstname . "|" . $productinfo . "|" . $amount . "|" . $txnid . "|" . $merchantkey;
$generatedhash = strtolower(hash('sha512', $str));

if ($generatedhash != $responsearray['hash']) {
    print_error("We can't validate your transaction. Please try again!!"); die;
}

$arraycourseinstance = explode('-', $responsearray['udf1']);
if (empty($arraycourseinstance) || count($arraycourseinstance) < 4) {
    print_error("Received an invalid payment notification!! (Fake payment?)"); die;
}

if (! $user = $DB->get_record("user", array("id" => $arraycourseinstance[1]))) {
    print_error("Not a valid user id"); die;
}

if (! $course = $DB->get_record("course", array("id" => $arraycourseinstance[0]))) {
    print_error("Not a valid course id"); die;
}

if (! $context = context_course::instance($arraycourseinstance[0], IGNORE_MISSING)) {
    print_error("Not a valid context id"); die;
}

if (! $plugininstance = $DB->get_record("enrol", array("id" => $arraycourseinstance[2], "status" => 0))) {
    print_error("Not a valid instance id"); die;
}


$enrolpayumoney = $userenrolments = $roleassignments = new stdClass();

$enrolpayumoney->id = $id;
$enrolpayumoney->item_name = $responsearray['productinfo'];
$enrolpayumoney->courseid = $arraycourseinstance[0];
$enrolpayumoney->userid = $arraycourseinstance[1];
$enrolpayumoney->instanceid = $arraycourseinstance[2];
$enrolpayumoney->amount = $responsearray['amount'];
//$enrolpayumoney->tax = $responsearray['tax'];



if ($responsearray['status'] == "success") {
    $enrolpayumoney->payment_status = 'Approved';
    
    $PAGE->set_context($context);
    $coursecontext = context_course::instance($course->id, IGNORE_MISSING);

    if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                         '', '', '', '', false, true)) {
        $users = sort_by_roleassignment_authority($users, $context);
        $teacher = array_shift($users);
    } else {
        $teacher = false;
    }

    $plugin = enrol_get_plugin('payumoney');

    $mailstudents = $plugin->get_config('mailstudents');
    $mailteachers = $plugin->get_config('mailteachers');
    $mailadmins   = $plugin->get_config('mailadmins');
    $shortname = format_string($course->shortname, true, array('context' => $context));

    if (!empty($mailstudents)) {
        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

        $eventdata = new \core\message\message();
        $eventdata->courseid          = $course->id;
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_payumoney';
        $eventdata->name              = 'payumoney_enrolment';
        $eventdata->userfrom          = empty($teacher) ? core_user::get_noreply_user() : $teacher;
        $eventdata->userto            = $user;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);

    }

    if (!empty($mailteachers) && !empty($teacher)) {
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);

        $eventdata = new \core\message\message();
        $eventdata->courseid          = $course->id;
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_payumoney';
        $eventdata->name              = 'payumoney_enrolment';
        $eventdata->userfrom          = $user;
        $eventdata->userto            = $teacher;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    if (!empty($mailadmins)) {
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);
        $admins = get_admins();
        foreach ($admins as $admin) {
            $eventdata = new \core\message\message();
            $eventdata->courseid          = $course->id;
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_payumoney';
            $eventdata->name              = 'payumoney_enrolment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $admin;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }
    }
}
if ($responsearray['status'] == "pending") {
    $enrolpayumoney->payment_status = 'Held for Review';
}
if ($responsearray['status'] == "failure") {
    $enrolpayumoney->payment_status = 'Declined due to some Error';
}


$enrolpayumoney->trans_id = $responsearray['txnid'];
$enrolpayumoney->payment_id = $responsearray['payuMoneyId'];
$enrolpayumoney->timeupdated = time();
/* Inserting value to enrol_payumoney table */
$ret1 = $DB->update_record("enrol_payumoney", $enrolpayumoney, false);

if ($responsearray['status'] == "success") {
    /* Inserting value to user_enrolments table */

    $userenrolments->status = 0;
    $userenrolments->enrolid = $arraycourseinstance[2];
    $userenrolments->userid = $arraycourseinstance[1];
    $userenrolments->timestart = time();
    $userenrolments->timeend = time() + $udf2;
    $userenrolments->modifierid = 2;
    $userenrolments->timecreated = time();
    $userenrolments->timemodified = time();
    $ret2 = $DB->insert_record("user_enrolments", $userenrolments, false);
    /* Inserting value to role_assignments table */
    $roleassignments->roleid = 5;
    $roleassignments->contextid = $arraycourseinstance[3];
    $roleassignments->userid = $arraycourseinstance[1];
    $roleassignments->timemodified = time();
    $roleassignments->modifierid = 2;
    $roleassignments->component = '';
    $roleassignments->itemid = 0;
    $roleassignments->sortorder = 0;
    $ret3 = $DB->insert_record('role_assignments', $roleassignments, false);
}


echo '<script type="text/javascript">
     window.location.href="'.$CFG->wwwroot.'/enrol/payumoney/return.php?id='.$arraycourseinstance[0].'";
     </script>';
die;


