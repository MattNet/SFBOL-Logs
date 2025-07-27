<?php
###
# Provides the methods to access an SFB-online log file
###
# create( $log )
# - Populates itself from the provided log. This is a large string, as if from file_get_contents()
# read( $impulse )
# - Returns an array of each item that occurs during the given turn.impulse
# precedence()
# - Returns a list of each item, in order, than can occur in an impulse
###

require_once( __DIR__."/LogUnit.php" );

class LogFile
{
  public $error = ""; # error string, should something fail

  private $UNITS = array();
  private $UNIT_LOOKUP = array();

  private $ADDREGEX = "/(.*) \(Type:(.*?)\) has been added at (\d{4,4})/";
  private $FRAMEREGEX = "/^Impulse (\d*\.\d*):$/";
  private $PLAYERREGEX = "/^(.*?) has selected (.*?)$/";

  public const SEQUENCE_MOVEMENT_SHIPS		= 0;
  public const SEQUENCE_MOVEMENT_SHUTTLES	= 1;
  public const SEQUENCE_MOVEMENT_SEEKERS	= 2;
  public const SEQUENCE_MOVEMENT_TAC		= 3;
  public const SEQUENCE_ESG_DAMAGE		= 4;
  public const SEQUENCE_ENVELOPER_DAMAGE	= 5;
  public const SEQUENCE_SEEKER_DAMAGE		= 6;
  public const SEQUENCE_WEB_DAMAGE		= 7;
  public const SEQUENCE_BREAKDOWNS		= 8;
  public const SEQUENCE_SPEED_CHANGES		= 9;
  public const SEQUENCE_THOLIAN_WEB_PASS	= 10;
  public const SEQUENCE_EMER_DECEL_EFFECT	= 11;
  public const SEQUENCE_VOLUNTARY_FIRE_CONTROL	= 12;
  public const SEQUENCE_CLOAKING_DEVICE		= 13;
  public const SEQUENCE_TRACTORS		= 14;
  public const SEQUENCE_LABS			= 15;
  public const SEQUENCE_LAUNCH_PLASMA		= 16;
  public const SEQUENCE_LAUNCH_DRONES		= 17;
  public const SEQUENCE_ESGS			= 18;
  public const SEQUENCE_DROP_SHIELDS		= 19;
  public const SEQUENCE_TRANSPORTERS		= 20;
  public const SEQUENCE_MINES_ACTIVE		= 21;
  public const SEQUENCE_LAND_SHUTTLES		= 22;
  public const SEQUENCE_LAUNCH_SHUTTLES		= 23;
  public const SEQUENCE_ANNOUNCE_EMER_DECEL	= 24;
  public const SEQUENCE_DIS_DEV_DECLARATION	= 25;
  public const SEQUENCE_FIRE_DECLARATION	= 26;
  public const SEQUENCE_PPDS			= 27;
  public const SEQUENCE_FIRST_HELLBORES		= 28;
  public const SEQUENCE_DIRECT_FIRE		= 29;
  public const SEQUENCE_SECOND_HELLBORES	= 30;
  public const SEQUENCE_CAST_WEB		= 31;
  public const SEQUENCE_DAMAGE_ALLOCATION	= 32;
  public const SEQUENCE_DIS_DEV_OPERATE		= 33;
  public const SEQUENCE_IMPULSE_END		= 34;

  ###
  # Class constructor
  ###
  # Args are:
  # - (string) The entire log file to be parsed
  # Returns:
  # - None
  ###
  function __construct( $log )
  {
    if( is_string($log) == true ) # check if the log data is a string. if so, convert to array
      $log = explode( "\n", $log );
    else if( is_array( $log ) != true ) # if the log data is not an array or string, then exit
    {
      $error .= "Input of ${self::CLASS} constructor is not a string or array.\n";
      return( 1 );
    }

    # List of player for units.
    # $player_list[ unit ] = player
    $player_list = array();

    # go through each line of the input file
    foreach( $log as $lineNum => $line )
    {
  # ADDREGEX
      $status = preg_match( $this->ADDREGEX, $line, $matches );
      if( $status == 1 )
      {
        # skip those markers that we don't want to be units
        if( str_ends_with( $matches[2], "Point of Slip") )
          continue;
        if( str_ends_with( $matches[2], "Point of Turn") )
          continue;
        # go on with building the unit
        $this->UNITS[] = new LogUnit( $log, $lineNum );
        $unit_key = array_key_last( $this->UNITS );
        $unit_name = $this->UNITS[ $unit_key ]->name;
        $this->UNIT_LOOKUP[ $unit_name ] = $unit_key; # populate the reverse lookup
        # error reporting for the construction of the unit
        if( $this->UNITS[ $unit_key ]->error != "" )
          $this->error .= "Unit '$unit_name' errors:\n".$this->UNITS[ $unit_key ]->error;
        continue; # Go to next line if the ADDREGEX matched
      }
  # PLAYERREGEX
      $status = preg_match( $this->PLAYERREGEX, $line, $matches );
      if( $status == 1 )
        $player_list[ $matches[2] ] = $matches[1];
    }

    # Assign players to units:
    # This is being done post-unit-creation
    # Cloaking depends on this, due to how it's reported in the logs
    foreach( $player_list as $unit_type => $player_name )
    {
      foreach( $this->UNITS as &$unit_obj )
        if( $unit_obj->type == $unit_type )
          $unit_obj->owner = $player_name;
    }

    foreach( $this->UNITS as &$unit_obj )
      $unit_obj->postProcess( $log );

    # error reporting for the entire read
    if( $this->error != "" )
      echo $this-error;
  }

