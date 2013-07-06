<?php

// get the input file
$input = $argv[1];
$frequency = $argv[2];

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

$success = exec("ffmpeg -i $input -f image2 -vf \"fps=fps=(1/$frequency)\" " . $base . "_stills/" . $base . "_%04d.jpg 2>&1 >> /dev/null && rm " . $base . "_stills/.$base");

// wait until stills are done
while(file_exists($base . "_stills/.$base"))
{
    echo ".";
    sleep(1);
}
echo "\n";

/*** stitch stills together ***/
$listing = preg_grep("/^([^.]?" . $base . "_)/", scandir($base . "_stills"));
$listing = array_values($listing);


$image_stack = array();
$images_per_stack = 5;
$current_stack = 0;

var_dump($listing);
foreach ($listing as $img)
{
    echo "Current: $img, Last: " . $listing[count($listing) - 1] . " " . count($listing) . "\n";
    
    // add the image to the stack
    $image_stack[] = $base . "_stills/$img";

    // if the current stack is full, write the sprite 
    if (count($image_stack) >= $images_per_stack || strcmp("$img", $listing[count($listing) - 1]) === 0)
    {
        echo "stack full or end\n";
        var_dump($image_stack);
        file_put_contents($base . "_stills/.stitch", "");
        echo "STITCHIN: " . implode(" ", $image_stack) . "\n";
        exec("convert " . implode(" ", $image_stack) . " -append " . $base . "_stills/$base" . "_sprite_" . $current_stack . ".jpg && rm $base" . "_stills/.stitch");
        
        while (file_exists($base . "_stills/.stitch"))
        {
            sleep(1);
        }

        // remove the images
        foreach ($image_stack as $used)
        {
            echo "REMOVING: $used\n";
            // removing
            unlink($used);

        }

        // unset the image stack
        $image_stack = array();
        $current_stack++;

    }


    
}




?>