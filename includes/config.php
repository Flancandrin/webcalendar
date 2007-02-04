<?php
/* This file loads configuration settings from the data file settings.php and
 * sets up some needed variables.
 *
 * The settings.php file is created during installation using the web-based db
 * setup page (install/index.php).
 *
 * <b>Note:</b>
 * DO NOT EDIT THIS FILE!
 *
 *
 * @author Craig Knudsen <cknudsen@cknudsen.com>
 * @copyright Craig Knudsen, <cknudsen@cknudsen.com>, http://www.k5n.us/cknudsen
 * @license http://www.gnu.org/licenses/gpl.html GNU GPL
 * @version $Id$
 * @package WebCalendar
 */

/* Prints a fatal error message to the user along with a link to the
 * Troubleshooting section of the WebCalendar System Administrator's Guide.
 *
 * Execution is aborted.
 *
 * @param string  $error  The error message to display
 * @internal We don't normally put functions in this file. But, since this
 *           file is included before some of the others, this function either
 *           goes here or we repeat this code in multiple files.
 */
function die_miserable_death ( $error ) {
  global $APPLICATION_NAME, $LANGUAGE, $login, $TROUBLE_URL;
  // Make sure app name is set.
  $appStr = ( function_exists ( 'generate_application_name' )
    ? generate_application_name ()
    : ( ! empty ( $APPLICATION_NAME ) ? $APPLICATION_NAME : 'Title' ) );

  if ( function_exists ( 'translate' ) ) {
    if ( empty ( $LANGUAGE ) )
      load_user_preferences ();

    $h2_label = $appStr . ' ' . translate ( 'Error' );
    $title = $appStr . ': ' . translate ( 'Fatal Error' );
    $trouble_label = translate ( 'Troubleshooting Help' );
    $user_BGCOLOR = get_pref_setting ( $login, 'BGCOLOR' );
  } else {
    $appStr = 'WebCalendar';
    $h2_label = $appStr . ' ' . 'Error';
    $title = $appStr . ': ' . 'Fatal Error';
    $trouble_label = 'Troubleshooting Help';
    $user_BGCOLOR = '#FFFFFF';
  }

  echo <<<EOT
<html>
  <head><title>{$title}</title></head>
  <body bgcolor ="{$user_BGCOLOR}">
    <h2>{$h2_label}</h2>
    <p>{$error}</p><hr />
    <p><a href="{$TROUBLE_URL}" target="_blank">{$trouble_label}</a></p>
  </body>
</html>
EOT;
  exit;
}

function db_error ( $doExit = false, $sql = '' ) {
  global $settings;

  $ret = str_replace ( 'XXX', dbi_error (), translate ( 'Database error XXX.' ) )
   . ( ! empty ( $settings['mode'] ) && $settings['mode'] == 'dev' && !
    empty ( $sql ) ? '<br />SQL:<br />' . $sql : '' );

  if ( $doExit ) {
    echo $ret;
    exit;
  } else
    return $ret;
}

