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
 * PayUMoney.com enrolment plugin - enrolment form.
 *
 * @package    enrol_payumoney
 * @copyright  2017 Exam Tutor, Venkatesan R Iyengar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$merchantkey = $this->get_config('merchantkey');
$merchantSALT = $this->get_config('merchantsalt');
$txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
$amount = $cost;
$productinfo = $coursefullname;
$label = "Pay Now";
$hash = '';


if ($this->get_config('checkproductionmode') == 1) {
    $url = "https://secure.payu.in/_payment";
    $testmode = "false";
} else {
    $url = "https://test.payu.in/_payment ";
    $testmode = "true";
}
//$invoice = date('Ymd') . "-" . $instance->courseid . "-" . hash('crc32', $txnid); //udf3
$_SESSION['timestamp'] = $timestamp = time();
$udf1 = $instance->courseid.'-'.$USER->id.'-'.$instance->id.'-'.$context->id;
$enrolperiod = $instance->enrolperiod;//udf2
//Hash Sequence
$hashSequence = $merchantkey . "|" . $txnid . "|" . $amount . "|" . $productinfo . "|" . $USER->firstname . "|" . $USER->email . "|" . $udf1 . "|" . $enrolperiod . "|||||||||" . $merchantSALT;
$fingerprint = strtolower(hash('sha512', $hashSequence));

?>
<div align="center">
<p>This course requires a payment for entry.</p>
<p><b><?php echo $instancename; ?></b></p>
<p><b><?php echo get_string("cost").": {$instance->currency} {$localisedcost}"; ?></b></p>
<p>&nbsp;</p>
<p><img alt="PayUMoney" src="<?php echo $CFG->wwwroot; ?>/enrol/payumoney/pix/payumoney-logo.jpg" /></p>
<p>&nbsp;</p>
<p>
	<form method="post" action="<?php echo $url; ?>" >
		<input type="hidden" name="key" value="<?php echo $merchantkey; ?>" />
		<input type="hidden" name="hash" value="<?php echo $fingerprint; ?>" />
		<input type="hidden" name="txnid" value="<?php echo $txnid; ?>" />
		<input type="hidden" name="amount" value="<?php echo $amount; ?>" />
		<input type="hidden" name="productinfo" value="<?php echo $productinfo; ?>" />
		<input type="hidden" name="firstname" value="<?php echo $USER->firstname; ?>" />
		<input type="hidden" name="email" value="<?php echo $USER->email; ?>" />
		<input type="hidden" name="phone" value="<?php echo $_SESSION['timestamp']; ?>" />
		<input type="hidden" name="surl" value="<?php echo $CFG->wwwroot; ?>/enrol/payumoney/ipn.php" />
                <input type="hidden" name="udf1" value="<?php echo $udf1 ?>" />
		<input type="hidden" name="udf2" value="<?php echo $enrolperiod; ?>" />
		<input type="hidden" name="service_provider" value="payu_paisa" />
		<input type="submit" id="sub_button" value="" />
	</form>
</p>
</div>
<style type="text/css">
#sub_button{
  background: url("<?php echo $CFG->wwwroot; ?>/enrol/payumoney/pix/paynow.png") no-repeat scroll 0 0 transparent;
  color: #000000;
  cursor: pointer;
  font-weight: bold;
  height: 20px;
  padding-bottom: 2px;
  width: 301px;
  height: 59px;
}
</style>
