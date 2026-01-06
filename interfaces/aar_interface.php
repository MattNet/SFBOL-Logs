#!/usr/bin/php -q
<?php
###
# Create an After-Action Report of the battle given by the logfile.
# This emphasizes decisions, consequences, and tactical pivots, not exhaustive mechanical replay
###

###
# Initialization
###
include_once("../LogFile.php");

const EVENT_RANGE_CHANGE   = 'range_change';
const EVENT_LAUNCH         = 'launch';
const EVENT_FIRE           = 'fire';
const EVENT_DAMAGE         = 'damage';
const EVENT_MANEUVER       = 'maneuver';
const EVENT_POWER          = 'power_change';
const EVENT_STATUS         = 'status_change';
const EVENT_END_STATE      = 'end_state';
const EVENT_UNKNOWN        = 'unknown';

$options = array( // CLI options
  'brief' => false,
  'header' => true,
  'footer' => false,
  'output' => null,
);

###
# CLI Configuration
###

$CLIoptions = "";
$CLIoptions .= "o::bfhn";
$CLIlong = array("out::", "help");

$CLI = getopt($CLIoptions, $CLIlong, $rest_index);
$CLIend = array_slice($argv, $rest_index);

# Adjust configuration from CLI
if (isset($CLI["o"])) $options['output'] = (int)$CLI["o"];
if (isset($CLI["out"])) $options['output'] = (int)$CLI["out"];
if (isset($CLI["b"])) $options['brief'] = true;
if (isset($CLI["brief"])) $options['brief'] = true;
if (isset($CLI["f"])) $options['footer'] = true;
if (isset($CLI["footer"])) $options['footer'] = true;
if (isset($CLI["n"])) $options['header'] = false;
if (isset($CLI["no-header"])) $options['header'] = false;
if (isset($CLI["h"]) || isset($CLI["help"])) errorOut("");

if ($CLI === false || empty($CLIend)) errorOut("Could not read command line arguments.");

###
# Input file handling
###
$readFile = $CLIend[0];
if (!isset($readFile) || !is_file($readFile) || !is_readable($readFile)) errorOut("Cannot read file '$readFile'.");

$input = file_get_contents($readFile);
if ($input === false) errorOut("Failed to read '$readFile'.");

###
# Initialize parser
###
$log = new LogFile($input);
if ($log->error != "") {
  echo $log->error;
  exit(1);
}

$principalUnits = array(); // list of ship units. format is ['type'=>string,'name'=>string,'index'=>$unitCache index]
$LastLine = 0;
$unitCache = $log->get_units();
$unitList = array();
$unitNameLookup = array(); // lookup of name to type

// Get the last line of the log sequence
foreach ($unitCache as $key=>$unit) {
  if ($unit["removed"] > $LastLine) $LastLine = $unit["removed"];
  $unitNameLookup[$unit["name"]] = $unit["type"];
  if($unit["basic"]=='ship')
    $principalUnits[] = [
      'index'=>$key,
      'name'=>$unit["name"],
      'type'=>$unit["type"]
    ];
}

###
# Iterate through impulses to build the turns
###
$impulseData = array();
for ($i = 0; $i <= $LastLine; $i++) { // $i = impulse in numeric notation
  $impulse = LogUnit::convertFromImp($i); // $impulse = impulse in 'turn.impulse" notation
  $impulseData[] = $log->read($impulse);
}
$turns = reconstructTurns($impulseData);
// once the turns are build, classify the events
foreach( $turns as &$turn){
  foreach( $turn as &$imp){
    $imp = classifyEvent($imp);
  }
}

$aarNarrative = synthesizeNarrative($turns);

###
# Final formatting
###
$lines = [];

if ($options['header']) {
  $lines[] = "### AFTER-ACTION REPORT ###";
  $lines[] = "";
}

$lines[] = trim($aarNarrative);

if ($options['footer']) {
  $lines[] = "";
  $lines[] = "### END REPORT ###";
}

$finalText = implode("\n", $lines) . "\n";