function do_config ( $fileLoc ) {
  global $db_database, $db_host, $db_login, $db_password, $db_persistent,
  $db_type, $NONUSER_PREFIX, $phpdbiVerbose, $PROGRAM_DATE, $PROGRAM_NAME,
  $PROGRAM_URL, $PROGRAM_VERSION, $readonly, $run_mode, $settings, $single_user,
  $single_user_login, $TROUBLE_URL, $use_http_auth, $user_inc;

  $PROGRAM_VERSION = 'v1.1.3';
  $PROGRAM_DATE = '?? ??? 2007 / CVS Snapshot';
  $PROGRAM_NAME = 'WebCalendar ' . "$PROGRAM_VERSION ($PROGRAM_DATE)";
  $PROGRAM_URL = 'http://www.k5n.us/webcalendar.php';
  $TROUBLE_URL = 'docs/WebCalendar-SysAdmin.html#trouble';

  // Open settings file to read.
  $settings = array ();
  $fd = @fopen ( $fileLoc, 'rb', true );
  if ( empty ( $fd ) && ! empty ( $includedir ) )
    @fopen ( $includedir . '/settings.php', 'rb', true );
  if ( empty ( $fd ) ) {
    // There is no settings.php file.
    // Redirect user to install page if it exists.
    if ( file_exists ( 'install/index.php' ) ) {
      header ( 'Location: install/index.php' );
      exit;
    } else
      die_miserable_death (
        translate ( 'Could not find settings.php file...' ) );
  }

  // We don't use fgets () since it seems to have problems with Mac-formatted
  // text files.  Instead, we read in the entire file, and split the lines manually.
  $data = '';
  while ( ! feof ( $fd ) ) {
    $data .= fgets ( $fd, 4096 );
  }
  fclose ( $fd );

  // Replace any combination of carriage return (\r) and new line (\n)
  // with a single new line.
  $data = preg_replace ( "/[\r\n]+/", "\n", $data );

  // Split the data into lines.
  $configLines = explode ( "\n", $data );

  for ( $n = 0, $cnt = count ( $configLines ); $n < $cnt; $n++ ) {
    $buffer = trim ( $configLines[$n], "\r\n " );
    if ( preg_match ( '/^#|\/\*/', $buffer ) || // comments
        preg_match ( '/^<\?/', $buffer ) || // start PHP code
        preg_match ( '/^\?>/', $buffer ) ) // end PHP code
      continue;
    if ( preg_match ( '/(\S+):\s*(\S+)/', $buffer, $matches ) )
      $settings[$matches[1]] = $matches[2];
    // echo "settings $matches[1] => $matches[2]<br />";
  }
  $configLines = $data = '';

  // Extract db settings into global vars.
  $db_database = $settings['db_database'];
  $db_host = $settings['db_host'];
  $db_login = $settings['db_login'];
  $db_password = $settings['db_password'];
  $db_persistent = ( preg_match ( '/(1|yes|true|on)/i',
      $settings['db_persistent'] ) ? '1' : '0' );
  $db_type = $settings['db_type'];
  // Use 'db_cachedir' if found, otherwise look for 'cachedir'.
  if ( ! empty ( $settings['db_cachedir'] ) )
    dbi_init_cache ( $settings['db_cachedir'] );
  else
  if ( ! empty ( $settings['cachedir'] ) )
    dbi_init_cache ( $settings['cachedir'] );

  if ( ! empty ( $settings['db_debug'] ) &&
      preg_match ( '/(1|true|yes|enable|on)/i', $settings['db_debug'] ) )
    dbi_set_debug ( true );

  foreach ( array ( 'db_type', 'db_host', 'db_login', 'db_password' ) as $s ) {
    if ( empty ( $settings[$s] ) )
      die_miserable_death ( str_replace ( 'XXX', $s,
          translate ( 'Could not find XXX defined in...' ) ) );
  }

  // Allow special settings of 'none' in some settings[] values.
  // This can be used for db servers not using TCP port for connection.
  $db_host = ( $db_host == 'none' ? '' : $db_host );
  $db_password = ( $db_password == 'none' ? '' : $db_password );

  $readonly = preg_match ( '/(1|yes|true|on)/i',
    $settings['readonly'] ) ? 'Y' : 'N';

  if ( empty ( $settings['mode'] ) )
    $settings['mode'] = 'prod';

  $run_mode = ( preg_match ( '/(dev)/i', $settings['mode'] ) ? 'dev' : 'prod' );
  $phpdbiVerbose = ( $run_mode == 'dev' ) ;
  $single_user = preg_match ( '/(1|yes|true|on)/i',
    $settings['single_user'] ) ? 'Y' : 'N';

  if ( $single_user == 'Y' )
    $single_user_login = $settings['single_user_login'];

  if ( $single_user == 'Y' && empty ( $single_user_login ) )
    die_miserable_death ( str_replace ( 'XXX', 'single_user_login',
        translate ( 'You must define XXX in' ) ) );

  $use_http_auth = ( preg_match ( '/(1|yes|true|on)/i',
      $settings['use_http_auth'] ) ? true : false );

  // Type of user authentication.
  $user_inc = $settings['user_inc'];

  // Check the current installation version.
  // Redirect user to install page if it is different from stored value.
  // This will prevent running WebCalendar until UPGRADING.html has been
  // read and required upgrade actions completed.
  $c = @dbi_connect ( $db_host, $db_login, $db_password, $db_database, false );
  if ( $c ) {
    $rows = dbi_get_cached_rows ( 'SELECT cal_value FROM webcal_config
       WHERE cal_setting = \'WEBCAL_PROGRAM_VERSION\'' );
    if ( ! $rows ) {
      // &amp; does not work here...leave it as &.
      header ( 'Location: install/index.php?action=mismatch&version=UNKNOWN' );
      exit;
    } else {
      $row = $rows[0];
      if ( empty ( $row ) || $row[0] != $PROGRAM_VERSION ) {
        // &amp; does not work here...leave it as &.
        header ( 'Location: install/index.php?action=mismatch&version='
         . ( empty ( $row ) ? 'UNKNOWN' : $row[0] ) );
        exit;
      }
    }
    dbi_close ( $c );
  } else { // Must mean we don't have a settings.php file.
    // &amp; does not work here...leave it as &.
    header ( 'Location: install/index.php?action=mismatch&version=UNKNOWN' );
    exit;
  }

  // We can add extra 'nonuser' calendars such as a holiday, corporate,
  // departmental, etc.  We need a unique prefix for these calendars
  // so we don't get them mixed up with real logins.  This prefix should be
  // a maximum of 5 characters and should NOT change once set!
  $NONUSER_PREFIX = '_NUC_';

  if ( $single_user != 'Y' )
    $single_user_login = '';
}

?>
