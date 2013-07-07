<?php

// get the input file
$input = $argv[1];
$frequency = $argv[2];

// image scaling
$h = 90;
$w = 160;
$web_path = "http://cdn.cs76.net/lectures/1";

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

        // remove the images
        foreach ($image_stack as $key => $used)
        {

            // add to VTT file
            $vtt .= "\n";
            $vtt .= convert_s_tc(($current_stack * $images_per_stack + $key) * $frequency) . " --> " . convert_s_tc(($current_stack * $images_per_stack + ($key + 1)) * $frequency) . "\n";
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