<?php
/*
 * @file
 * Including this file should load all result system functionality.
 * All includes (if necessary) should be done in this file, not elsewhere.
 */
session_start();

// classes are autoloaded.
require_once("WCAClasses/autoload.php");

// Try to include the pear mail package
$UserDir = trim(`echo ~`);
$pear_user_config = $UserDir . "/.pearrc";
set_include_path("." . PATH_SEPARATOR . $UserDir . "/pear/php" . PATH_SEPARATOR . get_include_path());
@ include "Mail.php";

// Let's set up system configuration data first.
global $config;
$config = new WCAClasses\ConfigurationData();
// perform some basic installation checks here
$installation_errors = $config->validateInstall();

// Create a global database connection object.
global $wcadb_conn;
$wcadb_conn = new WCAClasses\WCADBConn($config->get("database"));
// secondary connection for scripts using PDO
require_once '_pdo_db.php';

// misc. display functions
require_once '_ui.php';

// session functionality
require_once '_session.php';

// current results system functionality
require_once '_timer.php';
require_once '_parameters.php';
require_once '_database.php';
require_once '_choices.php';
require_once '_tables.php';
require_once '_links.php';
require_once '_values.php';
require_once '_cache.php';
require_once '_map.php';
require_once '_navigation.php';



#----------------------------------------------------------------------

function eventName ( $eventId ) {
  $event = getEvent( $eventId );
  return $event['name'];
}

function eventCellName ( $eventId ) {
  $event = getEvent( $eventId );
  return $event['cellName'];
}

function getEvent ( $eventId ) {
  foreach( getAllEvents() as $event )
    if( $event['id'] == $eventId )
      return $event;

  // Failed to get from cache file
  $event = dbQuery( "SELECT * FROM Events WHERE id='$eventId'" );
  if( count( $event ) == 1 )
    return $event[0];
}

function readEventSpecs ( $eventSpecs ) {
  $eventSpecsTree = array();
  foreach( getAllEventIdsIncludingObsolete() as $eventId )
    if( preg_match( "/(^| )$eventId\b(=(\d*)\/(\d*)\/(\w*)\/(\d*)\/(\d*))?/", $eventSpecs, $matches )) {
      $eventSpecsTree["$eventId"]['personLimit']      = isset( $matches[3] ) ? $matches[3] : "";
      $eventSpecsTree["$eventId"]['timeLimit']        = isset( $matches[4] ) ? $matches[4] : "";
      $eventSpecsTree["$eventId"]['timeFormat']       = isset( $matches[5] ) ? $matches[5] : "";
      $eventSpecsTree["$eventId"]['qualify']          = isset( $matches[6] ) ? $matches[6] : "";
      $eventSpecsTree["$eventId"]['qualifyTimeLimit'] = isset( $matches[7] ) ? $matches[7] : "";
    }
  return $eventSpecsTree;
}

function getEventSpecsEventIds ( $eventSpecs ) {
 return( array_keys( readEventSpecs( $eventSpecs )));
}

function getCompetition ( $id ) {
  foreach( getAllCompetitions() as $competition )
    if( $competition['id'] == $id )
      return $competition;
}

function roundCellName ( $roundId ) {
  $round = getRound( $roundId );
  return $round['cellName'];
}

function getRound ( $roundId ) {
  foreach( getAllRounds() as $round )
    if( $round['id'] == $roundId )
      return $round;
}

function getCountry ( $countryId ) {
  foreach( getAllUsedCountries() as $country )
    if( $country['id'] == $countryId )
      return $country;
}

function valueFormat ( $eventId ) {
  $event = getEvent( $eventId );
  return $event['format'];
}

#----------------------------------------------------------------------
function competitionDate ( $competition ) {
#----------------------------------------------------------------------
  extract( $competition );

  $months = explode( " ", ". Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec" );
  $date = $month ? $months[$month] : '&nbsp;';
  if( $day )
    $date .= " $day";
  if( $endMonth != $month )
    $date .= " - " . $months[$endMonth] . " $endDay";
  elseif( $endDay != $day )
    $date .= "-$endDay";
  return $date;
}

