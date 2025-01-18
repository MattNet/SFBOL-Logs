<?php
###
# Provides the methods to access a unit from an SFB-online log file
###
# create( $log, $time )
# - Populates itself from the provided log. This is a large string, as if from file_get_contents()
# read( $impulse )
# - Returns an array of each item that occurs during the given turn.impulse
# readAll()
# - Returns an array of every item that this unit performed
# convertToImp( $time )
# - Converts the 'turn.impulse' notation to number of impulses
# convertFromImp( $time )
# - Converts from number of impulses to the 'turn.impulse' notation
###


class LogUnit
{
  public $error = ""; # error string, should something fail
  public $name = ""; # name of the unit
  public $type = ""; # the unit type (e.g. "LDR TCWL")
  public $basicType = ""; # the basic unit type (e.g. "ship", "drone", etc...)
  public $weapons = array(); # The type of weapons and their ID number. Format is [] = array( [weapon],[number],[arc] )

  # Key format is number of impulses from start (turn 1, impulse 1 is "1". Turn 3, impulse 32 is "96")
  # value is array of actions. Action contents vary by action, but most will be associative arrays.
  protected $impulses = array();

  private $ADDREGEX = "/^(.*) \(Type:(.*?)\) has been added at (\d{4,4})(?:, direction (\w+), speed (\d+))?/";
  private $DAMAGEREGEX = "/^Allocation of damage for: (.*)$/";
  private $DMGAREGEX = "/^Damage: (\d+)\/(\d+)\/(\d+)\/(\d+)\/(\d+)\/(\d+) \(Total: (\d+)\)$/";
  private $DMGBREGEX = "/^Shield Reinforcement: (\d+)\/(\d+)\/(\d+)\/(\d+)\/(\d+)\/(\d+)$/";
  private $FACINGREGEX = "/^(.+) has changed to facing (\w+) after (.+) move\(s\)$/";
  private $FRAMEREGEX = "/^Impulse (\d*\.\d*):$/";
  private $INTERNALSREGEX = "/^Total # of Internals = (\d+)$/";
  private $LOCATIONREGEX = "/^(.*) has (moved|side-slipped|turned) to (\d{2,2})(\d{2,2})(\w+)$/";
  private $REMOVEREGEX = "/^(.+) has been removed$/";
  private $SPEEDREGEX = "/^(.+) (changed|initial) speed to (\d+)$/";
  private $TRACTORDOWNREGEX = "/^(.+) drops tractor on (.+)$/";
  private $TRACTORUPREGEX = "/^(.+) tractors (.+)$/";
  private $WEAPONREGEX = "/^(.*) fires (.+) #(\w+) \((.+)\) at (.*?) (using .*)?\(Range: (\d+)\)$/";
  private $pointerTime = 0; # tracks the last impulse found, so any events can go to the right impulse
  ###
  # Class constructor
  ###
  # Args are:
  # - (string) The log file. Say, as if from a get_file_contents() call
  # - (integer) [optional] The impulse offset to pull stuff into the object.
  # Returns:
  # - None
  ###
  function __construct( $log, int $offset=0 )
  {
    if( is_string($log) == true ) # check if the log data is a string. if so, convert to array
      $log = explode( "\n", $log );
    else if( is_array( $log ) != true ) # if the log data is not an array or string, then exit
    {
      $error .= " Input of {self::CLASS} constructor is not a string or array.";
      return( 1 );
    }

    # go through each line of the input file
    foreach( $log as $lineNum => $line )
    {
  # FRAMEREGEX
      $status = preg_match( $this->FRAMEREGEX, $line, $matches );
      if( $status == 1 )
      {
        $this->pointerTime = self::convertToImp( $matches[1] );
        if( $this->pointerTime === null )
          $this->error .= "Impulse conversion in the wrong format. Given '{$matches[1]}'.";
        continue; # Go to next line if the FRAMEREGEX matched
      }

  # Skip any regex of the lines except tracking the impulse. This saves time, but lets us 
  # know when the ADDREGEX occurred
      if( $offset > $lineNum )
        continue;

  # ADDREGEX
      $status = preg_match( $this->ADDREGEX, $line, $matches );
      if( $status == 1 )
      {
        if( $this->type != "" )
          continue; # skip adding more if we have already defined this object
        $this->name = $matches[1];
        $this->type = $matches[2];
        $this->basicType = self::get_basic_type( $this->type );
        $this->modify( $this->pointerTime, "add", $this->type );
        $this->modify( $this->pointerTime, "location", $matches[3] );
        if( ! isset($matches[4]) )
          $this->modify( $this->pointerTime, "facing", "A", "0" );
        else
          $this->modify( $this->pointerTime, "facing", $matches[4], "0" );
        if( ! isset($matches[5]) )
          $this->modify( $this->pointerTime, "speed", 0 );
        else
          $this->modify( $this->pointerTime, "speed", $matches[5] );
        continue; # Go to next line if the ADDREGEX matched
      }
  # DAMAGEREGEX
      $status = preg_match( $this->DAMAGEREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        $internals = 0;
        # get the total damage from the second line
        $status = preg_match( $this->DMGAREGEX, $log[$lineNum+1], $matches );
        if( $status == 1 )
          $damage = $matches[7]; # pull the total damage from the second line
        else
        {
          $this->error .= "Damage announcement line without subsequent allocation. Line ".($lineNum+1)."\n";
          continue;
        }
        # get the total reinforcement from the third line
        $status = preg_match( $this->DMGBREGEX, $log[$lineNum+2], $matches );
        if( $status == 1 )
          $damage -= $matches[1]+$matches[2]+$matches[3]+$matches[4]+$matches[5]+$matches[6];
        else
        {
          $this->error .= "Damage announcement line without subsequent reinforcement allocation. Line ".($lineNum+2)."\n";
          continue;
        }
        # get the internals from the fifth line (if applicable)
        $status = preg_match( $this->INTERNALSREGEX, $log[$lineNum+5], $matches );
        if( $status == 1 )
          $internals = $matches[1];
        $this->modify( $this->pointerTime, "damage", $damage, $internals );
        continue; # Go to next line if the DAMAGEREGEX matched
      }
  # FACINGREGEX
      $status = preg_match( $this->FACINGREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        $this->modify( $this->pointerTime, "facing", $matches[2], $matches[3] );
        continue; # Go to next line if the FACINGREGEX matched
      }
  # LOCATIONREGEX
      $status = preg_match( $this->LOCATIONREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        # create the "reason" for the facing
        if( $matches[2] == "moved" )
          $reason = "move";
        else if( $matches[2] == "turned" )
          $reason = "turn";
        else
          $reason = "side-slip";
        $this->modify( $this->pointerTime, "facing", $matches[5], $reason );
        $this->modify( $this->pointerTime, "location", $matches[3].$matches[4] );
        continue; # Go to next line if the LOCATIONREGEX matched
      }
  # REMOVEREGEX
      $status = preg_match( $this->REMOVEREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        $this->modify( $this->pointerTime, "remove", $this->name );
        continue; # Go to next line if the REMOVEREGEX matched
      }
  # SPEEDREGEX
      $status = preg_match( $this->SPEEDREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        $this->modify( $this->pointerTime, "speed", $matches[3] );
        continue; # Go to next line if the SPEEDREGEX matched
      }
  # TRACTORDOWNREGEX
      $status = preg_match( $this->TRACTORDOWNREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        $this->modify( $this->pointerTime, "tractordown", $matches[2] );
        continue; # Go to next line if the TRACTORDOWNREGEX matched
      }
  # TRACTORUPREGEX
      $status = preg_match( $this->TRACTORUPREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        $this->modify( $this->pointerTime, "tractorup", $matches[2] );
        continue; # Go to next line if the TRACTORUPREGEX matched
      }
  # WEAPONREGEX
      $status = preg_match( $this->WEAPONREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        $arc = $matches[4];
        $wpn = $matches[2];
        $wpnID = $matches[3];
        $this->modify( $this->pointerTime, "fire", $wpn." #".$wpnID, $matches[5] );
        # look through $this->weapons, so that we don't double-up on weapons
        $stuffer = array( $wpn, $wpnID, $arc );
        if( ! in_array( $stuffer, $this->weapons ) )
          $this->weapons[] = $stuffer;
        continue; # Go to next line if the WEAPONREGEX matched
      }
    }
  }

