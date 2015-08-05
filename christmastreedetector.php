<?php

ini_set('memory_limit', '1024M');

header("Content-type: image/png");

$chosenImage = 6;

switch($chosenImage){
    case 1:
        $inputImage     = imagecreatefromjpeg("nmzwj.jpg");
        break;
    case 2:
        $inputImage     = imagecreatefromjpeg("2y4o5.jpg");
        break;
    case 3:
        $inputImage     = imagecreatefromjpeg("YowlH.jpg");
        break;
    case 4:
        $inputImage     = imagecreatefromjpeg("2K9Ef.jpg");
        break;
    case 5:
        $inputImage     = imagecreatefromjpeg("aVZhC.jpg");
        break;
    case 6:
        $inputImage     = imagecreatefromjpeg("FWhSP.jpg");
        break;
    case 7:
        $inputImage     = imagecreatefromjpeg("roemerberg.jpg");
        break;
    default:
        exit();
}

// Process the loaded image

$topNspots = processImage($inputImage);

imagejpeg($inputImage);
imagedestroy($inputImage);

// Here be functions

function processImage($image) {
    $orange = imagecolorallocate($image, 220, 210, 60);
    $black = imagecolorallocate($image, 0, 0, 0);
    $red = imagecolorallocate($image, 255, 0, 0);

    $maxX = imagesx($image)-1;
    $maxY = imagesy($image)-1;

    // Parameters
    $spread = 1; // Number of pixels to each direction that will be added up
    $topPositions = 80; // Number of (brightest) lights taken into account
    $minLightDistance = round(min(array($maxX, $maxY)) / 30); // Minimum number of pixels between the brigtests lights
    $searchYperX = 5; // spread of the "search beam" from the median point to the top

    $renderStage = 3; // 1 to 3; exits the process early


    // STAGE 1
    // Calculate the brightness of each pixel (R+G+B)

    $maxBrightness = 0;
    $stage1array = array();

    for($row = 0; $row <= $maxY; $row++) {

        $stage1array[$row] = array();

        for($col = 0; $col <= $maxX; $col++) {

            $rgb = imagecolorat($image, $col, $row);
            $brightness = getBrightnessFromRgb($rgb);
            $stage1array[$row][$col] = $brightness;

            if($renderStage == 1){
                $brightnessToGrey = round($brightness / 765 * 256);
                $greyRgb = imagecolorallocate($image, $brightnessToGrey, $brightnessToGrey, $brightnessToGrey);
                imagesetpixel($image, $col, $row, $greyRgb);
            }

            if($brightness > $maxBrightness) {
                $maxBrightness = $brightness;
                if($renderStage == 1){
                    imagesetpixel($image, $col, $row, $red);
                }
            }
        }
    }
    if($renderStage == 1) {
        return;
    }


    // STAGE 2
    // Add up brightness of neighbouring pixels

    $stage2array = array();
    $maxStage2 = 0;

    for($row = 0; $row <= $maxY; $row++) {
        $stage2array[$row] = array();

        for($col = 0; $col <= $maxX; $col++) {
            if(!isset($stage2array[$row][$col])) $stage2array[$row][$col] = 0;

            // Look around the current pixel, add brightness
            for($y = $row-$spread; $y <= $row+$spread; $y++) {
                for($x = $col-$spread; $x <= $col+$spread; $x++) {

                    // Don't read values from outside the image
                    if($x >= 0 && $x <= $maxX && $y >= 0 && $y <= $maxY){
                        $stage2array[$row][$col] += $stage1array[$y][$x]+10;
                    }
                }
            }

            $stage2value = $stage2array[$row][$col];
            if($stage2value > $maxStage2) {
                $maxStage2 = $stage2value;
            }
        }
    }

    if($renderStage >= 2){
        // Paint the accumulated light, dimmed by the maximum value from stage 2
        for($row = 0; $row <= $maxY; $row++) {
            for($col = 0; $col <= $maxX; $col++) {
                $brightness = round($stage2array[$row][$col] / $maxStage2 * 255);
                $greyRgb = imagecolorallocate($image, $brightness, $brightness, $brightness);
                imagesetpixel($image, $col, $row, $greyRgb);
            }
        }
    }

    if($renderStage == 2) {
        return;
    }


    // STAGE 3

    // Create a ranking of bright spots (like "Top 20")
    $topN = array();

    for($row = 0; $row <= $maxY; $row++) {
        for($col = 0; $col <= $maxX; $col++) {

            $stage2Brightness = $stage2array[$row][$col];
            $topN[$col.":".$row] = $stage2Brightness;
        }
    }
    arsort($topN);

    $topNused = array();
    $topPositionCountdown = $topPositions;

    if($renderStage == 3){
        foreach ($topN as $key => $val) {
            if($topPositionCountdown <= 0){
                break;
            }

            $position = explode(":", $key);

            foreach($topNused as $usedPosition => $usedValue) {
                $usedPosition = explode(":", $usedPosition);
                $distance = abs($usedPosition[0] - $position[0]) + abs($usedPosition[1] - $position[1]);
                if($distance < $minLightDistance) {
                    continue 2;
                }
            }

            $topNused[$key] = $val;

            paintCrosshair($image, $position[0], $position[1], $red, 2);

            $topPositionCountdown--;

        }
    }


    // STAGE 4
    // Median of all Top N lights
    $topNxValues = array();
    $topNyValues = array();

    foreach ($topNused as $key => $val) {
        $position = explode(":", $key);
        array_push($topNxValues, $position[0]);
        array_push($topNyValues, $position[1]);
    }

    $medianXvalue = round(calculate_median($topNxValues));
    $medianYvalue = round(calculate_median($topNyValues));
    paintCrosshair($image, $medianXvalue, $medianYvalue, $red, 15);


    // STAGE 5
    // Find treetop

    $filename = 'debug.log';
    $handle = fopen($filename, "w");
    fwrite($handle, "\n\n STAGE 5");

    $treetopX = $medianXvalue;
    $treetopY = $medianYvalue;

    $searchXmin = $medianXvalue;
    $searchXmax = $medianXvalue;

    $width = 0;
    for($y = $medianYvalue; $y >= 0; $y--) {
        fwrite($handle, "\nAt y = ".$y);

        if(($y % $searchYperX) == 0) { // Modulo
            $width++;
            $searchXmin = $medianXvalue - $width;
            $searchXmax = $medianXvalue + $width;
            imagesetpixel($image, $searchXmin, $y, $red);
            imagesetpixel($image, $searchXmax, $y, $red);
        }

        foreach ($topNused as $key => $val) {
            $position = explode(":", $key); // "x:y"

            if($position[1] != $y){
                continue;
            }

            if($position[0] >= $searchXmin && $position[0] <= $searchXmax){
                $treetopX = $position[0];
                $treetopY = $y;
            }
        }

    }

    paintCrosshair($image, $treetopX, $treetopY, $red, 5);


    // STAGE 6
    // Find tree sides
    fwrite($handle, "\n\n STAGE 6");

    $treesideAngle = 60; // The extremely "fat" end of a christmas tree
    $treeBottomY = $treetopY;

    $topPositionsExcluded = 0;
    $xymultiplier = 0;
    while(($topPositionsExcluded < ($topPositions / 5)) && $treesideAngle >= 1){
        fwrite($handle, "\n\nWe're at angle ".$treesideAngle);
        $xymultiplier = sin(deg2rad($treesideAngle));
        fwrite($handle, "\nMultiplier: ".$xymultiplier);

        $topPositionsExcluded = 0;
        foreach ($topNused as $key => $val) {
            $position = explode(":", $key);
            fwrite($handle, "\nAt position ".$key);

            if($position[1] > $treeBottomY) {
                $treeBottomY = $position[1];
            }

            // Lights above the tree are outside of it, but don't matter
            if($position[1] < $treetopY){
                $topPositionsExcluded++;
                fwrite($handle, "\nTOO HIGH");
                continue;
            }

            // Top light will generate division by zero
            if($treetopY-$position[1] == 0) {
                fwrite($handle, "\nDIVISION BY ZERO");
                continue;
            }

            // Lights left end right of it are also not inside
            fwrite($handle, "\nLight position factor: ".(abs($treetopX-$position[0]) / abs($treetopY-$position[1])));
            if((abs($treetopX-$position[0]) / abs($treetopY-$position[1])) > $xymultiplier){
                $topPositionsExcluded++;
                fwrite($handle, "\n --- Outside tree ---");
            }
        }

        $treesideAngle--;
    }
    fclose($handle);

    // Paint tree's outline
    $treeHeight = abs($treetopY-$treeBottomY);
    $treeBottomLeft = 0;
    $treeBottomRight = 0;
    $previousState = false; // line has not started; assumes the tree does not "leave"^^

    for($x = 0; $x <= $maxX; $x++){
        if(abs($treetopX-$x) != 0 && abs($treetopX-$x) / $treeHeight > $xymultiplier){
            if($previousState == true){
                $treeBottomRight = $x;
                $previousState = false;
            }
            continue;
        }
        imagesetpixel($image, $x, $treeBottomY, $red);
        if($previousState == false){
            $treeBottomLeft = $x;
            $previousState = true;
        }
    }
    imageline($image, $treeBottomLeft, $treeBottomY, $treetopX, $treetopY, $red);
    imageline($image, $treeBottomRight, $treeBottomY, $treetopX, $treetopY, $red);


    // Print out some parameters

    $string = "Min dist: ".$minLightDistance." | Tree angle: ".$treesideAngle." deg | Tree bottom: ".$treeBottomY;

    $px     = (imagesx($image) - 6.5 * strlen($string)) / 2;
    imagestring($image, 2, $px, 5, $string, $orange);

    return $topN;
}

/**
 * Returns values from 0 to 765
 */
function getBrightnessFromRgb($rgb) {
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;

    return $r+$r+$b;
}

function paintCrosshair($image, $posX, $posY, $color, $size=5) {
    for($x = $posX-$size; $x <= $posX+$size; $x++) {
        if($x>=0 && $x < imagesx($image)){
            imagesetpixel($image, $x, $posY, $color);
        }
    }
    for($y = $posY-$size; $y <= $posY+$size; $y++) {
        if($y>=0 && $y < imagesy($image)){
            imagesetpixel($image, $posX, $y, $color);
        }
    }
}

// From http://www.mdj.us/web-development/php-programming/calculating-the-median-average-values-of-an-array-with-php/
function calculate_median($arr) {
    sort($arr);
    $count = count($arr); //total numbers in array
    $middleval = floor(($count-1)/2); // find the middle value, or the lowest middle value
    if($count % 2) { // odd number, middle is the median
        $median = $arr[$middleval];
    } else { // even number, calculate avg of 2 medians
        $low = $arr[$middleval];
        $high = $arr[$middleval+1];
        $median = (($low+$high)/2);
    }
    return $median;
}


?>
