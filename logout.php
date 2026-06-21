<?php
// Session စတင်ရန်
session_start();

// Session ထဲတွင် မှတ်သားထားသော Data များအားလုံးကို ဖျက်ထုတ်ခြင်း
$_SESSION = array();

// Session တစ်ခုလုံးကို အပြီးတိုင် ဖျက်သိမ်းခြင်း
session_destroy();

// Login စာမျက်နှာသို့ ပြန်လည်ပို့ဆောင်ပေးခြင်း
header("Location: login.php");
exit();
?>