  ###
  # Shows all items from a certain impulse
  ###
  # Args are:
  # - (string) The time to examine, in 'turn.impulse' notation
  # Returns:
  # - (array) All items from this impulse. NULL if none
  ###
  function read( $time )
  {
    $time = self::convertToImp( $time );
    if( $time === null )
    {
      $this->error .= "Invalid time format in read(). Given '$time'.\n";
      return NULL;
    }
    if( ! isset($this->impulses[$time]) )
    {
      $this->error .= "Time given to read() is not recorded in the log file.\n";
      return NULL;
    }
    return $this->impulses[$time];
  }

  ###
  # Shows all impulse items
  ###
  # Args are:
  # - None
  # Returns:
  # - (array) the 'impulses' array
  ###
  function readAll()
  {
    return $this->impulses;
  }

  ###
  # retrieves the speed of the unit for the current impulse
  ###
  # Args are:
  # - (string) The time to examine, in 'turn.impulse' notation
  # Returns:
  # - (int) The last speed change
  ###
  function getCurrentSpeed( $time )
  {
    $output = 0;
    $time = self::convertToImp( $time );
    if( $time === null )
    {
      $this->error .= "Invalid time format in read(). Given '$time'.\n";
      return NULL;
    }
    if( ! isset($this->impulses[$time]) )
    {
      $this->error .= "Time given to getCurrentSpeed() is not recorded in the log file.\n";
      return NULL;
    }
    $input = $this->readAll();
    foreach( $input as $impulse=>$actions )
    {
      if( $time < $impulse )
        break;
      if( isset( $actions["speed"] ) )
        $output = $actions["speed"];
    }
    return $output;
  }

