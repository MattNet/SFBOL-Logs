<?php
###
# Provides the methods to access a unit from an SFB-online log file
###
# __construct( $log, $offset=0 )
# - Extracts the information for the first unit found that is added
# read( $time )
# - Shows all items from a certain impulse
# readAll()
# - Shows all impulse items
# removeAction( $time, $action )
# - Removes the action at $time impulses.
# getCurrentSpeed( $time )
# - retrieves the speed of the unit for the current impulse
# isHetTac( $new_facing, $old_facing, $speed )
# - Determines if a facing change is a HET or a TAC
# (static) function convertToImp( $time )
# - Converts the 'turn.impulse' notation to number of impulses
# (static) convertFromImp( $time )
# - Converts from number of impulses to the 'turn.impulse' notation
# (static) convertToTurn( $time )
# - splits the 'turn.impulse' notation to turn and impulse
# (static) get_basic_type( $type )
# - Determines the basic unit type from the specific unit type
# - e.g. "LDR TCWL" is a basic type "ship"
###


class LogUnit
{
  public $added = 0; # When the unit was added to the board
  public $error = ""; # error string, should something fail
  public $name = ""; # name of the unit
  public $owner = ""; # the player who decides for the unit
  public $removed = 0; # when the unit was removed from the board (Last impulse, if not removed)
  public $type = ""; # the unit type (e.g. "LDR TCWL")
  public $basicType = ""; # the basic unit type (e.g. "ship", "drone", etc...)
  public $weapons = array(); # The type of weapons and their ID number. Format is [] = array( [weapon],[number],[arc] )

  # Key format is number of impulses from start (turn 1, impulse 1 is "1". Turn 3, impulse 32 is "96")
  # value is array of actions. Action contents vary by action, but most will be associative arrays.
  protected $impulses = array();