#----------------------------------------------------------------------

function chosenRegionName ( $visibleWorld = false ) {
  global $chosenRegionId;
  if ( !$chosenRegionId && $visibleWorld ) return 'World';
  return preg_replace( '/^_/', '', $chosenRegionId );
}

function chosenEventName () {
  global $chosenEventId;
  return $chosenEventId ? eventName( $chosenEventId ) : '';
}

function randomDebug () {
  return wcaDebug() ? rand( 1, 30000 ) : 1;
}

#----------------------------------------------------------------------

function eventCondition () {
  global $chosenEventId;
  return $chosenEventId ? " AND eventId = '$chosenEventId' " : "";
}

function competitionCondition () {
  global $chosenCompetitionId;
  return $chosenCompetitionId ? " AND competitionId = '$chosenCompetitionId' " : "";
}

function yearCondition () {
  global $chosenYears;

  #--- current = 90 days into the past + all future
  if( $chosenYears == 'current' )
    return "AND (10000*year+100*month+day)>" . wcaDate( 'Ymd', time() - 90 * 24 * 60 * 60 );

  if( preg_match( '/^until (\d+)$/', $chosenYears, $match ))
    return " AND year <= $match[1] ";

  if( preg_match( '/^only (\d+)$/', $chosenYears, $match ))
    return " AND year = $match[1] ";

  return '';
}

function regionCondition ( $countrySource ) {
  global $chosenRegionId;
  
  if( preg_match( '/^(world)?$/i', $chosenRegionId ))
    return '';

  if( preg_match( '/^_/', $chosenRegionId ))
    return " AND continentId = '$chosenRegionId'";

  if( $countrySource )
    $countrySource .= '.';

  return " AND ${countrySource}countryId = '$chosenRegionId'";
}

function pathToRoot () {
  global $config;
  return $config->get('pathToRoot');
}

function wcaDate ( $format='r', $timestamp=false ) {
  #--- Set timezone (otherwise date() might complain), then return the date
  date_default_timezone_set( 'Europe/Berlin' );
  return date( $format, $timestamp ? $timestamp : time() );
}

function genderText ($gender) {
  if ($gender == 'm') return 'Male';
  if ($gender == 'f') return 'Female';
  return '';
}

function getCurrentPictureFile ($upload_path, $personId) {
  $files = glob($upload_path . "a$personId.*");
  return $files ? $files[0] : FALSE;
}
function getWaitingPictureFile ($upload_path, $personId) {
  $files = glob($upload_path . "p$personId.*");
  return $files ? $files[0] : FALSE;
}
function getPreviousPictureFiles ($upload_path, $personId) {
  return array_reverse(glob($upload_path . "a$personId*"));
}
function acceptNewPictureFile ($upload_path, $personId, $newFile) {
  $currFile = getCurrentPictureFile($upload_path, $personId);
  if($currFile){
    $datetime = date('_Ymd_His.', filemtime($currFile));
    $backupFile = $upload_path . "old/a$personId$datetime" . pathinfo($currFile, PATHINFO_EXTENSION);
    rename($currFile, $backupFile);
  }
  rename($upload_path . $newFile, $upload_path . 'a' . substr($newFile, 1));
}
function declineNewPictureFile ($upload_path, $personId, $newFile) {
  $datetime = date('_Ymd_His.', filemtime($upload_path . $newFile));
  $backupFile = $upload_path . "declined/p$personId$datetime" . pathinfo($upload_path . $newFile, PATHINFO_EXTENSION);
  rename($upload_path . $newFile, $backupFile);
}
function getWaitingPictureFiles ($upload_path) {
  return array_map('basename', glob($upload_path . 'p*'));
}
