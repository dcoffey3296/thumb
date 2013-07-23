<?php

require_once(dirname(__FILE__)) . "/getopts.php";

// defaults
$input = "";
$frequency = 5;
$h = 90;
$w = 160;
$web_path = "http://cdn.cs76.net/2013/summer/lectures/0/thumbnails";
$thumb_dir = "thumbnails";
$images_per_stack = 12;

// 1 to 100, 0 being lowest quality
$quality = 35;

if (count(preg_grep("/help/", $argv)))
{
    print_help();
    exit(1);
}

$opts = getopts(array(
        "f" => array("switch" => array("f", "frequency"), "type" => GETOPT_VAL),
        "h" => array("switch" => array("h", "height"), "type" => GETOPT_VAL),
        "help" => array("switch" => array("help"), "type" => GETOPT_VAL),
        "i" => array("switch" => array("i", "input"), "type" => GETOPT_VAL),
        "p" => array("switch" => array("p", "path"), "type" => GETOPT_VAL),
        "q" => array("switch" => array("q", "quality"), "type" => GETOPT_VAL),
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

        case "help":
            if ($opts[$opt] !== 0)
            {
                // help
                print_help();
                exit(0);
            } 
            else if (is_numeric($opts[$opt]) && $opts[$opt] > 0)
        break;

        case "w":
            if ($opts[$opt] === 0)
            {
                // no heights specified, use default
                echo "Using default width: $w\n";
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

        case "n":
            if (strlen($opts[$opt]) > 0)
            {
                echo "Name is set to " . $opts[$opt] . "\n";
                $base = $opts[$opt];
            }
            else
            {
                error_log("Using default name.\n");
            }
        break;

        case "p":
            if ($opts[$opt] !== 0 && strlen($opts[$opt]) > 0)
            {
                // user specified a path
                $web_path = $opts[$opt];
		echo "Using path " . $opts[$opt] . "\n";
            }
            else
            {
                echo "Path not specified, using default: $web_path\n";
            }
        break;

        case "q":
            if ($opts[$opt] > 0 && $opts[$opt] <= 100)
            {
                // user specified a path
                $quality = $opts[$opt];
                echo "Quality is set to: $quality";
            }
            else
            {
                echo "Quality not specified, using default: $quality\n";
            }
        break;

        default:
            error_log("Unknown argument: " . $opt . " => " . $opts[$opt]);
    }
}



// get the input file's basename
if (isset($base));
{
    $base = pathinfo($input, PATHINFO_FILENAME);
}

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
// echo "preview images will be " . floor($secs / $frequency) . "\n";

try {
    mkdir($thumb_dir);
} catch (Exception $e) {
    echo "file exists\n";
}

// generate stills
// http://ffmpeg.org/trac/ffmpeg/wiki/Create%20a%20thumbnail%20image%20every%20X%20seconds%20of%20the%20video
echo "Generating stills every $frequency seconds";
file_put_contents("$thumb_dir/.$base", "");


$cmd = "ffmpeg -i $input -f image2 -vf \"fps=fps=(1/$frequency),scale=" . $w . "x" . $h . "\" $thumb_dir/" . $base . "-single_%04d.jpg > /dev/null 2>&1 && rm $thumb_dir/.$base";
// echo $cmd . "\n";

$success = exec($cmd);

// wait until stills are done
while(file_exists("$thumb_dir/" . $base))
{
    echo ".";
    sleep(1);
}
echo "\n";

/*** stitch stills together ***/
$listing = preg_grep("/^([^.]?" . $base . "-single)/", scandir("$thumb_dir"));
$listing = array_values($listing);

$image_stack = array();
$current_stack = 0;
$vtt = "WEBVTT\n";

// var_dump($listing);
foreach ($listing as $img)
{    
    // add the image to the stack
    $image_stack[] = "$thumb_dir/$img";

    // if the current stack is full, write the sprite 
    if (count($image_stack) >= $images_per_stack || strcmp("$img", $listing[count($listing) - 1]) === 0)
    {
        // var_dump($image_stack);
        // make a lock file while we stitch to block
        file_put_contents("$thumb_dir/.stitch-" . $base, "");

        // stitch the images together
        exec("convert " . implode(" ", $image_stack) . " -quality $quality -append $thumb_dir/$base" . "-" . $current_stack . ".jpg");

        // compress the image


        // remove our lock file
        exec("rm $thumb_dir/.stitch-" . $base);
        
        while (file_exists("$thumb_dir/.stitch"))
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
            $vtt .= $web_path . "/$thumb_dir/" . $base . "-" . $current_stack . ".jpg#xywh=" . /* xpos: */ "0" . "," . /* ypos: */ ($h * $key) . "," . /* width: */ $w . "," . /* height */ $h . "\n";


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

function print_help()
{
    global $input, $frequency, $h, $w, $web_path, $thumb_dir, $images_per_stack, $quality;

    echo "\n\n********** THUMBNAIL GENERATOR **********\n"
    . "PHP utility to generate thumbnails and a vtt file with stacks of images.\n\n"
    . "Usage: php thumbnail.php -i <input file> -p <path> [options]\n\n"
    . "options:\n"
    . "-f, frequency <int>\n"
    . "    The frequency in seconds in which to extract a still frame. Default: $frequency.\n"
    . "-h, height <int>\n"
    . "    The height in pixels of the thumbnails to extract. Default: $h.\n"
    . "-help\n"
    . "    Print help and exit.\n"
    . "-i, input <input file>\n"
    . "    the file to generate the thumbnails from.\n"
    . "-p, path <path to file>, example: http://cdn.cs76.net/2013/summer/lectures/0\n"
    . "    The full path to where the thumbnails will live on the server.\n"
    . "-q, quality <int>\n"
    . "    The quality from 1 to 100 (1 being worst) of the still images.  Default: $quality.\n"
    . "-w, width <int>\n"
    . "    The width in pixels of the thumbnails to extract. Default: $w\n\n";

}



?>