  private $ADDREGEX = "/^(.*) \(Type:(.*?)\) has been added at (\d{4,4})(?:, direction (\w+), speed (\d+))?/";
  private $CLOAKREGEX = "/^Activity Orders \(Segment: 6B02.01, Activate\/deactivate cloaking device.\)/";
  private $CLOAKWHOREGEX = "/^(.+?) orders are/";
  private $DAMAGEREGEX = "/^Allocation of damage for: (.*)$/";
  private $DMGAREGEX = "/^Damage: (\d+)\/(\d+)\/(\d+)\/(\d+)\/(\d+)\/(\d+) \(Total: (\d+)\)$/";
  private $DMGBREGEX = "/^Shield Reinforcement: (\d+)\/(\d+)\/(\d+)\/(\d+)\/(\d+)\/(\d+)$/";
  private $FACINGREGEX = "/^(.+) has changed to facing (\w+) after (.+) move\(s\)$/";
  private $FRAMEREGEX = "/^Impulse (\d*\.\d*):$/";
  private $INTERNALSREGEX = "/^Total # of Internals = (\d+)$/";
  private $LOCATIONREGEX = "/^(.*) has (moved|side-slipped|turned) to (\d{4,4})(\w+)$/";
  private $REMOVEREGEX = "/^(.+) has been (?:removed|discarded)$/";
  private $SPEEDREGEX = "/^(.+) (changed|initial) speed to (\d+)$/";
  private $TRACTORDOWNREGEX = "/^(.+) drops tractor on (.+)$/";
  private $TRACTORUPREGEX = "/^(.+) tractors (.+)$/";
  private $WEAPONREGEX = "/^(.*) fires (.+) #(\w+) \((.+)\) at (.*?) (using .*)?\(Range: (\d+)\)$/";
  private $pointerFacing = "A"; # tracks the last facing found. Used to determine HETs
  private $pointerSpeed = 0; # tracks the last speed change found. Used to determine TACs
  private $pointerTime = 0; # tracks the last impulse found, so any events can go to the right impulse
  private $lastLocation = ""; # The last recorded location of this unit, for impulse activity
  private $flagNeedMoreLogFile = false; # Flag for post-processing: do we need to iterate through the logfile again?

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
          $this->error .= "Impulse conversion in the wrong format. Given '{$matches[1]}',  Unit '".$this->name."'.";
        continue; # Go to next line if the FRAMEREGEX matched
      }

  # Skip any regex of the lines except tracking the impulse. This saves time, but lets us 
  # know when the ADDREGEX occurred
      if( $offset > $lineNum )
        continue;

  # ADDREGEX
  # Note that a Re-Sync can remove, then add a unit on the same impulse
      $status = preg_match( $this->ADDREGEX, $line, $matches );
      # If the pregex matched and if we have not already defined this object
      if( $status == 1 && $this->type == "" )
      {
        # check if this is a new unit or simply a new location
        if( $this->type == "" )
          # New unit. Name and type are unknown until now
          list( , $this->name, $this->type, $location ) = $matches;
        else
          list( , , , $location ) = $matches;

        # fill out the object and tag information
        $this->added = $this->pointerTime;
        $this->basicType = self::get_basic_type( $this->type );
        $output = array( "Add"=>$this->pointerTime, "facing"=>"A", "location"=>$location, "speed"=>0, "type"=>$this->type, "owner"=>$this->name );
        if( isset($matches[4]) )
          $output["facing"] = $matches[4];
        if( isset($matches[5]) )
          $output["speed"] = intval($matches[5]);

        # add the tag information to the impulse
        if( ! isset($this->impulses[ $this->pointerTime ]) )
          $this->impulses[ $this->pointerTime ] = array();
        $this->impulses[ $this->pointerTime ]["add"] = $output;

        continue; # Go to next line if the ADDREGEX matched
      }
  # CLOAKREGEX
      $status = preg_match( $this->CLOAKREGEX, $line, $matches );
      if( $status == 1 )
      {
        # Mark this as needing post-processing.
        # Due to how it is reported in the logs, cloaking is marked based on the player controlling the unit, not on the unit name
        # This means that it is not always accurate that this is the unit doing the cloaking.
        $this->flagNeedMoreLogFile = true;
      }
  # DAMAGEREGEX
      $status = preg_match( $this->DAMAGEREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        $damage = 0;
        $internals = 0;
        $reinforcement = 0;
        # get the total damage from the second line
        $status = preg_match( $this->DMGAREGEX, $log[$lineNum+1], $matches );
        if( $status == 1 )
          $damage = intval($matches[7]); # pull the total damage from the second line
        else
        {
          $this->error .= "Damage announcement line without subsequent allocation. Unit '".$this->name."', Line ".($lineNum+1)."\n";
          continue;
        }
        # get the total reinforcement from the third line
        $status = preg_match( $this->DMGBREGEX, $log[$lineNum+2], $matches );
        if( $status == 1 )
          $reinforcement = intval($matches[1]+$matches[2]+$matches[3]+$matches[4]+$matches[5]+$matches[6]);
        else
        {
          $this->error .= "Damage announcement line without subsequent reinforcement allocation. Unit '".$this->name."', Line ".($lineNum+2)."\n";
          continue;
        }
        # get the internals from the fifth line (if applicable)
        $status = preg_match( $this->INTERNALSREGEX, $log[$lineNum+5], $matches );
        if( $status == 1 )
          $internals = intval($matches[1]);

        $shields = $damage - $reinforcement - $internals;
        $output = array( "internals"=>$internals, "owner"=>$this->name, 
                         "owner location"=>$this->lastLocation, 
                         "reinforcement"=>$reinforcement, "shields"=>$shields,
                         "total"=>$damage
                       );

        # add the tag information to the impulse
        if( ! isset($this->impulses[ $this->pointerTime ]) )
          $this->impulses[ $this->pointerTime ] = array();
        # this is an array of damages, because damage can happen many times an impulse
        $this->impulses[ $this->pointerTime ]["damage"][] = $output;

        continue; # Go to next line if the DAMAGEREGEX matched
      }
  # FACINGREGEX
      $status = preg_match( $this->FACINGREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        list( , , $newFacing, $moves ) = $matches;
        $reason = $this->isHetTac( $newFacing, $this->pointerFacing, $this->pointerSpeed );
        if( $reason == "" )
          $reason = $moves; # set to the number of movement since the last turn if not a HET or TAC

        # fill out the object and tag information
        $output = array( "facing"=>$newFacing, "owner"=>$this->name, "turn"=>$reason );
        $this->pointerFacing = $newFacing; # set pointerFacing after the HET check

        # add the tag information to the impulse
        if( ! isset($this->impulses[ $this->pointerTime ]) )
          $this->impulses[ $this->pointerTime ] = array();
        $this->impulses[ $this->pointerTime ]["facing"] = $output;

        continue; # Go to next line if the FACINGREGEX matched
      }
  # LOCATIONREGEX
      $status = preg_match( $this->LOCATIONREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        list( , , $type, $location, $facing ) = $matches;
        # determine the method of facing change
        if( $type == "moved" )
          $reason = "move";
        else if( $type == "side-slipped" )
          $reason = "side-slip";
        else
        {
          $reason = $this->isHetTac( $facing, $this->pointerFacing, $this->pointerSpeed );
          if( $reason == "" )
            $reason = "turn";
        }

        # fill out the object and tag information
        $output = array( "facing"=>$facing, "location"=>$location, "owner"=>$this->name, "turn"=>$reason );
        $this->pointerFacing = $facing; # set pointerFacing after the HET check

        # add the tag information to the impulse
        if( ! isset($this->impulses[ $this->pointerTime ]) )
          $this->impulses[ $this->pointerTime ] = array();
        $this->impulses[ $this->pointerTime ]["location"] = $output;

        # update the latest location
        $this->lastLocation = $location;

        continue; # Go to next line if the LOCATIONREGEX matched
      }
  # REMOVEREGEX
  # Note that a Re-Sync can remove, then add a unit on the same impulse
      $status = preg_match( $this->REMOVEREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        # fill out the object and tag information
        $output = array( "add"=>$this->added, "owner"=>$this->name, "remove"=>$this->pointerTime, "type"=>$this->type );
        $this->removed = $this->pointerTime;

        # add the tag information to the impulse
        if( ! isset($this->impulses[ $this->pointerTime ]) )
          $this->impulses[ $this->pointerTime ] = array();
        $this->impulses[ $this->pointerTime ]["remove"] = $output;

        continue; # Go to next line if the REMOVEREGEX matched
      }
  # SPEEDREGEX
      $status = preg_match( $this->SPEEDREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        # fill out the object and tag information
        $this->pointerSpeed = intval($matches[3]);
        $output = array( "owner"=>$this->name, "speed"=>$this->pointerSpeed );

        # add the tag information to the impulse
        if( ! isset($this->impulses[ $this->pointerTime ]) )
          $this->impulses[ $this->pointerTime ] = array();
        $this->impulses[ $this->pointerTime ]["speed"] = $output;

        continue; # Go to next line if the SPEEDREGEX matched
      }
  # TRACTORDOWNREGEX
      $status = preg_match( $this->TRACTORDOWNREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        # fill out the tag information
        $output = array( "owner"=>$this->name, "owner location" => $this->lastLocation,
                         "target"=>$matches[2], "tractordown"=>$this->pointerTime
                       );

        # add the tag information to the impulse
        if( ! isset($this->impulses[ $this->pointerTime ]) )
          $this->impulses[ $this->pointerTime ] = array();
        $this->impulses[ $this->pointerTime ]["tractordown"] = $output;

        continue; # Go to next line if the TRACTORDOWNREGEX matched
      }
  # TRACTORUPREGEX
      $status = preg_match( $this->TRACTORUPREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        # fill out the tag information
        $output = array( "owner"=>$this->name, "owner location" => $this->lastLocation,
                         "target"=>$matches[2], "tractorup"=>$this->pointerTime
                       );

        # add the tag information to the impulse
        if( ! isset($this->impulses[ $this->pointerTime ]) )
          $this->impulses[ $this->pointerTime ] = array();
        $this->impulses[ $this->pointerTime ]["tractorup"] = $output;

        continue; # Go to next line if the TRACTORUPREGEX matched
      }
  # WEAPONREGEX
      $status = preg_match( $this->WEAPONREGEX, $line, $matches );
      if( $status == 1 && $matches[1] == $this->name )
      {
        list( , , $wpn, $wpnID, $arc, $target ) = $matches;
        $range = end($matches); # optionally, the firing mode of the weapon preceeds the range entry
        # fill out the object and tag information
        $output = array( "arc"=>$arc, "id"=>$wpnID, "owner"=>$this->name,
                         "owner location" => $this->lastLocation, 
                         "range"=>$range, "target"=>$target, "weapon"=>$wpn
                       );

        # Add to $this->weapons
        $stuffer = array( "arc"=>$arc, "id"=>$wpnID, "weapon"=>$wpn );
        # make sure our input array does not already exist so that we don't double-up entries
        if( ! in_array( $stuffer, $this->weapons ) )
          $this->weapons[] = $stuffer;

        # add the tag information to the impulse
        if( ! isset($this->impulses[ $this->pointerTime ]) )
          $this->impulses[ $this->pointerTime ] = array();
        $this->impulses[ $this->pointerTime ]["fire"][] = $output;

        continue; # Go to next line if the WEAPONREGEX matched
      }
    }
    # if we never saw a "removed" statement for this unit, then set "removed" to the last impulse
    if( $this->removed == 0 )
      $this->removed = $this->pointerTime;
  }

  ###
  # Perform post object-creation processes
  # These are self-modifying processes, due to things that happen after this object or other objects are instantiated
  ###
  # Args are:
  # - None
  # Returns:
  # - None
  ###
  function postProcess( $log )
  {
    if( $this->flagNeedMoreLogFile )
      # go through each line of the input file (again)
      # This is to fill in things that are missed, now that we have more 
      # knowledge of what is in the log file
      foreach( $log as $lineNum => $line )
      {
    # FRAMEREGEX
        $status = preg_match( $this->FRAMEREGEX, $line, $matches );
        if( $status == 1 )
        {
          $this->pointerTime = self::convertToImp( $matches[1] );
          if( $this->pointerTime === null )
            $this->error .= "Impulse conversion in the wrong format. Given '{$matches[1]}',  Unit '".$this->name."'.";
          continue; # Go to next line if the FRAMEREGEX matched
        }
    # CLOAKREGEX
        $status = preg_match( $this->CLOAKREGEX, $line, $matches );
        if( $status == 1 )
        {
          # Match the player making the announcement to this unit
          $status = preg_match( $this->CLOAKWHOREGEX, $log[$lineNum+1], $matches );
          if( $status == 1 && $matches[1] == $this->owner )
          {
            $output = array( "owner"=>$this->name, "owner location" => $this->lastLocation );
            $this->impulses[ $this->pointerTime ]["cloak"] = $output;
          }
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
      $this->error .= "Invalid time format in read(). Given '$time', Unit '".$this->name."'.\n";
      return NULL;
    }
    if( ! isset($this->impulses[$time]) )
    {
      $this->error .= "Time given to read() is not recorded in the log file: Unit '".$this->name."', Turn ".self::convertFromImp($time)."\n";
      return NULL;
    }
    return $this->impulses[$time];
  }

  ###
  # Shows all impulse segment items
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
  # Removes the action at $time impulses
  ###
  # Args are:
  # - (string) The time to examine, in 'impulse' notation
  # - (string) The action to remove
  # Returns:
  # - (boolean) True for success
  ###
  function removeAction( $time, $action )
  {
    # sanitize the $time input
    $time = self::convertToImp( $time );
    if( $time === null )
    {
      $this->error .= "Invalid time format in removeAction(). Given '$time', Unit '".$this->name."'.\n";
      return false;
    }
    if( ! isset($this->impulses[$time]) )
    {
      $this->error .= "Time given to removeAction() is not recorded in the log file: Unit '".$this->name."', Turn ".self::convertFromImp($time)."\n";
      return false;
    }

    # sanitize the $action input
    if( ! isset($this->impulses[$time][$action]) )
    {
      $this->error .= "Action given to removeAction() does not exist at $time. Unit '".$this->name."'\n";
      return false;
    }

    # erase the action
    unset($this->impulses[$time][$action]);
    # if the impulse is empty, remove the impulse entry
    if( empty($this->impulses[$time]) )
      unset($this->impulses[$time]);

    return true;
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
      $this->error .= "Invalid time format in read(). Given '$time', Unit '".$this->name."'.\n";
      return NULL;
    }
    if( ! isset($this->impulses[$time]) )
    {
      $this->error .= "Time given to getCurrentSpeed() is not recorded in the log file: Unit '".$this->name."', Turn ".self::convertFromImp($time)."\n";
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
  # Determines if a facing change is a HET or a TAC
  ###
  # Args are:
  # - (string) The time, in 'turn.impulses' notation
  # Returns:
  # - (string) Returns "HET" is a direction change greater than 1 facing
  #            Returns "TAC" if the speed is 0
  #            Returns "" if none of the above
  ###
  function isHetTac( $new, $old, $speed )
  {
    $newFaceOrd = ord(strtolower($new))-96;
    $oldFaceOrd = ord(strtolower($old))-96;
    $letterDistance = 5; # ord('f') - ord('a')
    $reason = "";

    # determine how many facings the turn encompases
    $distance = $newFaceOrd - $oldFaceOrd;
    if( abs($distance) < 4 ) # handles any facings that don't cross the A/F division
      $distance = $distance;
    else if( $distance < 0 ) # CW changes across the A/F barrier
      $distance = $letterDistance + $distance +1;
    else if( $distance > 0 ) # CCW changes across the A/F barrier
      $distance = $letterDistance - $distance -1;
     # $distance is 0-5. '+' is clockwise, '-' is CCW

     if( abs($distance) > 1 )
      $reason = "HET";
    else if( abs($distance) == 1 && $speed == 0 )
      $reason = "TAC";
    return $reason;
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
  # splits the 'turn.impulse' notation to turn and impulse
  ###
  # Args are:
  # - (string) The time, in 'turn.impulses' notation
  # Returns:
  # - (int) The number of turns that have occurred
  # - (int) The number of impulses that have occurred
  ###
  static function convertToTurn( $time )
  {
    if( ! str_contains( $time, "." ) )
      $time = convertFromImp( $time );
    return explode( ".", $time );
  }
  ###
  # Determines the basic unit type from the specific unit type
  # e.g. "LDR TCWL" is a basic type "ship"
  # See (A3.23)
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
    # handle all types of plasmas
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

    # assume anything left is a ship
    return "ship";
  }
}
?>
