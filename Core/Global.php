<?php
/**
 * Skytells PHP Framework --------------------------------------------------*
 * @category   Web Development ( Programming )
 * @package    Skytells PHP Framework
 * @version    3.1
 * @copyright  2007-2018 Skytells, Inc. All rights reserved.
 * @license    MIT | https://www.skytells.net/us/terms .
 * @author     Dr. Hazem Ali ( fb.com/Haz4m )
 * @see        The Framework's changelog to be always up to date.
 */
 use Skytells\Ecosystem\Payload;
 static $Framework, $_Autoload, $lang;
 static $ConnectedDBS = 0;
 $db = null;
 require __DIR__.'/Constants.php';
 require APP_MISC_DIR.'/Settings.php';
 require __DIR__.'/Ecosystem/Runtime.php';
 require __DIR__.'/Ecosystem/Console.php';
 require __DIR__.'/Ecosystem/Payload.php';
 require __DIR__.'/Ecosystem/Router.php';
 require __DIR__.'/Kernel/Kernel.php';
 foreach(glob(APP_MISC_DIR.'/Config/*.php') as $file) { require $file; }
 require __DIR__.'/Kernel/Boot.php';
 #  if (count($_Autoload) > 0) { Payload::Autoload($_Autoload); }
  Payload::Define('ROUTES');
  Payload::Define('SETTINGS');
  Payload::Autoload(Array(ENV_FUNCTIONS_DIR));
  require COREDIRNAME.'/Services.php';
  Load::setReporter(FALSE);
  Load::handler('Http');
  define('HTTP_SERVER_PROTOCOL', (Skytells\Handlers\Http::isSSL()) ? 'https://' : 'http://');
  Skytells\Ecosystem\Payload::Autoload(Array(ENV_BASES_DIR, ENV_INTERFACES_DIR));
  $db = null;
  free_memory();