  ###
  # Modifies $impulses for the given action
  ###
  # Args are:
  # - (int) The time, in impulses notation
  # - (string) The type of action. allowed keywords are:
  #     add, facing, fire, location
  # - (string) The value for the action
  # - (string) A single note for the action
  # Returns:
  # - (boolean) True if successful
  ###
  function modify( $time, $type, $value, $reason = "" )
  {
    $time = self::convertToImp( $time );
    if( $time === null )
    {
      $this->error .= "Invalid time format in read(). Given '$time'.\n";
      return NULL;
    }
    if( ! isset($this->impulses[ $time ]) )
      $this->impulses[ $time ] = array();
    switch( strtolower($type) )
    {
    case "add":
      $this->impulses[ $time ]["add"] = $value;
      break;
    case "damage":
      $output = array( $value );
      if( $reason != "" )
        $output[] = $reason;
      $this->impulses[ $time ]["damage"] = $output;
      break;
    case "facing":
      $output = array( $value );
      if( $reason != "" )
        $output[] = $reason;
      $this->impulses[ $time ]["facing"] = $output;
      break;
    case "fire":
      $output = array( $value );
      if( $reason != "" )
        $output[] = $reason;
      $this->impulses[ $time ]["fire"][] = $output;
      break;
    case "location":
    # I would make this an array, to handle Sabots and displacemnt devices.
    # but those are much less common than multiple miss-movements
      $this->impulses[ $time ]["location"] = intval($value);
      break;
    case "remove":
      $this->impulses[ $time ]["remove"] = $value;
      break;
    case "speed":
      $this->impulses[ $time ]["speed"] = intval($value);
      break;
    case "tractordown":
      $output = array( $value );
      if( $reason != "" )
        $output[] = $reason;
      $this->impulses[ $time ]["tractordown"][] = $output;
      break;
    case "tractorup":
      $output = array( $value );
      if( $reason != "" )
        $output[] = $reason;
      $this->impulses[ $time ]["tractorup"][] = $output;
      break;
    }
    return TRUE;
  }

  ###
  # Converts the 'turn.impulse' notation to number of impulses
  ###
  # Args are:
  # - (string) The time, in 'turn.impulses' notation
  # Returns:
  # - (int) The number of impulses that have occurred. Null for an error
  ###
  static function convertToImp( $time )
  {
    if( ! str_contains( $time, "." ) )
    {
      # check if this has been converted already
      if( intval($time) == $time )
        return $time;
      # fail if this is in the wrong format
      else if( ! str_contains( $time, "." ) )
        return null;
    }
    list( $turns, $imps ) = explode( ".", $time );
    return ( (($turns-1) * 32) + $imps );
  }
  ###
  # Converts from number of impulses to the 'turn.impulse' notation
  ###
  # Args are:
  # - (int) The time, in number of impulses
  # Returns:
  # - (string) The time in 'turns.impulses' notation
  ###
  static function convertFromImp( $time )
  {
    if( str_contains( $time, "." ) )
      return $time; # check if this has been converted already
    # Turns are 1-based, so add 1 to the resulting division
    $turns = intval($time / 32) +1;
    # It is the last impulse (32) if the modulos comes up 0
    $imps = $time % 32;
    if( $imps == 0 )
    {
      $imps = 32;
      $turns -= 1;
    }      
    return "$turns.$imps";
  }
  ###
  # Determines the basic unit type from the specific unit type
  # e.g. "LDR TCWL" is a basic type "ship"
  ###
  # Args are:
  # - (string) The specific unit type
  # Returns:
  # - (string) The basic unit type
  ###
  static function get_basic_type( $type )
  {
    $type = strtolower( $type );
    # handle all types of drones
    if( substr( $type, -5 ) == "drone" )
      return "drone";
    # handle all types of shuttles
    if( substr( $type, -6 ) == "plasma" )
      return "plasma";
    # handle all types of shuttles
    if( substr( $type, -7 ) == "shuttle" )
      return "shuttle";
    if( substr( $type, -7 ) == "fighter" )
      return "shuttle";
    # handle random types of markers
    # ESGs
    if( substr( $type, -3 ) == "esg" )
      return "esg";
    # Dis Dev marker
    if( substr( $type, -6 ) == "disdev" )
      return "dis dev";
    # PPD marker
    if( substr( $type, -3 ) == "ppd" )
      return "ppd";
    # Web marker
    if( substr( $type, -3 ) == "web" )
      return "web";

    #assume anything left is a ship
    return "ship";
  }
}
?>