###
# Emit the final text
###
if ($options['output'] === null) {
  echo $finalText;
  exit(0);
}
$bytes = file_put_contents($options['output'], $finalText);
if ($bytes === false) {
  fwrite(STDERR, "ERROR: Unable to write output to {$options['output']}\n");
  exit(1);
}
exit(0);


###
# Reconstruct a unified, ordered timeline from impulse-based LogFile output
#
# @param array $impulseData Array indexed by numeric impulse (0-based),
#                           each value is the result of LogFile::read()
# @return array Formatted as [ turn =>
#                              [ event =>
#                                [ 'turn'=>int, 'impulse'=>int, 'segment'=>int, 'sequence'=>int, 'event'=>[output of $log->read()] ],
#                                ...
#                              ],
#                              ...
#                            ]
#               Where 'turn' and 'impulse' is the current time of the event(s).
#               Where 'segment' is the moment of the impulse that the event occurs.
#               Where 'sequence' is an iterator
###
function reconstructTurns(array $impulseData): array
{
    $turns = [];

    foreach ($impulseData as $impulseIndex => $impulsePayload) {
        // Skip empty or malformed impulses
        if (!is_array($impulsePayload) || empty($impulsePayload)) continue;

        // Derive temporal position
        $turnNumber    = intdiv($impulseIndex, 32) + 1;
        $impulseNumber = ($impulseIndex % 32) + 1;

        // Segment loop (movement, fire, seeking, etc.)
        foreach ($impulsePayload as $segmentName => $segmentEvents) {
            if (!is_array($segmentEvents)) continue;
            // Event loop inside segment
            foreach ($segmentEvents as $sequence => $event) {
                if (!is_array($event)) continue;
                $turns[$turnNumber][] = [
                    'turn'     => $turnNumber,
                    'impulse'  => $impulseNumber,
                    'segment'  => $segmentName,
                    'sequence' => $sequence,
                    'event'    => $event
                ];
            }
        }
    }
    return $turns;
}