  ###
  # Shows all of the weapons that have been fired, by the named unit
  ###
  # Args are:
  # - (string) The name of the unit, as found in the reverse lookup
  # Returns:
  # - (array) collection of weapons in the format [] = array( [weapon],[number],[arc] )
  ###
  function get_weapons( $name )
  {
    if( ! isset( $this->UNIT_LOOKUP[ $name ] ) )
    {
      $this->error .= " get_weapons(): Cannot find unit '$name' in list of units.\n";
      return false;
    }
    $unitIndex = $this->UNIT_LOOKUP[ $name ];
    return $this->UNITS[ $unitIndex ]->weapons;
  }

  ###
  # Shows all of the units used in the game
  ###
  # Args are:
  # - None
  # Returns:
  # - (array) Collection of units and their information
  ###
  function get_units ()
  {
    $output = array();
    foreach( $this->UNIT_LOOKUP as $unit=>$value )
      $output[] = array(
        "added"=> $this->UNITS[ $value ]->added,
        "basic"=> $this->UNITS[ $value ]->basicType,
        "name"=>$unit,
        "removed"=> $this->UNITS[ $value ]->removed,
        "type"=> $this->UNITS[ $value ]->type
      );
    return $output;
  }

  ###
  # Shows all items from a certain impulse, in order of segments
  ###
  # Args are:
  # - (string) The time to examine, in 'turn.impulse' notation
  # Returns:
  # - (array) all of the actions for this impulse
  # format is array( self::SEQUENCE_xxx => array( 0=>array( 0=>action, 1=>reason, "owner"=>originating_ship ) ) )
  ###
  function read( $time )
  {
    $time = LogUnit::convertToImp( $time );
    $output = array();
    foreach( $this->UNITS as $unit )
    {
      # get the orders for this unit for this impulse
      $impulse = $unit->read( $time );

      # skip if nothing happened on this impulse from this unit
      if( ! $impulse )
        continue;

      # go through each order for this unit
      foreach( $impulse as $key=>$value )
      {
        switch( $key )
        {
        case "add":
          switch( $unit->basicType )
          {
          case "esg":
            $phase = self::SEQUENCE_ESGS;
            break;
          case "dis dev":
            $phase = self::SEQUENCE_DIS_DEV_DECLARATION;
            break;
          case "drone":
            $phase = self::SEQUENCE_LAUNCH_DRONES;
            break;
          case "plasma":
            $phase = self::SEQUENCE_LAUNCH_PLASMA;
            break;
          case "ppd":
            $phase = self::SEQUENCE_FIRE_DECLARATION;
            break;
          case "ship":
            $phase = self::SEQUENCE_MOVEMENT_SHIPS;
            break;
          case "shuttle":
            $phase = self::SEQUENCE_LAUNCH_SHUTTLES;
            break;
          case "web":
            $phase = self::SEQUENCE_CAST_WEB;
            break;
          }
          if( ! isset($output[ $phase ]) || ! is_array($output[ $phase ]) )
            $output[ $phase ] = array();
          $output[ $phase ][] = $value;

          break;

        case "cloak":
          $phase = self::SEQUENCE_CLOAKING_DEVICE;

          if( ! isset($output[ $phase ]) || ! is_array($output[ $phase ]) )
            $output[ $phase ] = array();
          $output[ $phase ][] = $value;

          break;
        case "damage":
          $phase = self::SEQUENCE_DAMAGE_ALLOCATION;

          if( ! isset($output[ $phase ]) || ! is_array($output[ $phase ]) )
            $output[ $phase ] = array();
          # capture the second dimension arrays as the output
          foreach( $value as $out )
            $output[ $phase ][] = $out;

          break;
        case "facing":
        case "location":
          # place units that are added
          if( isset($value[1]) && ( $value[1] == "Launch" || $value[1] == "Add" ) )
          {
            switch( $unit->basicType )
            {
            case "esg":
              $phase = self::SEQUENCE_ESGS;
              break;
            case "drone":
              $phase = self::SEQUENCE_LAUNCH_DRONES;
              break;
            case "plasma":
              $phase = self::SEQUENCE_LAUNCH_PLASMA;
              break;
            case "ship":
              $phase = self::SEQUENCE_MOVEMENT_SHIPS;
              break;
            case "shuttle":
              $phase = self::SEQUENCE_LAUNCH_SHUTTLES;
              break;
            case "web":
              $phase = self::SEQUENCE_CAST_WEB;
              break;
            }
          }
          # move TACcing units
          else if( $unit->getCurrentSpeed( $time ) === 0 )
            $phase = self::SEQUENCE_MOVEMENT_TAC;
          # move everything else
          else
            switch( $unit->basicType )
            {
            case "ship":
              $phase = self::SEQUENCE_MOVEMENT_SHIPS;
              break;
            case "shuttle":
              $phase = self::SEQUENCE_MOVEMENT_SHUTTLES;
              break;
            case "drone":
            case "plasma":
              $phase = self::SEQUENCE_MOVEMENT_SEEKERS;
              break;
            }

          if( ! isset($output[ $phase ]) || ! is_array($output[ $phase ]) )
            $output[ $phase ] = array();
          $output[ $phase ][] = $value;

          break;
        case "fire":
          $phase = self::SEQUENCE_FIRE_DECLARATION;

          if( ! isset($output[ $phase ]) || ! is_array($output[ $phase ]) )
            $output[ $phase ] = array();
          # capture the second dimension arrays as the output
          foreach( $value as $out )
            $output[ $phase ][] = $out;

          break;
        case "speed":
          $phase = self::SEQUENCE_SPEED_CHANGES;

          if( ! isset($output[ $phase ]) || ! is_array($output[ $phase ]) )
            $output[ $phase ] = array();
          $output[ $phase ][] = $value;

          break;
        case "remove":
          switch( $unit->basicType )
          {
          case "dis dev":
            $phase = self::SEQUENCE_DIS_DEV_DECLARATION;
            break;
          case "esg":
            $phase = self::SEQUENCE_ESGS;
            break;
          case "ppd":
            $phase = self::SEQUENCE_PPDS;
            break;
          case "web":
            $phase = self::SEQUENCE_CAST_WEB;
            break;
          case "drone":
          case "plasma":
          case "ship":
          case "shuttle":
          default:
            $phase = self::SEQUENCE_IMPULSE_END;
            break;
          }

          if( ! isset($output[ $phase ]) || ! is_array($output[ $phase ]) )
            $output[ $phase ] = array();
          $output[ $phase ][] = $value;

          break;
        case "tractordown":
        case "tractorup":
          $phase = self::SEQUENCE_TRACTORS;

          if( ! isset($output[ $phase ]) || ! is_array($output[ $phase ]) )
            $output[ $phase ] = array();
          $output[ $phase ][] = $value;

          break;
        default:
          $phase = 99;

          if( ! isset($output[ $phase ]) || ! is_array($output[ $phase ]) )
            $output[ $phase ] = array();
          $output[ $phase ][] = $value;

          break;
        }
      }
    }
    return $output;
  }

  ###
  # Shows all impulses from a certain unit
  ###
  # Args are:
  # - (string) The unit name to examine
  # Returns:
  # - (array) all of the actions for this unit
  ###
  function readAll( $unitName )
  {
    if( ! isset( $this->UNIT_LOOKUP[ $unitName ] ) )
    {
      $this->error .= $this->class."::readAll(): Cannot find unit '$unitName' in list of units.\n";
      return false;
    }
    $unitIndex = $this->UNIT_LOOKUP[ $unitName ];
    return $this->UNITS[ $unitIndex ]->readAll();
  }

  ###
  # Shows all sequence constants
  ###
  # Args are:
  # - None
  # Returns:
  # - (array) A list of the constants
  ###
  function get_sequence ()
  {
    $output = array();
    $fooClass = new ReflectionClass ( $this::class );
    $constants = $fooClass->getConstants();

    foreach ( $constants as $name => $value )
      if( str_starts_with( $name, "SEQUENCE_" ) )
        $output[ $value ] = $name;
    return $output;
  }
}

?>
