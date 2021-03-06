<?php
/**
 * Skytells PHP Framework --------------------------------------------------*
 * @category   Web Development ( Programming )
 * @package    Skytells PHP Framework
 * @version 3.0
 * @license Freeware
 * @copyright  2007-2017 Skytells, Inc. All rights reserved.
 * @license    https://www.skytells.net/us/terms  Freeware.
 * @author Dr. Hazem Ali ( fb.com/Haz4m )
 * @see The Framework's changelog to be always up to date.
 */
 Namespace Skytells\Handlers;
  Class Mail
    {
      public $cName;
      public $Responder;
      public function __construct()
        {
          $this->cName = "Mail";

          return $this;
        }


      public function sendMail($MailInfo)
        {
          $from=(isset($MailInfo["from"]) && !empty($MailInfo["from"])) ? $MailInfo["from"] : SITE_NAME;
          $sender=(isset($MailInfo["sender"]) && !empty($MailInfo["sender"])) ? $MailInfo["sender"] : SENDER_EMAIL;
          $headers = "MIME-Version: 1.0" . "\r\n";
          $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
          $headers .= 'From: '.$from.' <'.$sender.'>' . "\r\n";


            if ($m = mail($MailInfo["to"], $MailInfo["subject"], $MailInfo["message"], $headers))
            {
              return true;
            }
            else
            {
              return false;
            }
        }
    }