###
# Classify a raw reconstructed event into a narrative-relevant category
#
# Filters out low-significance or bookkeeping-only events and assigns distilled data needed for narration
#
# @param array $entry Single reconstructed event from reconstructTurns(), with keys:
#                     'turn', 'impulse', 'segment', 'sequence', 'event'
# @return array|null  Classified event array, or null if the event should be ignored.
#                     Returned array format:
#                     [
#                       'turn'     => int,
#                       'impulse'  => int,
#                       'segment'  => string,
#                       'sequence' => int,
#                       'category' => string,
#                       'weight'   => int (0–100),
#                       'actors'   => array,
#                       'targets'  => array,
#                       'data'     => array (category-specific distilled details)
#                     ]
###
function classifyEvent(array $entry): ?array
{
    if (!isset($entry['segment'], $entry['event'])) return null;

    global $log,$principalUnits,$unitNameLookup;
    $segment = $entry['segment'];
    $event   = $entry['event'];

    switch ($segment) {
        case $log::SEQUENCE_MOVEMENT_SHIPS:
        case $log::SEQUENCE_MOVEMENT_SHUTTLES:
        case $log::SEQUENCE_MOVEMENT_SEEKERS:
        case $log::SEQUENCE_MOVEMENT_TAC:
        case $log::SEQUENCE_DIS_DEV_DECLARATION:
          $actors = ['role' => 'mover', 'name' => $event['owner'], 'unit' => $event['type']];
          $data   = [];
          $impulse = $entry['turn'] . "." . $entry['impulse'];
          $weight = 0;

          // get the range between this and the other ship(s)
          foreach($principalUnits as $PrUn)
          {
            if($PrUn['name']==$event["owner"]) continue;
            $data['range'][$PrUn['type']] = $log->get_unit_range( $event["owner"], $PrUn['name'], $impulse );

            if ($data['range'][$PrUn['type']] <= 3)  $weight = 90;
              elseif ($data['range'][$PrUn['type']] <= 5)  $weight = 70;
              elseif ($data['range'][$PrUn['type']] <= 8)  $weight = 50;
              elseif ($data['range'][$PrUn['type']] <= 15) $weight = 30;
              else $weight = 10;
          }
          if(!isset($event['turn'])) {}
          elseif ($event['turn']=='HET') {
            $data['HET'] = true;
            $weight = max($weight, 80);
          } 
          elseif ($event['turn']=='turn' || $event['turn']=='side-slip') {
            $data['maneuver'] = true;
            $weight = max($weight, 25);
          }
          if ($weight < 20) return null;
          return buildClassifiedEvent($entry, 'movement', $weight, $data, $actors);
          break;
//if($event['turn']!="move"){print_r($entry);exit();}//$unitNameLookup;

        case $log::SEQUENCE_FIRE_DECLARATION:
        case $log::SEQUENCE_CAST_WEB:
          if (empty($event['weapon'])) return null;
          $data = [ 'weapon' => $event['weapon'] ];
          $actors = ['role' => 'attacker', 'name' => $event['owner'], 'unit' => $event['type'], 'defender' => $unitNameLookup[$event['owner']] ];

          if (isset($event['hits'])) $data['hits'] = $event['hits'];
          if (isset($event['damage']) && $event['damage'] > 0) {
            $data['damage'] = $event['damage'];
            $weight = min(100, 40 + $event['damage']);
          } else {
            $weight = 15;
          }
          if ($weight < 25) return null;
          return buildClassifiedEvent($entry, 'fire', $weight, $data, $actors);
          break;

        case $log::SEQUENCE_LAUNCH_PLASMA:
        case $log::SEQUENCE_LAUNCH_DRONES:
          $data = [];
          $actors = ['role' => 'launcher', 'unit' => $event['type'], 'launchedType' => $unitNameLookup[$event['owner']] ];

          if (!empty($event['launch'])) {
            $data['launch'] = $event['launch'];
            $weight = 50;
          } elseif (!empty($event['impact'])) {
            $data['impact'] = $event['impact'];
            $weight = 70;
          } else {
            return null;
          }
          return buildClassifiedEvent($entry, 'seeking', $weight, $data, $actors);
          break;

        case $log::SEQUENCE_ESG_DAMAGE:
        case $log::SEQUENCE_ENVELOPER_DAMAGE:
        case $log::SEQUENCE_WEB_DAMAGE:
        case $log::SEQUENCE_SEEKER_DAMAGE:
        case $log::SEQUENCE_DAMAGE_ALLOCATION:
          if (!isset($event['damage'])) return null;
          $actors = ['role' => 'defender', 'unit' => $event['type']];
          $damage = (int)$event['damage'];

          if ($damage <= 0) return null;
          $data = [ 'damage' => $damage ];
          if (!empty($event['shield'])) $data['shield'] = $event['shield'];
          if (!empty($event['internals'])) {
            $data['internals'] = $event['internals'];
            $weight = 80;
          } else {
            $weight = min(70, 30 + $damage);
          }
          return buildClassifiedEvent($entry, 'damage', $weight, $data, $actors);
          break;

        case $log::SEQUENCE_SPEED_CHANGES:
        case $log::SEQUENCE_EMER_DECEL_EFFECT:
          $data = [];
          $actors = ['role' => 'speed_change', 'name' => $event['owner'], 'unit' => $event['type']];
          $weight = 0;

          if (isset($event['speed'])) {
            $data['speed'] = $event['speed'];
            $weight = 40;
          }
          if ($weight < 30) return null;
          return buildClassifiedEvent($entry, 'power', $weight, $data, $actors);
          break;

        case $log::SEQUENCE_CLOAKING_DEVICE:
        case $log::SEQUENCE_TRACTORS:
        case $log::SEQUENCE_BREAKDOWNS:
          $actors = ['role' => 'mover', 'name' => $event['owner'], 'unit' => $unitNameLookup[ $event['owner'] ]] ;
          $data = [];
          $weight = 0;
          foreach (['tractor', 'cloak', 'breakdown', 'concede'] as $key) {
            if (!empty($event[$key])) {
              $data[$key] = true;
              $weight = 90;
            }
          }
          if ($weight === 0) return null;
          return buildClassifiedEvent($entry, 'status', $weight, $data, $actors);
          break;
        default:
            // Unknown or bookkeeping-only segment
            return null;
    }
}
###
# Construct a normalized classified event record
#
# Centralizes the output structure for all classified events so that
# later stages (chaining and narration) can assume a consistent schema.
#
# @param array  $entry    Original reconstructed event entry
# @param string $category Narrative category (movement, fire, damage, etc.)
# @param int    $weight   Narrative importance weight (0–100)
# @param array  $data     Minimal category-specific data needed for narration
#
# @return array Normalized classified event with the following keys:
#               'turn', 'impulse', 'segment', 'sequence',
#               'category', 'weight', 'actors', 'targets', 'data'
###
function buildClassifiedEvent(array $entry, string $category, int $weight, array $data, array $actors): array
{
    return [
        'turn'     => $entry['turn'],
        'impulse'  => $entry['impulse'],
        'segment'  => $entry['segment'],
        'sequence' => $entry['sequence'],
        'category' => $category,
        'weight'   => $weight,
        'actors'   => $actors,
        'targets'  => $entry['event']['target'] ?? [],
        'data'     => $data
    ];
}
###
# Convert classified events into a turn-by-turn narrative
#
# Groups events by turn, orders them chronologically, collapses related
# events into narrative chains, and produces a readable after-action
# report emphasizing decisions and outcomes rather than mechanics.
#
# @param array $classifiedTurns Array indexed by turn number,
#                               each value is an array of classified events
#                               as returned by classifyEvent()
#
# @return string Fully formatted narrative text, divided by turns
###
function synthesizeNarrative(array $classifiedTurns): string
{
    $output = [];
    ksort($classifiedTurns);

    foreach ($classifiedTurns as $turn => $events) {
        $events = array_filter($events, 'is_array');
        if (empty($events)) continue;

        usort($events, function ($a, $b) { // Sort strictly by time
        return [$a['impulse'], $a['sequence']]
             <=> [$b['impulse'], $b['sequence']];
        });

        $lines = [];
        $lines[] = "T{$turn}:";

        // Collapse events into narrative chains
        $chains = chainEvents($events);
        foreach ($chains as $chain) {
          $lines[] = "  " . narrateEventChain($chain);
        }

        $output[] = implode("\n", $lines);
        $output[] = ""; // blank line between turns
      }
    return trim(implode("\n", $output));
}
###
# Collapse adjacent classified events into narrative chains
#
# Events may be chained when they:
#   - Occur on the same impulse
#   - Share the same narrative category
#   - Have contiguous sequence numbers
#
# This reduces repetitive narration and enables higher-level summaries
# such as "fires multiple weapons" or "takes combined damage."
#
# @param array $events Array of classified events for a single turn,
#                      sorted by impulse and sequence
#
# @return array Array of event chains, where each chain is an array of
#               one or more classified events
###
function chainEvents(array $events): array
{
    $chains = [];
    $current = [];

    foreach ($events as $event) {
        if (empty($current)) {
            $current[] = $event;
            continue;
        }
        $prev = end($current);
        $canChain =
            $event['impulse'] === $prev['impulse'] &&
            $event['category'] === $prev['category'] &&
            $event['sequence'] === $prev['sequence'] + 1;
        if ($canChain) {
            $current[] = $event;
        } else {
            $chains[] = $current;
            $current = [$event];
        }
    }
    if (!empty($current)) $chains[] = $current;
    return $chains;
}
###
# Generate narrative prose for a single chain of classified events
#
# Converts a sequence of related classified events into a concise
# English sentence that reflects tactical intent and outcome.
#
# The narration logic is category-specific and may summarize:
#   - Range changes and maneuver intent
#   - Weapon fire and effectiveness
#   - Damage severity and shield/internal outcomes
#   - Power adjustments and status changes
#
# @param array $chain Array of classified events belonging to the same chain
#
# @return string Human-readable narrative sentence describing the chain
###
function narrateEventChain(array $chain): string
{
    global $unitNameLookup;
    $category = $chain[0]['category'];
    $actors = $chain[0]['actors'] ?? [];
    $primary = $actors['unit'];

    switch ($category) {
        case 'movement':
          $ranges = []; // Format is: $ranges['other ship unit'] => (int); Supports 3 or more ship units
          foreach ($chain as $e) {
            if (!isset($e['data']['range'])) continue;
            foreach($e['data']['range'] as $u=>$r){
              if($primary !== null)
                return "$primary closes to range $r to $u.";
              else
                return "Closes to range $r to $u.";
            }
          }
          foreach ($chain as $e) {
            if (!empty($e['data']['HET'])) {
              if($primary !== null)
                return "$primary executes a high-energy turn.";
              else
                return "Executes a high-energy turn.";
            }
          }
          if($primary !== null)
            return "$primary maneuvers for position.";
          else
            return "Maneuvers for position.";
          break;

        case 'fire':
          $weapons = [];
          $damage  = 0;
          foreach ($chain as $e) {
            if (!empty($e['data']['weapon']))
              $weapons[] = $e['data']['weapon'];
            if (!empty($e['data']['damage']))
              $damage += $e['data']['damage'];
          }
          $weaponText = implode(", ", array_unique($weapons));
          if ($damage > 0) {
            if($primary !== null)
              return "$primary fires {$weaponText}, scoring {$damage} damage.";
            else
              return "Fires {$weaponText}, scoring {$damage} damage.";
          }
          if($primary !== null)
            return "$primary fires {$weaponText} with little effect.";
          else
            return "Fires {$weaponText} with little effect.";
          break;

        case 'seeking':
          foreach ($chain as $e) {
            if (isset($e['data']['impact'])) {
              if($primary !== null)
                return "$primary suffers seeking weapons impacts.";
              else
                return "Seeking weapons impact.";
            }
          }
          if($primary !== null)
            return "$primary launches seeking weapons.";
          else
            return "Launches seeking weapons.";
          break;

        case 'damage':
          $total = 0;
          $internals = 0;
          $shield = null;

          foreach ($chain as $e) {
            $total += $e['data']['damage'] ?? 0;
            if (!empty($e['data']['internals']))
              $internals += (int)$e['data']['internals'];
            if (!empty($e['data']['shield']))
              $shield = $e['data']['shield'];
          }

          if ($internals > 0) {
            if($primary !== null)
              return "$primary takes {$internals} internals through the {$shield} shield.";
            else
              return "Takes {$internals} internals through the {$shield} shield.";
          }

          if ($shield !== null) {
            if($primary !== null)
              return "$primary shield {$shield} takes {$total} damage.";
            else
              return "Shield {$shield} takes {$total} damage.";
          }
          if($primary !== null)
            return "$primary damage is absorbed.";
          else
            return "Damage is absorbed.";
          break;

        case 'power':
          foreach ($chain as $e) {
            if (isset($e['data']['speed'])) {
              if($primary !== null)
                return "$primary adjusts speed.";
              else
                return "Adjusts speed.";
            }
            if (isset($e['data']['reinforcement']))
              return "Surges reinforcement.";
          }
          return "Reallocates power.";
          break;

        case 'status':
          foreach ($chain as $e) {
            if (!empty($e['data']['concede']))
              return "Opponent concedes.";
            if (!empty($e['data']['breakdown']))
              return "Breakdown ends the engagement.";
            if (!empty($e['data']['tractor']))
              return "Establishes tractor.";
            if (!empty($e['data']['cloak']))
              return "Engages cloak.";
          }
          return "Status changes.";
          break;

        default:
            return '';
    }
}

###
# CLI help output
###
function errorOut(string $message): void {
  global $argv, $FRAMESPERACTION, $FRAMESFORMOVE, $MOVEMENTTRAILS;
  echo "\n";
  if ($message !== null && $message != "") echo $message . "\n\n";
  echo "Usage:\n  {$argv[0]} [OPTIONS..] /path/to/log\n";
  echo "Options:\n";
  echo "  -o, --out <FILE>    Write to this file\n";
  echo "  -h, --help          Show this help message\n";
  exit(1);
}
?>

