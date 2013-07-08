<?php

require_once(dirname(__FILE__)) . "/getopts.php";

// defaults
$input = "";
$frequency = 5;
$h = 90;
$w = 160;
$web_path = "http://cdn.cs76.net/lectures/1";

$opts = getopts(array(
        "f" => array("switch" => array("f", "frequency"), "type" => GETOPT_VAL),
        "h" => array("switch" => array("h", "height"), "type" => GETOPT_VAL),
        "i" => array("switch" => array("i", "input"), "type" => GETOPT_VAL),
        "p" => array("switch" => array("p", "path"), "type" => GETOPT_VAL), 
        "w" => array("switch" => array("w", "width"), "type" => GETOPT_VAL), 
        $argv));

// handle command line args
foreach (array_keys($opts) as $opt)
{
    if (is_numeric($opt))
    {
        continue;
    }
    

    switch ($opt)
    {
        // ignore unknown commandline input
        case "cmdline":
            continue;
        break;

        case "f":
            if ($opts[$opt] === 0)
            {
                // no heights specified, use default
                echo "Using default frequency: $frequency\n";
            } 
            else if (is_numeric($opts[$opt]) && $opts[$opt] > 0)
            {
                // user specified height
                $frequency = $opts[$opt];
            }
            else
            {
                error_log("Invalid frequency: " . $opts[$opt] . "\n");
                exit(1);
            }
        break;

        case "h":
            if ($opts[$opt] === 0)
            {
                // no heights specified, use default
                echo "Using default height: $h\n";
            } 
            else if (is_numeric($opts[$opt]) && $opts[$opt] > 0)
            {
                // user specified height
                $h = $opts[$opt];
            }
            else
            {
                error_log("Invalid Height: " . $opts[$opt] . "\n");
                exit(1);
            }
        break;

        case "w":
            if ($opts[$opt] === 0)
            {
                // no heights specified, use default
                echo "Using default width: $h\n";
            } 
            else if (is_numeric($opts[$opt]) && $opts[$opt] > 0)
            {
                // user specified height
                $w = $opts[$opt];
            }
            else
            {
                error_log("Invalid Width: " . $opts[$opt] . "\n");
                exit(1);
            }
        break;

        case "i":
            if (file_exists($opts[$opt]))
            {
                // user specified height
                $input = $opts[$opt];
            }
            else
            {
                error_log("Invalid Input: " . $opts[$opt] . "\n");
                exit(1);
            }
        break;

        case "p":
            if ($opts[$opt] !== 0 && strlen($opts[$opt] > 0))
            {
                // user specified a path
                $web_path = $opts[$opt];
            }
            else
            {
                echo "Path not specified, using default: $web_path\n";
            }
        break;

        default:
            error_log("Unknown argument: " . $opt . " => " . $opts[$opt]);
    }
}



// get the input file's basename
$base = pathinfo($input, PATHINFO_FILENAME);

// probe the input file
$success = exec("ffprobe $input 2>&1 >> /dev/null", $array);

if ($success)
{
    foreach ($array as $line)
    {
        // extract FPS
        if (preg_match("/([0-9\.]+)\sfps/", $line, $matches) == 1)
        {
            $fps = $matches[1];
        }

        // extract duration
        if (preg_match("/Duration:\s(\d{0,2}:\d{0,2}:\d{0,2}\.\d{0,2})/", $line, $matches) == 1)
        {
            $duration = $matches[1];
            echo "Duration: $duration\n";
        }
    }   
}


// make sure we got the fps
if (!isset($fps))
{
    error_log("No fps\n");
    exit(1);
}

// get duration in seconds
preg_match("/^(\d{0,2}):/", $duration, $matches);
$hours = (int) $matches[1];

preg_match("/:(\d{0,2}):/", $duration, $matches);
$mins = (int) $matches[1] + ($hours * 60);

preg_match("/:(\d{0,2})\./", $duration, $matches);
$secs = (int) $matches[1] + ($mins * 60);

preg_match("/\.(\d{0,2})/", $duration, $matches);
$secs += (int) $matches[1] / 100;

echo "secs: $secs\n";
echo "frames: " . floor((29.97 * $secs)) . "\n";
echo "preview images will be " . floor($secs / $frequency) . "\n";

try {
    mkdir($base . "_stills");
} catch (Exception $e) {
    echo "file exists\n";
}

// generate stills
// http://ffmpeg.org/trac/ffmpeg/wiki/Create%20a%20thumbnail%20image%20every%20X%20seconds%20of%20the%20video
echo "Generating stills every $frequency seconds";
file_put_contents($base . "_stills/.$base", "");


$cmd = "ffmpeg -i $input -f image2 -vf \"fps=fps=(1/$frequency),scale=" . $w . "x" . $h . "\" " . $base . "_stills/" . $base . "-single_%04d.jpg  2>&1 >> /dev/null && rm " . $base . "_stills/.$base";
echo $cmd . "\n";

$success = exec($cmd);

// wait until stills are done
while(file_exists($base . "_stills/.$base"))
{
    echo ".";
    sleep(1);
}
echo "\n";

/*** stitch stills together ***/
$listing = preg_grep("/^([^.]?" . $base . "-single)/", scandir($base . "_stills"));
$listing = array_values($listing);


$image_stack = array();
$images_per_stack = 5;
$current_stack = 0;
$vtt = "WEBVTT\n";

var_dump($listing);
foreach ($listing as $img)
{    
    // add the image to the stack
    $image_stack[] = $base . "_stills/$img";

    // if the current stack is full, write the sprite 
    if (count($image_stack) >= $images_per_stack || strcmp("$img", $listing[count($listing) - 1]) === 0)
    {
        var_dump($image_stack);
        file_put_contents($base . "_stills/.stitch", "");
        exec("convert " . implode(" ", $image_stack) . " -append " . $base . "_stills/$base" . "-" . $current_stack . ".jpg && rm $base" . "_stills/.stitch");
        
        while (file_exists($base . "_stills/.stitch"))
        {
            sleep(1);
        }

        // print the line for each input file
        foreach ($image_stack as $key => $used)
        {
            // vars for timecode
            $tc1 = ($current_stack * $images_per_stack + $key) * $frequency;
            $tc2 = ($current_stack * $images_per_stack + ($key + 1)) * $frequency;
            
            // check if the current point in time is beyond the end of the movie
            if ($tc1 > $secs)
            {
                continue;
            }
            // if the first point is not beyond the duration but the second point is, make the second point the last frame
            else if($tc2 > $secs)
            {
                $tc2 = $secs;
            }

            // add to VTT file
            $vtt .= "\n";
            $vtt .= convert_s_tc($tc1) . " --> " . convert_s_tc($tc2) . "\n";
            $vtt .= $web_path . "/" . $base . "-" . $current_stack . ".jpg#xywh=" . /* xpos: */ "0" . "," . /* ypos: */ ($h * $key) . "," . /* width: */ $w . "," . /* height */ $h . "\n";


            // removing
            unlink($used);
        }

        // unset the image stack
        $image_stack = array();
        $current_stack++;
    }
}

file_put_contents("$base.vtt", $vtt);


function convert_s_tc($seconds)
{
    $milliseconds = $seconds * 1000;
    $seconds = floor($milliseconds / 1000);
    $minutes = floor($seconds / 60);
    $hours = floor($minutes / 60);
    $milliseconds = $milliseconds % 1000;
    $seconds = $seconds % 60;
    $minutes = $minutes % 60;

    $format = '%02u:%02u:%02u.%03u';
    $time = sprintf($format, $hours, $minutes, $seconds, $milliseconds);
    return $time;
}



?>